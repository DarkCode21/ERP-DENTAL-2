<?php
/**
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TPVneo\Extension\Model;

use Closure;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PresupuestoCliente
{
    public function clear(): Closure
    {
        return function () {
            $this->aparcado = false;
        };
    }
}
