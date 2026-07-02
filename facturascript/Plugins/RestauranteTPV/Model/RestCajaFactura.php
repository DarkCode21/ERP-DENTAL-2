<?php
/**
 * This file is part of RestauranteTPV plugin for FacturaScripts
 * Copyright (C) 2026 Interiberica Informatica
 */

namespace FacturaScripts\Plugins\RestauranteTPV\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

class RestCajaFactura extends ModelClass
{
    use ModelTrait;

    public $fecha;
    public $id;
    public $idcaja;
    public $idfactura;
    public $importe;
    public $nick;

    public function clear(): void
    {
        parent::clear();
        $this->fecha = date(self::DATETIME_STYLE);
        $this->importe = 0.0;
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'rest_caja_facturas';
    }
}
