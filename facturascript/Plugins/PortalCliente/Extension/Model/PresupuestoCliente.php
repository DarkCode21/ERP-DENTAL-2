<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Extension\Model;

use Closure;
use FacturaScripts\Plugins\PortalCliente\Lib\PortalDocShare;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PresupuestoCliente
{
    public function test(): Closure
    {
        return function (){
            if (empty($this->pc_uuid)) {
                $this->pc_uuid = uniqid();
            }
        };
    }

    public function url(): Closure
    {
        return function ($type, $list) {
            switch ($type) {
                case 'public':
                    return empty($this->pc_uuid)
                        ? 'PortalPresupuesto?code=' . $this->primaryColumnValue()
                        : 'PortalPresupuesto/' . $this->pc_uuid;

                case 'public-share':
                    $url = $this->url('public', $list);
                    $url .= str_contains($url, '?') ? '&' : '?';
                    $url .= 'share=' . PortalDocShare::getCode($this);
                    return $url;

                case 'public-print':
                    return empty($this->pc_uuid)
                        ? 'PortalPresupuesto?code=' . $this->primaryColumnValue() . '&action=print'
                        : 'PortalPresupuesto/' . $this->pc_uuid . '?action=print';

                case 'public-print-share':
                    $url = $this->url('public-print', $list);
                    $url .= str_contains($url, '?') ? '&' : '?';
                    $url .= 'share=' . PortalDocShare::getCode($this);
                    return $url;
            }
        };
    }
}