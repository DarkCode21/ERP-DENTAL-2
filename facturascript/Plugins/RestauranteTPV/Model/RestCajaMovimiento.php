<?php
/**
 * This file is part of RestauranteTPV plugin for FacturaScripts
 * Copyright (C) 2026 Interiberica Informatica
 */

namespace FacturaScripts\Plugins\RestauranteTPV\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

class RestCajaMovimiento extends ModelClass
{
    use ModelTrait;

    public $concepto;
    public $fecha;
    public $idcaja;
    public $idmov;
    public $importe;
    public $nick;
    public $tipo;

    public function clear(): void
    {
        parent::clear();
        $this->concepto = '';
        $this->fecha = date(self::DATETIME_STYLE);
        $this->importe = 0.0;
        $this->tipo = 'in';
    }

    public static function primaryColumn(): string
    {
        return 'idmov';
    }

    public static function tableName(): string
    {
        return 'rest_caja_movimientos';
    }
}
