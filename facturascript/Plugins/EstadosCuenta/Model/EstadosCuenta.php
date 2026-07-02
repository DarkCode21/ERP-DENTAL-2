<?php

namespace FacturaScripts\Plugins\EstadosCuenta\Model;

use FacturaScripts\Core\Model\Base;

class EstadosCuenta extends Base\ModelClass
{
    use Base\ModelTrait;   

    public $diferencia;

    public static function primaryColumn(): string
    {
        return 'codcliente';
    }

    public static function tableName(): string
    {
        return 'clientes';
    }
    public function primaryDescriptionColumn(): string
    {
        return 'nombre';
    }
}