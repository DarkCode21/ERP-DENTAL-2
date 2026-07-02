<?php
/**
 * This file is part of AgruparProducto plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * AgruparProducto Copyright (C) 2022-2023 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\AgruparProducto\Extension\Model\Base;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\ProductGroupingLine;
use Closure;

/**
 * Description of SalesDocumentLine
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class SalesDocumentLine
{

    public function saveInsertBefore(): Closure
    {
        return function() {
            if (empty($this->idproducto) || $this->cantidad <= 1) {
                return;
            }

            $this->applyGroupingDiscount();
        };
    }

    public function saveUpdateBefore(): Closure
    {
        return function() {
            if (empty($this->idproducto) || $this->cantidad <= 1) {
                return;
            }

            $this->applyGroupingDiscount();
        };
    }

    public function applyGroupingDiscount(): Closure
    {
        return function () {
            $groupingLine = new ProductGroupingLine();
            $where = [
                new DataBaseWhere('discount', 0, '>'),
                new DataBaseWhere('idproduct', $this->idproducto),
            ];
            $order = ['quantity' => 'DESC'];
            foreach ($groupingLine->all($where, $order) as $grouping) {
                /// Is quantity a multiplier of grouping quantity?
                if ($this->cantidad % $grouping->quantity != 0) {
                    continue;
                }

                if ($this->dtopor < $grouping->discount) {
                    $this->dtopor = $grouping->discount;
                }
                break;
            }
        };
    }
}
