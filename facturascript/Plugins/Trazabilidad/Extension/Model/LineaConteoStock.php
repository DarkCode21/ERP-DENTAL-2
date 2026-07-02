<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Extension\Model;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\LineaConteoStockTraza;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class LineaConteoStock
{
    public function getLinesTraza(): Closure
    {
        return function () {
            $where = [
                new DataBaseWhere('idconteo', $this->idconteo),
                new DataBaseWhere('idlinea', $this->idlinea)
            ];
            return LineaConteoStockTraza::all($where, [], 0, 0);
        };
    }
}
