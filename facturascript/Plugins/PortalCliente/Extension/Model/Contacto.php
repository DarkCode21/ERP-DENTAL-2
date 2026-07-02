<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Extension\Model;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\PortalCart;
use FacturaScripts\Dinamic\Model\PortalFavorite;
use FacturaScripts\Dinamic\Model\PortalNote;
use FacturaScripts\Dinamic\Model\PortalTicket;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Contacto
{
    public function delete(): Closure
    {
        return function () {
            $where = [new DataBaseWhere('idcontacto', $this->primaryColumnValue())];
            foreach (PortalNote::all($where, [], 0, 0) as $note) {
                $note->delete();
            }

            foreach (PortalCart::all($where, [], 0, 0) as $cart) {
                $cart->delete();
            }

            foreach (PortalTicket::all($where, [], 0, 0) as $ticket) {
                $ticket->delete();
            }

            foreach (PortalFavorite::all($where, [], 0, 0) as $favorite) {
                $favorite->delete();
            }
        };
    }

    public function getPCCart(): Closure
    {
        return function (): array {
            $model = new PortalCart();
            $where = [new DataBaseWhere('idcontacto', $this->idcontacto)];
            return $model->all($where, ['creation_date' => 'ASC'], 0, 0);
        };
    }

    public function getPCNick(): Closure
    {
        return function (): string {
            // si hay email, lo usamos para generar el nick
            if (false === empty($this->email)) {
                $emailArray = explode('@', $this->email);
                return $emailArray[0] . '_' . Tools::randomString(4);
            }

            // si hay nombre, lo usamos para generar el nick
            // limpiamos el nombre de caracteres especiales y espacios
            if (false === empty($this->nombre)) {
                return str_replace(' ', '_', trim(Tools::ascii($this->nombre))) . '_' . Tools::randomString(4);
            }

            // generamos un nick aleatorio
            return Tools::randomString(8);
        };
    }

    public function newPCLogkey(): Closure
    {
        return function () {
            $this->pc_log_key = Tools::randomString(99);
        };
    }

    public function setPCPassword(): Closure
    {
        return function (string $password) {
            $this->pc_password = password_hash($password, PASSWORD_DEFAULT);
            $this->new_password = '';
            $this->repeat_password = '';
        };
    }

    public function test(): Closure
    {
        return function () {
            if (false === $this->testPCPassword()) {
                return;
            }

            if (empty($this->pc_nick)) {
                $this->pc_nick = $this->getPCNick();
            }

            if ($this->pc_allow_buy) {
                $this->pc_allow_show_catalogue = true;
            }

            if ($this->pc_allow_buy && empty($this->codcliente)) {
                Tools::log()->warning('contact-is-not-a-customer');
                return false;
            }

            $this->pc_nick = Tools::noHtml(str_replace(' ', '', $this->pc_nick));
        };
    }

    public function testPCPassword(): Closure
    {
        return function (): bool {
            if (isset($this->new_password, $this->repeat_password) && $this->new_password !== '' && $this->repeat_password !== '') {
                if ($this->new_password !== $this->repeat_password) {
                    Tools::log()->warning('passwords-not-match');
                    return false;
                }

                $this->setPCPassword($this->new_password);
            }

            return true;
        };
    }

    protected function updatePCActivity(): Closure
    {
        return function (?string $userAgent = null): bool {
            if (time() - strtotime($this->pc_last_login) > 3600) {
                $this->pc_last_login = Tools::dateTime();
                $this->pc_last_ip = Session::getClientIp();
                $this->pc_last_browser = $userAgent;
                return true;
            }

            return false;
        };
    }

    public function verifyPCLogkey(): Closure
    {
        return function (string $value): bool {
            return $this->pc_log_key === $value;
        };
    }

    public function verifyPCPassword(): Closure
    {
        return function (string $password): bool {
            if (password_verify($password, $this->pc_password)) {
                if (password_needs_rehash($this->pc_password, PASSWORD_DEFAULT)) {
                    $this->setPCPassword($password);
                }

                return true;
            }

            return false;
        };
    }
}
