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
 * Familia de producto asignada a una estación de preparación.
 */
class RestEstacionFamilia extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $codfamilia;

    /** @var int */
    public $id;

    /** @var int */
    public $idestacion;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'rest_estacion_familias';
    }

    public function clear(): void
    {
        parent::clear();
        $this->codfamilia = '';
        $this->idestacion = null;
    }

    public function url(string $type = 'auto', string $list = 'AjustesRestauranteTPV#List'): string
    {
        if ($type === 'list' || ($type === 'auto' && empty($this->id))) {
            return empty($this->idestacion)
                ? 'AjustesRestauranteTPV#ListRestEstacion'
                : 'EditRestEstacion?code=' . $this->idestacion;
        }
        return parent::url($type, $list);
    }

    public function test(): bool
    {
        if (empty($this->idestacion) || empty($this->codfamilia)) {
            self::toolBox()::log()->error('La familia debe estar asociada a una estación.');
            return false;
        }
        return parent::test();
    }
}
