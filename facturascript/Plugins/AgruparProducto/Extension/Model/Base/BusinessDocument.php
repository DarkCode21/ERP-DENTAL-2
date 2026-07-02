<?php
/**
 * This file is part of AgruparProducto plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * AgruparProducto Copyright (C) 2022-2022 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\AgruparProducto\Extension\Model\Base;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\ProductGroupingLine;

/**
 * Description of BusinessDocument
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class BusinessDocument
{

    public function getNewProductLine()
    {
        return function($newLine, $variant, $product) {
            if (class_exists('BussinesDocumentFormTools')) {
                $this->oldCalculate($newLine, $variant, $product);
                return;
            }
            $this->calculate($newLine, $product);
        };
    }

    /**
     * For old document forms.
     */
    public function oldCalculate() {
        return function($newLine, $product) {
            $quantities = [];

            $groupingLine = new ProductGroupingLine();
            $where = [new DataBaseWhere('idproduct', $product->primaryColumnValue())];
            foreach ($groupingLine->all($where, ['quantity' => 'ASC']) as $grouping) {
                $quantities[] = $grouping->quantity;
                if ($grouping->bydefault) {
                    $newLine->cantidad = $grouping->quantity;
                }
            }

            $newLine->groupings = \implode(' / ', $quantities);
        };
    }

    /**
     * For actual document forms.
     */
    public function calculate() {
        return function($newLine, $product) {
            $groupingLine = new ProductGroupingLine();
            $where = [
                new DataBaseWhere('idproduct', $product->primaryColumnValue()),
                new DataBaseWhere('bydefault', true),
            ];
            if ($groupingLine->loadFromCode('', $where)) {
                $newLine->cantidad = $groupingLine->quantity;
            }
        };
    }
}
