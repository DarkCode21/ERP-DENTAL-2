<?php
/**
 * Copyright (C) 2022-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Extension\Model;

use Closure;
use FacturaScripts\Core\Tools;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Producto
{
    public function clear(): Closure
    {
        return function () {
            $this->trazabilidad = false;
        };
    }

    public function test(): Closure
    {
        return function () {
            // sin trazabilidad (false o null)
            if (false == $this->trazabilidad) {
                $this->trazabilidad = false;
                return true;
            }

            // con trazabilidad
            if ($this->nostock) {
                Tools::log()->warning('traceability-no-stock-error');
                return false;
            } elseif ($this->ventasinstock) {
                Tools::log()->warning('traceability-sale-wo-stock-error');
                return false;
            } elseif ($this->publico) {
                Tools::log()->warning('traceability-public-error');
                return false;
            }

            return true;
        };
    }
}
