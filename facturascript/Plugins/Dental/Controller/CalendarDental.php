<?php

namespace FacturaScripts\Plugins\Dental\Controller;

use DateTimeImmutable;
use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Http;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Dental\Lib\DentalCalendar;
use FacturaScripts\Plugins\Dental\Lib\DentalCrypto;
use FacturaScripts\Plugins\Dental\Model\Cita;
use Throwable;

class CalendarDental extends Controller
{
    private const SETTINGS_GROUP = 'dental_salon';
    private const DEFAULT_EMBED_TTL = 7200;

    public $apiStatus = '';
    public $apiStatusMessage = '';
    public $embedUrl = '';
    public $settings = [];
    public $showSettings = false;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'dental';
        $data['title'] = 'calendar';
        $data['icon'] = 'fas fa-calendar-alt';
        $data['showonmenu'] = true;

        return $data;
    }

    public function hasApiPassword(): bool
    {
        return !empty($this->settings['api_password']);
    }

    public function hasEmbedSecret(): bool
    {
        return !empty($this->settings['embed_secret']);
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $this->loadSettings();
        $action = $this->request->request->get('action', $this->request->query->get('action', ''));

        switch ($action) {
            case 'events':
                $this->eventsAction();
                return;

            case 'move':
                $this->moveAction();
                return;

            case 'save-settings':
                $this->saveSettingsAction();
                break;

            case 'test-api':
                $this->testApiAction();
                break;
        }

        $this->embedUrl = $this->buildEmbedUrl($this->settings);
        $this->showSettings = $this->request->query->get('settings', '') === '1'
            || $action === 'save-settings'
            || $action === 'test-api'
            || empty($this->settings['wp_url']);

        $this->setTemplate('Calendar/CalendarDental');
    }

    public function useLocalCalendar(): bool
    {
        return empty($this->embedUrl);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function buildEmbedToken(array $settings): string
    {
        $secret = trim((string)($settings['embed_secret'] ?? ''));
        $user = trim((string)($settings['embed_user'] ?? '')) ?: trim((string)($settings['api_username'] ?? ''));

        if ($secret === '' || $user === '') {
            return '';
        }

        $now = time();
        $payload = [
            'iss' => 'facturascripts-dental',
            'aud' => 'salon-erp-calendar',
            'iat' => $now,
            'exp' => $now + self::DEFAULT_EMBED_TTL,
            'user' => $user,
            'nonce' => bin2hex(random_bytes(8)),
        ];

        $payload64 = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', $payload64, $secret);

        return $payload64 . '.' . $signature;
    }

    private function buildEmbedUrl(array $settings): string
    {
        if (empty($settings['embed_enabled']) || empty($settings['wp_url'])) {
            return '';
        }

        $token = $this->buildEmbedToken($settings);
        if ($token === '') {
            return '';
        }

        return $this->appendQuery($settings['wp_url'], [
            'sln_erp_calendar' => '1',
            'sln_erp_token' => $token,
        ]);
    }

    private function appendQuery(string $url, array $params): string
    {
        $separator = strpos($url, '?') === false ? '?' : '&';

        return $url . $separator . http_build_query($params);
    }

    private function eventsAction(): void
    {
        $start = substr((string)$this->request->query->get('start', ''), 0, 10);
        $end = substr((string)$this->request->query->get('end', ''), 0, 10);

        $where = [];
        if ($start !== '') {
            $where[] = new DataBaseWhere('fecha', $start, '>=');
        }
        if ($end !== '') {
            $where[] = new DataBaseWhere('fecha', $end, '<=');
        }

        $citas = Cita::all($where, ['fecha' => 'ASC', 'hora_inicio' => 'ASC'], 0, 1000);
        $this->jsonResponse(DentalCalendar::citasToFullCalendarEvents($citas));
    }

    private function loadSettings(): void
    {
        $this->settings = [
            'wp_url' => trim((string)Tools::settings(self::SETTINGS_GROUP, 'wp_url', '')),
            'api_username' => trim((string)Tools::settings(self::SETTINGS_GROUP, 'api_username', '')),
            'api_password' => $this->readSecret('api_password_enc', 'api_password'),
            'embed_user' => trim((string)Tools::settings(self::SETTINGS_GROUP, 'embed_user', '')),
            'embed_secret' => $this->readSecret('embed_secret_enc', 'embed_secret'),
            'embed_enabled' => (bool)Tools::settings(self::SETTINGS_GROUP, 'embed_enabled', true),
        ];
    }

    private function jsonResponse(array $data): void
    {
        $this->setTemplate(false);
        $this->response->headers->set('Content-Type', 'application/json');
        $this->response->setContent(json_encode($data));
    }

    private function moveAction(): void
    {
        if (!$this->permissions->allowUpdate) {
            $this->jsonResponse(['success' => false, 'message' => 'not-allowed-modify']);
            return;
        }

        $id = (int)$this->request->query->get('id', 0);
        $cita = new Cita();
        if ($id < 1 || false === $cita->loadFromCode($id)) {
            $this->jsonResponse(['success' => false, 'message' => 'record-not-found']);
            return;
        }

        $fecha = substr((string)$this->request->query->get('fecha', ''), 0, 10);
        $horaInicio = substr((string)$this->request->query->get('hora_inicio', ''), 0, 8);
        $horaFin = substr((string)$this->request->query->get('hora_fin', ''), 0, 8);

        if ($fecha === '' || $horaInicio === '' || $horaFin === '') {
            $this->jsonResponse(['success' => false, 'message' => 'invalid-request']);
            return;
        }

        $cita->fecha = $fecha;
        $cita->hora_inicio = $horaInicio;
        $cita->hora_fin = $horaFin;
        $cita->duracion = $this->calculateMinutes($fecha, $horaInicio, $horaFin);

        $this->jsonResponse(['success' => $cita->save()]);
    }

    private function calculateMinutes(string $fecha, string $start, string $end): int
    {
        $from = new DateTimeImmutable($fecha . ' ' . $start);
        $to = new DateTimeImmutable($fecha . ' ' . $end);

        return max(0, (int)(($to->getTimestamp() - $from->getTimestamp()) / 60));
    }

    private function saveSettings(array $settings): bool
    {
        Tools::settingsSet(self::SETTINGS_GROUP, 'wp_url', $settings['wp_url']);
        Tools::settingsSet(self::SETTINGS_GROUP, 'api_username', $settings['api_username']);
        Tools::settingsSet(self::SETTINGS_GROUP, 'embed_user', $settings['embed_user']);
        Tools::settingsSet(self::SETTINGS_GROUP, 'embed_enabled', $settings['embed_enabled']);
        Tools::settingsSet(self::SETTINGS_GROUP, 'api_password', '');
        Tools::settingsSet(self::SETTINGS_GROUP, 'embed_secret', '');
        Tools::settingsSet(self::SETTINGS_GROUP, 'api_password_enc', $this->writeSecret($settings['api_password']));
        Tools::settingsSet(self::SETTINGS_GROUP, 'embed_secret_enc', $this->writeSecret($settings['embed_secret']));

        if (Tools::settingsSave()) {
            $this->settings = $settings;
            Tools::log()->info('Configuracion del calendario Salon guardada');
            return true;
        }

        Tools::log()->warning('No se pudo guardar la configuracion del calendario Salon');
        return false;
    }

    private function saveSettingsAction(): void
    {
        if (!$this->permissions->allowUpdate || false === $this->validateFormToken()) {
            return;
        }

        $this->saveSettings($this->settingsFromRequest());
    }

    private function settingsFromRequest(): array
    {
        $settings = $this->settings;
        $settings['wp_url'] = rtrim(trim((string)$this->request->request->get('wp_url', '')), '/');
        $settings['api_username'] = trim((string)$this->request->request->get('api_username', ''));
        $settings['embed_user'] = trim((string)$this->request->request->get('embed_user', ''));
        $settings['embed_enabled'] = $this->request->request->get('embed_enabled', '0') === '1';

        $apiPassword = (string)$this->request->request->get('api_password', '');
        if ($apiPassword !== '') {
            $settings['api_password'] = $apiPassword;
        }

        $embedSecret = (string)$this->request->request->get('embed_secret', '');
        if ($embedSecret !== '') {
            $settings['embed_secret'] = $embedSecret;
        }

        return $settings;
    }

    private function readSecret(string $encryptedKey, string $legacyKey): string
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

    private function testApiAction(): void
    {
        if (!$this->permissions->allowUpdate || false === $this->validateFormToken()) {
            return;
        }

        $this->settings = $this->settingsFromRequest();
        $this->testSalonApi($this->settings);
    }

    private function testSalonApi(array $settings): void
    {
        if (empty($settings['wp_url']) || empty($settings['api_username']) || empty($settings['api_password'])) {
            $this->apiStatus = 'warning';
            $this->apiStatusMessage = 'Faltan URL, usuario o password de API.';
            return;
        }

        $loginUrl = rtrim($settings['wp_url'], '/') . '/wp-json/salon/api/v1/login';
        $response = Http::get($loginUrl, [
            'name' => $settings['api_username'],
            'password' => $settings['api_password'],
        ])->setTimeout(20);

        $data = $response->json(true);
        if ($response->ok() && is_array($data) && !empty($data['access_token'])) {
            $this->apiStatus = 'success';
            $this->apiStatusMessage = 'Conexion API correcta.';
            return;
        }

        $this->apiStatus = 'danger';
        $this->apiStatusMessage = 'No se pudo conectar con la API de Salon. HTTP '
            . $response->status() . ' ' . $response->errorMessage();
    }

    private function writeSecret(string $value): string
    {
        return $value === '' ? '' : DentalCrypto::encrypt($value);
    }
}
