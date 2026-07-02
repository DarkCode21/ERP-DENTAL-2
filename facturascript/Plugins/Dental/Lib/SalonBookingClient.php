<?php
/**
 * Salon Booking API client for Dental appointments.
 */

namespace FacturaScripts\Plugins\Dental\Lib;

use DateTimeImmutable;
use FacturaScripts\Core\Http;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Dental\Model\Cita;
use FacturaScripts\Plugins\Dental\Model\Paciente;
use FacturaScripts\Plugins\Dental\Model\TratamientoPaciente;
use Throwable;

class SalonBookingClient
{
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
            if (empty($this->settings['sync_enabled'])) {
                return $this->storeResult($cita, false, 'skipped', 'Sincronizacion con Salon desactivada.');
            }

            $payload = $this->buildBookingPayload($cita);
            $token = $this->login();

            $bookingId = (int)($cita->salon_booking_id ?? 0);
            $response = $bookingId > 0
                ? $this->putJson($this->apiUrl('/bookings/' . $bookingId), $payload, $token)
                : Http::postJson($this->apiUrl('/bookings'), $payload)
                    ->setBearerToken($token)
                    ->setTimeout(30);

            $data = $response->json(true);
            if (!$response->ok() || !is_array($data) || empty($data['id'])) {
                return $this->storeResult($cita, false, 'failed', $this->responseError($response, $data));
            }

            $cita->salon_booking_id = (int)$data['id'];
            $cita->salon_customer_id = !empty($data['customer_id']) ? (int)$data['customer_id'] : $cita->salon_customer_id;
            $cita->salon_service_id = (int)$payload['services'][0]['service_id'];

            $this->storePatientCustomerId($cita, (int)$cita->salon_customer_id);

            return $this->storeResult($cita, true, 'synced', '');
        } catch (Throwable $exception) {
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

    private function buildBookingPayload(Cita $cita): array
    {
        $paciente = $cita->getPaciente();
        if (!$paciente instanceof Paciente) {
            throw new \RuntimeException('Paciente no encontrado para la cita.');
        }

        $cliente = $paciente->getCliente();
        if (null === $cliente) {
            throw new \RuntimeException('Cliente FacturaScripts no encontrado para el paciente.');
        }

        $especialista = $cita->getEspecialista();
        $assistantId = $especialista ? (int)$especialista->salon_assistant_id : 0;
        if ($assistantId < 1) {
            throw new \RuntimeException('Falta Salon assistant ID en el especialista de la cita.');
        }

        $serviceId = $this->resolveServiceId($cita);
        if ($serviceId < 1) {
            throw new \RuntimeException('Falta Salon service ID en la cita, tratamiento o configuracion del calendario.');
        }

        $customerId = (int)($cita->salon_customer_id ?: $paciente->salon_customer_id);
        $customer = $this->customerPayload($cliente);
        if ($customerId < 1 && empty($customer['customer_email'])) {
            throw new \RuntimeException('El paciente necesita email o un Salon customer ID para crear la reserva.');
        }

        $payload = [
            'date' => $this->normalizeDate((string)$cita->fecha),
            'time' => substr((string)$cita->hora_inicio, 0, 5),
            'status' => $this->mapStatus((string)$cita->estado),
            'customer_first_name' => $customer['customer_first_name'],
            'customer_last_name' => $customer['customer_last_name'],
            'customer_email' => $customer['customer_email'],
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

        $response = Http::get($this->apiUrl('/login'), [
            'name' => $this->settings['api_username'],
            'password' => $this->settings['api_password'],
        ])->setTimeout(20);

        $data = $response->json(true);
        if (!$response->ok() || !is_array($data) || empty($data['access_token'])) {
            throw new \RuntimeException($this->responseError($response, $data));
        }

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
        return Http::put($url, json_encode($payload))
            ->setHeader('Content-Type', 'application/json')
            ->setBearerToken($token)
            ->setTimeout(30);
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

    private function resolveServiceId(Cita $cita): int
    {
        if ((int)$cita->salon_service_id > 0) {
            return (int)$cita->salon_service_id;
        }

        if ((int)$cita->idtratamiento > 0) {
            $tratamiento = new TratamientoPaciente();
            if ($tratamiento->loadFromCode($cita->idtratamiento) && (int)$tratamiento->salon_service_id > 0) {
                return (int)$tratamiento->salon_service_id;
            }
        }

        return (int)($this->settings['salon_default_service_id'] ?? 0);
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

        return 'Salon API HTTP ' . $response->status() . ' ' . $response->errorMessage();
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
            Tools::log()->notice('Cita dental #' . $cita->id . ' sincronizada con Salon Booking');
        } elseif ($status !== 'skipped') {
            Tools::log()->warning('Error sincronizando cita dental #' . $cita->id . ': ' . $error);
        }

        return [
            'success' => $success,
            'status' => $status,
            'message' => $error,
        ];
    }
}
