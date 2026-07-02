<?php
/**
 * This file is part of the Produccion plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 * All Rights Reserved.
 */
namespace FacturaScripts\Plugins\Produccion\Extension\Model;

use Closure;
use FacturaScripts\Core\Tools;

/**
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * @property bool $numserie
 * @property bool $trazabilidad
 * @method hasColumn(string $field): bool
 */
class Producto
{
    public function clear(): Closure
    {
        return function () {
            $this->numserie = false;
        };
    }

    public function test(): Closure
    {
        return function () {
            if ($this->hasColumn('trazabilidad')) {
                if ($this->numserie && $this->trazabilidad) {
                    Tools::log()->warning('numserie-traceability-error');
                    return false;
                }
            }

            return true;
        };
    }
}
