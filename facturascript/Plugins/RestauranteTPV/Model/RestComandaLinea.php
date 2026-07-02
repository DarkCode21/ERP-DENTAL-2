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
 * Línea de una comanda (producto pedido con su estado).
 * Estados: pendiente | preparado | servido | cancelado
 */
class RestComandaLinea extends ModelClass
{
    use ModelTrait;

    const ESTADO_PENDIENTE  = 'pendiente';
    const ESTADO_PREPARADO  = 'preparado';
    const ESTADO_SERVIDO    = 'servido';
    const ESTADO_CANCELADO  = 'cancelado';

    /** @var float */
    public $cantidad;

    /** @var string */
    public $descripcion;

    /** @var string */
    public $estado;

    /** @var int */
    public $idcomanda;

    /** @var int 0=nuevo (no enviado), 1=antiguo (ya enviado a cocina) */
    public $enviado;

    /** @var int|null */
    public $idlinea_padre;

    /** @var int */
    public $idlinea;

    /** @var string */
    public $observaciones;

    /** @var float */
    public $pvpunitario;

    /** @var string */
    public $referencia;

    public static function primaryColumn(): string
    {
        return 'idlinea';
    }

    public static function tableName(): string
    {
        return 'rest_comandas_lineas';
    }

    public function clear(): void
    {
        parent::clear();
        $this->enviado      = 0;
        $this->cantidad     = 1.0;
        $this->descripcion  = '';
        $this->estado       = self::ESTADO_PENDIENTE;
        $this->observaciones = '';
        $this->pvpunitario  = 0.0;
        $this->referencia   = '';
        $this->idlinea_padre = null;
    }

    public function subtotal(): float
    {
        return $this->cantidad * $this->pvpunitario;
    }

    public function test(): bool
    {
        if (empty($this->idcomanda)) {
            self::toolBox()::log()->error('La línea debe estar asociada a una comanda.');
            return false;
        }
        if (empty($this->descripcion)) {
            self::toolBox()::log()->error('La descripción del producto no puede estar vacía.');
            return false;
        }
        if (!in_array($this->estado, [self::ESTADO_PENDIENTE, self::ESTADO_PREPARADO, self::ESTADO_SERVIDO, self::ESTADO_CANCELADO])) {
            self::toolBox()::log()->error('Estado de línea no válido: ' . $this->estado);
            return false;
        }
        return parent::test();
    }
}
