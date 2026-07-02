<?php
/**
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Extension\Model;

use Closure;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class LineaAlbaranCliente
{
    use TrazabilidadLineaModelTrait;

    public function delete(): Closure
    {
        return function () {
            $this->deleteMovimientosLinea();
        };
    }

    public function saveInsert(): Closure
    {
        return function () {
            $this->insertBatchSerial();
        };
    }

    public function onChange(): Closure
    {
        return function ($field) {
            if ($field == 'actualizastock') {
                $this->updateStockLoteMovimiento($field);
            }
        };
    }
}
