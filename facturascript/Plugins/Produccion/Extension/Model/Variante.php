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
use FacturaScripts\Plugins\Produccion\Lib\Produccion\RecipeCostUpdater;

/**
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * @property string $referencia
 * @method isDirty(string $field): bool
 */
class Variante
{
    public function onUpdate(): Closure
    {
        return function (): void {
            if ($this->isDirty('coste')) {
                RecipeCostUpdater::update($this->referencia);
            }
        };
    }
}
