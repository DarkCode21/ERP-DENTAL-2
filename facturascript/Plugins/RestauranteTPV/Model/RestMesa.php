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

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

/**
 * Mesa del restaurante.
 * Estados: libre | ocupada | reservada
 */
class RestMesa extends ModelClass
{
    use ModelTrait;

    const ESTADO_LIBRE     = 'libre';
    const ESTADO_OCUPADA   = 'ocupada';
    const ESTADO_RESERVADA = 'reservada';

    /** @var int */
    public $capacidad;

    /** @var string */
    public $estado;

    /** @var int */
    public $idmesa;

    /** @var int|null */
    public $idzona;

    /** @var string */
    public $nombre;

    /** @var int */
    public $pos_x;

    /** @var int */
    public $pos_y;

    public static function primaryColumn(): string
    {
        return 'idmesa';
    }

    public static function tableName(): string
    {
        return 'rest_mesas';
    }

    public function url(string $type = 'auto', string $list = 'AjustesRestauranteTPV#List'): string
    {
        return parent::url($type, $list);
    }

    public function clear(): void
    {
        parent::clear();
        $this->capacidad = 4;
        $this->estado    = self::ESTADO_LIBRE;
        $this->idzona    = null;
        $this->nombre    = '';
        $this->pos_x     = 0;
        $this->pos_y     = 0;
    }

    public function isLibre(): bool
    {
        return $this->estado === self::ESTADO_LIBRE;
    }

    public function test(): bool
    {
        if (empty($this->nombre) && empty($this->idmesa)) {
            $this->nombre = $this->generateAutoNombre();
        }

        if (empty($this->nombre)) {
            self::toolBox()::log()->error('El nombre de la mesa no puede estar vacío.');
            return false;
        }
        if (!in_array($this->estado, [self::ESTADO_LIBRE, self::ESTADO_OCUPADA, self::ESTADO_RESERVADA])) {
            self::toolBox()::log()->error('Estado de mesa no válido: ' . $this->estado);
            return false;
        }
        return parent::test();
    }

    private function generateAutoNombre(): string
    {
        $prefix = $this->getZonaPrefijo();
        if ('' === $prefix) {
            return '';
        }

        $db = new DataBase();
        $sql = 'SELECT nombre FROM ' . self::tableName() . ' WHERE nombre LIKE ' . $db->var2str($prefix . '%');
        $maxNumber = 0;

        foreach ($db->select($sql) as $row) {
            $nombre = trim((string)($row['nombre'] ?? ''));
            if ('' === $nombre || 0 !== strpos($nombre, $prefix)) {
                continue;
            }

            $suffix = substr($nombre, strlen($prefix));
            if ('' !== $suffix && ctype_digit($suffix)) {
                $maxNumber = max($maxNumber, (int)$suffix);
            }
        }

        return $prefix . ($maxNumber + 1);
    }

    private function getZonaPrefijo(): string
    {
        if (empty($this->idzona)) {
            return '';
        }

        $zona = new RestZona();
        if (false === $zona->loadFromCode($this->idzona)) {
            return '';
        }

        return $zona->getPrefijo();
    }
}
