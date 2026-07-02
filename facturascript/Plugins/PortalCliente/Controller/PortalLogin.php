<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Cache;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\PortalPanelController;
use FacturaScripts\Dinamic\Model\Contacto;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalLogin extends PortalPanelController
{
    const LOGIN_TEMPLATE = 'PortalLogin';
    const MAX_ATTEMPTS = 6;
    const MAX_TIME = 600;

    /** @var string */
    public $pc_nick;

    /** @var string */
    public $return;

    /** @var array */
    private static $blocked_nicks;

    /** @var array */
    private static $blocked_ips;

    public static function blockNick(string $nick, ?int $time = null): void
    {
        self::loadBlockedNickList();

        if (isset(self::$blocked_nicks[$nick])) {
            self::$blocked_nicks[$nick]['attempts']++;
            self::$blocked_nicks[$nick]['last'] = ($time ?? time());
        } else {
            self::$blocked_nicks[$nick] = [
                'attempts' => 1,
                'last' => ($time ?? time()),
            ];
        }

        Cache::set('portal-cliente-blocked-email-list', self::$blocked_nicks);
    }

    public static function blockIp(string $ip, ?int $time = null): void
    {
        self::loadBlockedIpList();

        if (isset(self::$blocked_ips[$ip])) {
            self::$blocked_ips[$ip]['attempts']++;
            self::$blocked_ips[$ip]['last'] = ($time ?? time());
        } else {
            self::$blocked_ips[$ip] = [
                'attempts' => 1,
                'last' => ($time ?? time()),
            ];
        }

        Cache::set('portal-cliente-blocked-ip-list', self::$blocked_ips);
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'PortalCliente';
        $data['title'] = 'login';
        $data['icon'] = 'fas fa-key';
        return $data;
    }

    public static function isIpBlocked(string $ip): bool
    {
        self::loadBlockedIpList();

        foreach (self::$blocked_ips as $key => $value) {
            if ($key === $ip && $value['attempts'] >= self::MAX_ATTEMPTS) {
                return true;
            }
        }

        return false;
    }

    public static function isNickBlocked(?string $nick): bool
    {
        if (empty($nick)) {
            return true;
        }

        self::loadBlockedNickList();

        foreach (self::$blocked_nicks as $key => $value) {
            if ($key === $nick && $value['attempts'] >= self::MAX_ATTEMPTS) {
                return true;
            }
        }

        return false;
    }

    protected function createViews()
    {
        if (false === $this->contact->exists()) {
            $this->return = $this->request->get('return', 'PortalCliente');
            $this->pc_nick = $this->request->get('pc_nick', '');
            $this->setTemplate(self::LOGIN_TEMPLATE);
            $this->title = Tools::lang()->trans('login');
            return;
        }

        $this->redirect('PortalCliente');
    }

    /**
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        if ($action === 'login') {
            return $this->loginAction();
        }

        return parent::execPreviousAction($action);
    }

    protected static function loadBlockedIpList(): void
    {
        if (is_null(self::$blocked_ips)) {
            self::$blocked_ips = Cache::remember('portal-cliente-blocked-ip-list', function () {
                return [];
            });
        }

        // quitamos los registros antiguos
        $time = time();
        foreach (self::$blocked_ips as $key => $value) {
            if ($value['last'] < ($time - self::MAX_TIME)) {
                unset(self::$blocked_ips[$key]);
            }
        }
    }

    protected static function loadBlockedNickList(): void
    {
        if (is_null(self::$blocked_nicks)) {
            self::$blocked_nicks = Cache::remember('portal-cliente-blocked-nick-list', function () {
                return [];
            });
        }

        // quitamos los registros antiguos
        $time = time();
        foreach (self::$blocked_nicks as $key => $value) {
            if ($value['last'] < ($time - self::MAX_TIME)) {
                unset(self::$blocked_nicks[$key]);
            }
        }
    }

    protected function loadData($viewName, $view)
    {
        $this->hasData = true;
    }

    protected function loginAction(): bool
    {
        // obtenemos la contraseña
        $password = $this->request->request->get('pc_password', '');

        if (false === $this->validateFormToken()) {
            return false;
        } elseif ($this->isIpBlocked(Session::getClientIp())) {
            // evitamos ataques de fuerza bruta
            Tools::log()->warning('ip-banned');
            return false;
        } elseif (self::isNickBlocked($this->pc_nick)) {
            Tools::log()->warning('nick-blocked', ['%nick%' => $this->pc_nick]);
            return false;
        }

        // evitamos ataques con contraseñas muy largas
        if (strlen($password) > 100) {
            Tools::log()->warning('password-too-long');
            $this->blockNick($this->pc_nick);
            $this->blockIp(Session::getClientIp());
            return false;
        }

        // buscamos el contacto
        $contact = new Contacto();
        $where = [new DataBaseWhere('pc_nick', $this->pc_nick)];

        if (false === $contact->loadFromCode('', $where)) {
            Tools::log()->warning('nick-not-found', ['%nick%' => $this->pc_nick]);
            $this->blockNick($this->pc_nick);
            $this->blockIp(Session::getClientIp());
            return false;
        }

        // comprobamos si está activo
        if (false === $contact->pc_active) {
            Tools::log()->warning('inactive-contact', ['%nick%' => $this->pc_nick]);
            self::blockNick($contact->pc_nick);
            self::blockIp(Session::getClientIp());
            return false;
        }

        // comprobamos la contraseña
        if (false === $contact->verifyPCPassword($password)) {
            Tools::log()->warning('wrong-password');
            self::blockNick($contact->pc_nick);
            self::blockIp(Session::getClientIp());
            return false;
        }

        // actualizamos la semilla de tokens
        $this->multiRequestProtection->addSeed($contact->idcontacto);

        // actualizamos la actividad
        $contact->newPCLogkey();
        $contact->updatePCActivity($this->request->headers->get('User-Agent'));
        $contact->save();

        // guardamos las cookies
        $expire = time() + FS_COOKIES_EXPIRE;
        setcookie('pc_idcontacto', $contact->idcontacto, $expire, Tools::config('route', '/'));
        setcookie('pc_log_key', $contact->pc_log_key, $expire, Tools::config('route', '/'));

        $this->contact = $contact;

        // redireccionamos
        if (empty($this->return)) {
            $this->redirect('PortalCliente');
            return true;
        }

        $this->redirect($this->return);
        return true;
    }
}
