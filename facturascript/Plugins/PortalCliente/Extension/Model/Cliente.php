<?php
/**
 * Copyright (C) 2025 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Extension\Model;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\PortalNote;

class Cliente
{
    public function delete(): Closure
    {
        return function () {
            $where = [new DataBaseWhere('codcliente', $this->primaryColumnValue())];
            foreach (PortalNote::all($where, [], 0, 0) as $ticket) {
                $ticket->delete();
            }
        };
    }
}