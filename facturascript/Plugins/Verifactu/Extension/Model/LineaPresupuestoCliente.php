<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Extension\Model;

use Closure;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class LineaPresupuestoCliente
{
    public function clear(): Closure
    {
        return function () {
            $this->vf_send = true;
        };
    }
}
