<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Extension\Model;

use Closure;

class Variante
{
    public function save(): Closure
    {
        return function () {
            $this->getProducto()->save();
        };
    }
}