<?php
/**
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Extension\Model;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\ProductoLote;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Variante
{
    public function getLotes(): Closure
    {
        return function (bool $whitQty = false) {
            $where = [new DataBaseWhere('referencia', $this->referencia)];
            if ($whitQty) {
                $where[] = new DataBaseWhere('cantidad', 0, '>');
            }
            return ProductoLote::all($where, ['fecha' => 'ASC'], 0, 0);
        };
    }
}
