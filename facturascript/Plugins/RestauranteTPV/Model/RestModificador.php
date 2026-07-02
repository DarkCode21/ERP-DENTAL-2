<?php
/**
 * This file is part of RestauranteTPV plugin for FacturaScripts
 * Copyright (C) 2026 Interibérica Informática
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 */

namespace FacturaScripts\Plugins\RestauranteTPV\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

/**
 * Modificador/agregado disponible en el TPV.
 * Ejemplos: "Extra queso +1.50", "Sin gluten +0.00", "Patatas +2.00".
 */
class RestModificador extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $idmodificador;

    /** @var string */
    public $nombre;

    /** @var float */
    public $precio;

    public static function primaryColumn(): string
    {
        return 'idmodificador';
    }

    public static function tableName(): string
    {
        return 'rest_modificadores';
    }

    public function url(string $type = 'auto', string $list = 'AjustesRestauranteTPV#List'): string
    {
        return parent::url($type, $list);
    }

    public function clear(): void
    {
        parent::clear();
        $this->nombre = '';
        $this->precio = 0.0;
    }
}
