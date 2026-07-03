<?php
/**
 * Salon Booking API client for Dental appointments.
 */

namespace FacturaScripts\Plugins\Dental\Lib;

use DateTimeImmutable;
use FacturaScripts\Core\Http;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Dental\Model\Cita;
use FacturaScripts\Plugins\Dental\Model\Especialista;
use FacturaScripts\Plugins\Dental\Model\Paciente;
use FacturaScripts\Plugins\Dental\Model\TratamientoPaciente;
use Throwable;

class SalonBookingClient
{
    private const LOG_CHANNEL = 'dental-salon';
    private const SETTINGS_GROUP = 'dental_salon';
    private const API_PATH = '/wp-json/salon/api/v1';

    /** @var array */
    private $settings;

    /** @var string */
    private $token = '';

    public function __construct(?array $settings = null)
    {
        $this->settings = $settings ?? self::loadSettings();
    }

    public static function loadSettings(): array
    {
        return [
            'wp_url' => rtrim(trim((string)Tools::settings(self::SETTINGS_GROUP, 'wp_url', '')), '/'),
            'api_username' => trim((string)Tools::settings(self::SETTINGS_GROUP, 'api_username', '')),
            'api_password' => self::readSecret('api_password_enc', 'api_password'),
            'sync_enabled' => (bool)Tools::settings(self::SETTINGS_GROUP, 'sync_enabled', true),
            'salon_default_service_id' => (int)Tools::settings(self::SETTINGS_GROUP, 'salon_default_service_id', 0),
        ];
    }

    public function syncCita(Cita $cita): array
    {
        try {
            $this->logInfo('Salon sync iniciada.', [
                'cita_id' => $cita->id,
                'idpaciente' => $cita->idpaciente,
                'idespecialista' => $cita->idespecialista,
                'fecha' => $cita->fecha,
                'hora_inicio' => $cita->hora_inicio,
                'wp_url' => $this->settings['wp_url'] ?? '',
                'sync_enabled' => empty($this->settings['sync_enabled']) ? '0' : '1',
            ]);

            if (empty($this->settings['sync_enabled'])) {
                return $this->storeResult($cita, false, 'skipped', 'Sincronizacion con Salon desactivada.');
            }

            $token = $this->login();
            $payload = $this->buildBookingPayload($cita, $token);

            $bookingId = (int)($cita->salon_booking_id ?? 0);
            $response = $bookingId > 0
                ? $this->putJson($this->apiUrl('/bookings/' . $bookingId), $payload, $token)
                : $this->withAccessToken(Http::postJson($this->apiUrl('/bookings'), $payload), $token)
                    ->setTimeout(30);

            $data = $response->json(true);
            if (!$response->ok() || !is_array($data) || empty($data['id'])) {
                $error = $this->responseError($response, $data);
                $this->logWarning('Salon booking fallo.', [
                    'cita_id' => $cita->id,
                    'booking_id' => $bookingId,
                    'http_status' => $response->status(),
                    'error' => $error,
                ]);
                return $this->storeResult($cita, false, 'failed', $error);
            }

            $cita->salon_booking_id = (int)$data['id'];
            $cita->salon_customer_id = !empty($data['customer_id']) ? (int)$data['customer_id'] : $cita->salon_customer_id;
            $cita->salon_service_id = (int)$payload['services'][0]['service_id'];

            $this->storePatientCustomerId($cita, (int)$cita->salon_customer_id);

            $this->logInfo('Salon booking creado/actualizado.', [
                'cita_id' => $cita->id,
                'salon_booking_id' => $cita->salon_booking_id,
                'salon_customer_id' => $cita->salon_customer_id,
                'salon_service_id' => $cita->salon_service_id,
                'salon_assistant_id' => $payload['services'][0]['assistant_id'] ?? 0,
            ]);

            return $this->storeResult($cita, true, 'synced', '');
        } catch (Throwable $exception) {
            $this->logWarning('Salon sync excepcion.', [
                'cita_id' => $cita->id,
                'error' => $exception->getMessage(),
            ]);
            return $this->storeResult($cita, false, 'failed', $exception->getMessage());
        }
    }

