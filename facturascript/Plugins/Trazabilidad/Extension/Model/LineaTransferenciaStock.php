<?php
/**
 * Copyright (C) 2024-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Extension\Model;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\ProductoLote;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class LineaTransferenciaStock
{
    public function getLoteDest(): Closure
    {
        return function () {
            $lote = new ProductoLote();
            $where = [
                new DataBaseWhere('codalmacen', $this->getTransference()->codalmacendestino),
                new DataBaseWhere('numserie', $this->numserie),
                new DataBaseWhere('referencia', $this->referencia)
            ];
            $lote->loadFromCode('', $where);
            return $lote;
        };
    }

    public function getLoteOrig(): Closure
    {
        return function () {
            $lote = new ProductoLote();
            $where = [
                new DataBaseWhere('codalmacen', $this->getTransference()->codalmacenorigen),
                new DataBaseWhere('numserie', $this->numserie),
                new DataBaseWhere('referencia', $this->referencia)
            ];
            $lote->loadFromCode('', $where);
            return $lote;
        };
    }
}
