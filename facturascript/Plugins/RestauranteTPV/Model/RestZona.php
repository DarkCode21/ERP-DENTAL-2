<?php
/**
 * This file is part of RestauranteTPV plugin for FacturaScripts
 * Copyright (C) 2026 Interibérica Informática
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\RestauranteTPV\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

/**
 * Zona del local (interior, terraza, barra, etc.)
 */
class RestZona extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $descripcion;

    /** @var int */
    public $idzona;

    /** @var string */
    public $nombre;

    /** @var string */
    public $prefijo;

    /** @var RestMesa[] Mesas de esta zona, cargadas dinámicamente por PanelMesas */
    public $mesas = [];

    public static function primaryColumn(): string
    {
        return 'idzona';
    }

    public static function tableName(): string
    {
        return 'rest_zonas';
    }

    public function url(string $type = 'auto', string $list = 'AjustesRestauranteTPV#List'): string
    {
        return parent::url($type, $list);
    }

    public function clear(): void
    {
        parent::clear();
        $this->descripcion = '';
        $this->nombre = '';
        $this->prefijo = '';
    }

    public function getPrefijo(): string
    {
        $prefijo = $this->normalizePrefix($this->prefijo);
        if ('' !== $prefijo) {
            return $prefijo;
        }

        return $this->normalizePrefix(mb_substr(trim((string)$this->nombre), 0, 1, 'UTF-8'));
    }

    public function test(): bool
    {
        if (empty($this->nombre)) {
            self::toolBox()::log()->error('El nombre de la zona no puede estar vacío.');
            return false;
        }

        $this->prefijo = $this->getPrefijo();

        if (empty($this->prefijo)) {
            self::toolBox()::log()->error('El prefijo de la zona no puede estar vacío.');
            return false;
        }

        return parent::test();
    }

    private function normalizePrefix(string $prefix): string
    {
        $prefix = mb_strtoupper(trim($prefix), 'UTF-8');
        return preg_replace('/[^A-Z0-9]/i', '', $prefix) ?? '';
    }
}
