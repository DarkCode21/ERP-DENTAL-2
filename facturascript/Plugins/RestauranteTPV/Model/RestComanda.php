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
use FacturaScripts\Core\Tools;

/**
 * Cabecera de comanda.
 * Cada comanda pertenece a una mesa y puede estar ligada
 * a un PresupuestoCliente de FacturaScripts.
 * Estados: abierta | cobrada | cancelada
 */
class RestComanda extends ModelClass
{
    use ModelTrait;

    const ESTADO_ABIERTA    = 'abierta';
    const ESTADO_EN_PROCESO = 'en-proceso';
    const ESTADO_COBRADA    = 'cobrada';
    const ESTADO_CANCELADA  = 'cancelada';

    const TIPO_MESA        = 'in-table';
    const TIPO_PARA_LLEVAR = 'take-away';
    const TIPO_DELIVERY    = 'delivery';

    /** @var string */
    public $codcamarero;

    /** @var string */
    public $estado;

    /** @var string */
    public $fecha;

    /** @var string */
    public $hora;

    /** @var int */
    public $idcomanda;

    /** @var int|null */
    public $idmesa;

    /** @var int|null */
    public $idfactura;

    /** @var int|null */
    public $idpedido;

    /** @var string */
    public $observaciones;

    /** @var float */
    public $total;

    /** @var string */
    public $tipo;

    public static function primaryColumn(): string
    {
        return 'idcomanda';
    }

    public static function tableName(): string
    {
        return 'rest_comandas';
    }

    public function clear(): void
    {
        parent::clear();
        $this->codcamarero   = '';
        $this->estado        = self::ESTADO_ABIERTA;
        $this->fecha         = Tools::date();
        $this->hora          = Tools::hour();
        $this->idmesa        = null;
        $this->idfactura = null;
        $this->idpedido  = null;
        $this->observaciones = '';
        $this->tipo          = self::TIPO_MESA;
        $this->total         = 0.0;
    }

    public function url(string $type = 'auto', string $list = 'AjustesRestauranteTPV#List'): string
    {
        return parent::url($type, $list);
    }

    public function isAbierta(): bool
    {
        return $this->estado === self::ESTADO_ABIERTA;
    }

    public function test(): bool
    {
        if ($this->tipo === self::TIPO_MESA && empty($this->idmesa)) {
            self::toolBox()::log()->error('La comanda de tipo mesa debe estar asociada a una mesa.');
            return false;
        }
        if (!in_array($this->estado, [self::ESTADO_ABIERTA, self::ESTADO_EN_PROCESO, self::ESTADO_COBRADA, self::ESTADO_CANCELADA])) {
            self::toolBox()::log()->error('Estado de comanda no válido: ' . $this->estado);
            return false;
        }
        if (!in_array($this->tipo, [self::TIPO_MESA, self::TIPO_PARA_LLEVAR, self::TIPO_DELIVERY])) {
            $this->tipo = self::TIPO_MESA;
        }
        return parent::test();
    }
}