    public function testConnection(): array
    {
        try {
            $token = $this->login();
            return [
                'success' => $token !== '',
                'message' => 'Conexion API correcta.',
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function apiUrl(string $path): string
    {
        return rtrim((string)$this->settings['wp_url'], '/') . self::API_PATH . $path;
    }

    private function buildBookingPayload(Cita $cita, string $token): array
    {
        $paciente = $cita->getPaciente();
        if (!$paciente instanceof Paciente) {
            throw new \RuntimeException('Paciente no encontrado para la cita.');
        }

        $cliente = $paciente->getCliente();
        if (null === $cliente) {
            throw new \RuntimeException('Cliente FacturaScripts no encontrado para el paciente.');
        }

        $serviceId = $this->resolveServiceId($cita, $token);
        if ($serviceId < 1) {
            throw new \RuntimeException('No se pudo resolver ni crear un servicio de Salon para la cita.');
        }

        $assistantId = $this->resolveAssistantId($cita, $serviceId, $token);
        if ($assistantId < 1) {
            throw new \RuntimeException('No se pudo resolver ni crear un asistente de Salon para la cita.');
        }

        $customerId = (int)($cita->salon_customer_id ?: $paciente->salon_customer_id);
        $customer = $this->customerPayload($cliente);
        $payload = [
            'date' => $this->normalizeDate((string)$cita->fecha),
            'time' => substr((string)$cita->hora_inicio, 0, 5),
            'status' => $this->mapStatus((string)$cita->estado),
            'customer_first_name' => $customer['customer_first_name'],
            'customer_last_name' => $customer['customer_last_name'],
            'customer_email' => $customer['customer_email'] ?: $this->syntheticEmail('cliente', (string)$cliente->codcliente),
            'customer_phone' => $customer['customer_phone'],
            'customer_address' => $customer['customer_address'],
            'services' => [[
                'service_id' => $serviceId,
                'assistant_id' => $assistantId,
                'resource_id' => 0,
                'duration' => $this->durationToSalon($cita),
            ]],
            'discounts' => [],
            'note' => trim((string)$cita->motivo),
            'admin_note' => $this->adminNote($cita),
            'transaction_id' => '',
        ];

        if ($customerId > 0) {
            $payload['customer_id'] = $customerId;
        }

        return $payload;
    }

    private function customerPayload($cliente): array
    {
        $name = trim((string)($cliente->razonsocial ?: $cliente->nombre));
        if ($name === '') {
            $name = trim((string)$cliente->codcliente);
        }

        $parts = preg_split('/\s+/', $name, 2);

        return [
            'customer_first_name' => $parts[0] ?? $name,
            'customer_last_name' => $parts[1] ?? '',
            'customer_email' => filter_var((string)$cliente->email, FILTER_VALIDATE_EMAIL) ? (string)$cliente->email : '',
            'customer_phone' => trim((string)($cliente->telefono1 ?: $cliente->telefono2)),
            'customer_address' => '',
        ];
    }

    private function findAssistantByProfile(Especialista $especialista, string $token): array
    {
        $items = $this->listItems('/assistants', $token);
        $email = $this->validEmail((string)$especialista->email);
        $name = $this->normalizeText($especialista->getFullName());

        foreach ($items as $item) {
            if ($email !== '' && $this->normalizeText((string)($item['email'] ?? '')) === $this->normalizeText($email)) {
                return $item;
            }
        }

        foreach ($items as $item) {
            if ($name !== '' && $this->normalizeText((string)($item['name'] ?? '')) === $name) {
                return $item;
            }
        }

        return [];
    }

    private function findServiceByName(string $name, string $token): array
    {
        $items = $this->listItems('/services', $token, ['type' => 'all']);
        $target = $this->normalizeText($name);

        foreach ($items as $item) {
            if ($target !== '' && $this->normalizeText((string)($item['name'] ?? '')) === $target) {
                return $item;
            }
        }

        return [];
    }

    private function adminNote(Cita $cita): string
    {
        $notes = [];
        if (!empty($cita->observaciones)) {
            $notes[] = trim((string)$cita->observaciones);
        }
        $notes[] = 'ERP Dental cita #' . $cita->id;

        return implode("\n\n", $notes);
    }

    private function login(): string
    {
        if ($this->token !== '') {
            return $this->token;
        }

        if (empty($this->settings['wp_url']) || empty($this->settings['api_username']) || empty($this->settings['api_password'])) {
            throw new \RuntimeException('Faltan URL, usuario o password de API de Salon.');
        }

        $this->logInfo('Salon login API.', [
            'wp_url' => $this->settings['wp_url'],
            'api_username' => $this->settings['api_username'],
        ]);

        $response = Http::get($this->apiUrl('/login'), [
            'name' => $this->settings['api_username'],
            'password' => $this->settings['api_password'],
        ])->setTimeout(20);

        $data = $response->json(true);
        if (!$response->ok() || !is_array($data) || empty($data['access_token'])) {
            $error = $this->responseError($response, $data);
            $this->logWarning('Salon login API fallo.', [
                'http_status' => $response->status(),
                'error' => $error,
            ]);
            throw new \RuntimeException($error);
        }

        $this->logInfo('Salon login API correcto.', [
            'http_status' => $response->status(),
        ]);

        $this->token = (string)$data['access_token'];
        return $this->token;
    }

    private function mapStatus(string $status): string
    {
        switch ($status) {
            case 'confirmada':
            case 'en_sala':
            case 'en_curso':
            case 'finalizada':
                return 'sln-b-confirmed';

            case 'cancelada':
            case 'cancelada_paciente':
            case 'cancelada_clinica':
            case 'no_asistio':
                return 'sln-b-canceled';

            case 'pendiente':
            case 'reprogramada':
            default:
                return 'sln-b-pending';
        }
    }

    private function durationToSalon(Cita $cita): string
    {
        $minutes = (int)$cita->duracion;
        if ($minutes < 1 && !empty($cita->fecha) && !empty($cita->hora_inicio) && !empty($cita->hora_fin)) {
            $from = new DateTimeImmutable($this->normalizeDate((string)$cita->fecha) . ' ' . substr((string)$cita->hora_inicio, 0, 8));
            $to = new DateTimeImmutable($this->normalizeDate((string)$cita->fecha) . ' ' . substr((string)$cita->hora_fin, 0, 8));
            $minutes = max(0, (int)(($to->getTimestamp() - $from->getTimestamp()) / 60));
        }

        $minutes = max(1, $minutes);
        return sprintf('%02d:%02d', floor($minutes / 60), $minutes % 60);
    }

    private function normalizeDate(string $date): string
    {
        foreach (['Y-m-d', 'd-m-Y', 'Y-m-d H:i:s'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $date);
            if ($parsed instanceof DateTimeImmutable) {
                return $parsed->format('Y-m-d');
            }
        }

        $timestamp = strtotime($date);
        if (false === $timestamp) {
            throw new \RuntimeException('Fecha de cita invalida para Salon.');
        }

        return date('Y-m-d', $timestamp);
    }

    private function putJson(string $url, array $payload, string $token): Http
    {
        return $this->withAccessToken(
            Http::put($url, json_encode($payload))->setHeader('Content-Type', 'application/json'),
            $token
        )
            ->setTimeout(30);
    }

    private function postResource(string $path, array $payload, string $token): array
    {
        $this->logInfo('Salon POST recurso.', [
            'path' => $path,
            'name' => $payload['name'] ?? '',
            'email' => $payload['email'] ?? '',
        ]);

        $response = $this->withAccessToken(Http::postJson($this->apiUrl($path), $payload), $token)
            ->setTimeout(30);

        $data = $response->json(true);
        if (!$response->ok() || !is_array($data) || empty($data['id'])) {
            $error = $this->responseError($response, $data);
            $this->logWarning('Salon POST recurso fallo.', [
                'path' => $path,
                'http_status' => $response->status(),
                'error' => $error,
            ]);
            throw new \RuntimeException($error);
        }

        $this->logInfo('Salon POST recurso correcto.', [
            'path' => $path,
            'id' => (int)$data['id'],
        ]);

        return $data;
    }

    private static function readSecret(string $encryptedKey, string $legacyKey): string
    {
        $encrypted = (string)Tools::settings(self::SETTINGS_GROUP, $encryptedKey, '');
        if ($encrypted !== '') {
            try {
                return DentalCrypto::decrypt($encrypted);
            } catch (Throwable $exception) {
                Tools::log()->warning('No se pudo descifrar una credencial de Salon');
                return '';
            }
        }

        return (string)Tools::settings(self::SETTINGS_GROUP, $legacyKey, '');
    }

    private function resolveAssistantId(Cita $cita, int $serviceId, string $token): int
    {
        $especialista = $cita->getEspecialista();
        if (!$especialista instanceof Especialista) {
            throw new \RuntimeException('Especialista no encontrado para la cita.');
        }

        $assistant = [];
        if ((int)$especialista->salon_assistant_id > 0) {
            $assistant = $this->getResource('/assistants/' . (int)$especialista->salon_assistant_id, $token);
            if (!empty($assistant)) {
                $this->logInfo('Salon asistente resuelto por ID local.', [
                    'especialista_id' => $especialista->id,
                    'salon_assistant_id' => (int)$especialista->salon_assistant_id,
                ]);
            }
        }

        if (empty($assistant)) {
            $assistant = $this->findAssistantByProfile($especialista, $token);
            if (!empty($assistant)) {
                $this->logInfo('Salon asistente resuelto por perfil.', [
                    'especialista_id' => $especialista->id,
                    'salon_assistant_id' => (int)($assistant['id'] ?? 0),
                ]);
            }
        }

        if (empty($assistant)) {
            $this->logInfo('Salon asistente no existe, creando.', [
                'especialista_id' => $especialista->id,
                'nombre' => $especialista->getFullName(),
                'service_id' => $serviceId,
            ]);
            $data = $this->postResource('/assistants', [
                'name' => $especialista->getFullName(),
                'services' => [$serviceId],
                'email' => $this->validEmail((string)$especialista->email) ?: $this->syntheticEmail('asistente', (string)$especialista->id),
                'phone' => trim((string)$especialista->telefono),
                'description' => trim((string)$especialista->observaciones),
                'availabilities' => [],
                'holidays' => [],
                'image_url' => '',
            ], $token);
            $assistant = ['id' => (int)$data['id'], 'services' => [$serviceId]];
        }

        $assistantId = (int)($assistant['id'] ?? 0);
        if ($assistantId < 1) {
            return 0;
        }

        $services = array_map('intval', (array)($assistant['services'] ?? []));
        if (!in_array($serviceId, $services, true)) {
            $services[] = $serviceId;
            $payload = $assistant;
            $payload['services'] = array_values(array_unique($services));
            $this->putResource('/assistants/' . $assistantId, $payload, $token);
            $this->logInfo('Salon asistente actualizado con servicio.', [
                'salon_assistant_id' => $assistantId,
                'salon_service_id' => $serviceId,
            ]);
        }

        if ((int)$especialista->salon_assistant_id !== $assistantId) {
            $especialista->salon_assistant_id = $assistantId;
            $especialista->save();
        }

        return $assistantId;
    }

    private function resolveServiceId(Cita $cita, string $token): int
    {
        if ((int)$cita->salon_service_id > 0 && $this->resourceExists('/services/' . (int)$cita->salon_service_id, $token)) {
            $this->logInfo('Salon servicio resuelto por cita.', [
                'cita_id' => $cita->id,
                'salon_service_id' => (int)$cita->salon_service_id,
            ]);
            return (int)$cita->salon_service_id;
        }

        $tratamiento = null;
        if ((int)$cita->idtratamiento > 0) {
            $tratamiento = new TratamientoPaciente();
            if (
                $tratamiento->loadFromCode($cita->idtratamiento)
                && (int)$tratamiento->salon_service_id > 0
                && $this->resourceExists('/services/' . (int)$tratamiento->salon_service_id, $token)
            ) {
                $this->logInfo('Salon servicio resuelto por tratamiento.', [
                    'cita_id' => $cita->id,
                    'tratamiento_id' => $tratamiento->id,
                    'salon_service_id' => (int)$tratamiento->salon_service_id,
                ]);
                return (int)$tratamiento->salon_service_id;
            }
        }

        $defaultServiceId = (int)($this->settings['salon_default_service_id'] ?? 0);
        if ($defaultServiceId > 0 && $this->resourceExists('/services/' . $defaultServiceId, $token)) {
            $this->logInfo('Salon servicio resuelto por defecto.', [
                'cita_id' => $cita->id,
                'salon_service_id' => $defaultServiceId,
            ]);
            return $defaultServiceId;
        }

        $name = $this->serviceName($cita, $tratamiento);
        $service = $this->findServiceByName($name, $token);
        if (empty($service)) {
            $this->logInfo('Salon servicio no existe, creando.', [
                'cita_id' => $cita->id,
                'name' => $name,
                'duration' => $this->durationToSalon($cita),
            ]);
            $data = $this->postResource('/services', [
                'name' => $name,
                'price' => $tratamiento instanceof TratamientoPaciente ? (float)$tratamiento->precio : 0,
                'unit' => 1,
                'duration' => $this->durationToSalon($cita),
                'exclusive' => 0,
                'secondary' => 0,
                'secondary_display_mode' => 'always',
                'secondary_parent_services' => [],
                'execution_order' => 1,
                'break' => '00:00',
                'empty_assistants' => 0,
                'description' => 'Creado automaticamente desde ERP Dental.',
                'categories' => [],
                'availabilities' => [],
                'image_url' => '',
            ], $token);
            $service = ['id' => (int)$data['id']];
        }

        $serviceId = (int)($service['id'] ?? 0);
        if ($serviceId > 0) {
            $cita->salon_service_id = $serviceId;
            if ($tratamiento instanceof TratamientoPaciente && (int)$tratamiento->salon_service_id !== $serviceId) {
                $tratamiento->salon_service_id = $serviceId;
                $tratamiento->save();
            }
        }

        return $serviceId;
    }

    private function getResource(string $path, string $token): array
    {
        $response = $this->withAccessToken(Http::get($this->apiUrl($path)), $token)
            ->setTimeout(20);

        $data = $response->json(true);
        if (!$response->ok() || !is_array($data)) {
            if ($response->status() !== 404) {
                $this->logWarning('Salon GET recurso fallo.', [
                    'path' => $path,
                    'http_status' => $response->status(),
                    'error' => $this->responseError($response, $data),
                ]);
            }
            return [];
        }

        if (!empty($data['items'][0]) && is_array($data['items'][0])) {
            return $data['items'][0];
        }

        return [];
    }

    private function listItems(string $path, string $token, array $params = []): array
    {
        $params += [
            'per_page' => 100,
            'page' => 1,
            'orderby' => 'name',
            'order' => 'asc',
        ];

        $response = $this->withAccessToken(Http::get($this->apiUrl($path), $params), $token)
            ->setTimeout(30);

        $data = $response->json(true);
        if (!$response->ok() || !is_array($data) || !is_array($data['items'] ?? null)) {
            $this->logWarning('Salon listado recurso fallo.', [
                'path' => $path,
                'http_status' => $response->status(),
                'error' => $this->responseError($response, $data),
            ]);
            return [];
        }

        $this->logInfo('Salon listado recurso correcto.', [
            'path' => $path,
            'count' => count($data['items']),
        ]);

        return $data['items'];
    }

    private function normalizeText(string $text): string
    {
        return mb_strtolower(trim($text), 'UTF-8');
    }

    private function putResource(string $path, array $payload, string $token): void
    {
        $response = $this->putJson($this->apiUrl($path), $payload, $token);
        $data = $response->json(true);
        if (!$response->ok()) {
            $error = $this->responseError($response, $data);
            $this->logWarning('Salon PUT recurso fallo.', [
                'path' => $path,
                'http_status' => $response->status(),
                'error' => $error,
            ]);
            throw new \RuntimeException($error);
        }
    }

    private function resourceExists(string $path, string $token): bool
    {
        return !empty($this->getResource($path, $token));
    }

    private function serviceName(Cita $cita, ?TratamientoPaciente $tratamiento): string
    {
        if ($tratamiento instanceof TratamientoPaciente && trim((string)$tratamiento->referencia_servicio) !== '') {
            return trim((string)$tratamiento->referencia_servicio);
        }

        return 'Consulta dental';
    }

    private function syntheticEmail(string $prefix, string $id): string
    {
        $id = preg_replace('/[^a-z0-9]+/i', '-', $id) ?: 'sin-id';
        return strtolower($prefix . '-' . trim($id, '-') . '@erp-dental.invalid');
    }

    private function validEmail(string $email): string
    {
        $email = trim($email);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    private function responseError(Http $response, $data): string
    {
        if (is_array($data)) {
            if (!empty($data['message'])) {
                return 'Salon API HTTP ' . $response->status() . ': ' . $data['message'];
            }
            if (!empty($data['code'])) {
                return 'Salon API HTTP ' . $response->status() . ': ' . $data['code'];
            }
        }

        $body = trim((string)$response->body());
        if (strlen($body) > 500) {
            $body = substr($body, 0, 500) . '...';
        }

        return 'Salon API HTTP ' . $response->status() . ' ' . $response->errorMessage()
            . ($body === '' ? '' : ' Body: ' . $body);
    }

    private function logInfo(string $message, array $context = []): void
    {
        Tools::log(self::LOG_CHANNEL)->info($message, $context);
    }

    private function logWarning(string $message, array $context = []): void
    {
        Tools::log(self::LOG_CHANNEL)->warning($message, $context);
    }

    private function storePatientCustomerId(Cita $cita, int $customerId): void
    {
        if ($customerId < 1) {
            return;
        }

        $paciente = $cita->getPaciente();
        if ($paciente instanceof Paciente && (int)$paciente->salon_customer_id !== $customerId) {
            $paciente->salon_customer_id = $customerId;
            $paciente->save();
        }
    }

    private function storeResult(Cita $cita, bool $success, string $status, string $error): array
    {
        $cita->salon_sync_status = $status;
        $cita->salon_sync_error = $success ? '' : $error;
        $cita->salon_synced_at = date('Y-m-d H:i:s');

        if (!$cita->save()) {
            Tools::log()->warning('No se pudo guardar el estado de sincronizacion Salon en la cita #' . $cita->id);
        }

        if ($success) {
            Tools::log()->notice('Cita sincronizada con Salon Booking');
        } elseif ($status !== 'skipped') {
            Tools::log()->warning('Error sincronizando cita dental #' . $cita->id . ': ' . $error);
        }

        return [
            'success' => $success,
            'status' => $status,
            'message' => $error,
        ];
    }

    private function withAccessToken(Http $request, string $token): Http
    {
        return $request
            ->setBearerToken($token)
            ->setHeader('Access-Token', $token);
    }
}
