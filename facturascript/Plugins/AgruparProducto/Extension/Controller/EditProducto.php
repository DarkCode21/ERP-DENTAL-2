<?php
/**
 * This file is part of AgruparProducto plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * AgruparProducto Copyright (C) 2022-2022 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\AgruparProducto\Extension\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\ProductGroupingLine;

/**
 * Class to list the product grouping line items in the Producto edit view
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditProducto
{
    /**
     * Load views
     */
    public function createViews()
    {
        return function() {
            $this->addEditListView('EditProductGroupingLine', 'ProductGroupingLine', 'product-grouping', 'fas fa-box-open');
        };
    }

    /**
     * This function is called when creating the model table. Returns the SQL
     * that will be executed after the creation of the table. Useful to insert values
     * default.
     *
     * @return string
     */
    public function install()
    {
        return function() {
            new ProductGroupingLine();
        };
    }

    /**
     * Load view data procedure
     *
     * @param string                      $viewName
     * @param ExtendedController\BaseView $view
     * @return function
     */
    public function loadData()
    {
        return function($viewName, $view) {
            if ($viewName == 'EditProductGroupingLine') {
                $mainViewName = $this->getMainViewName();
                $idproduct = $this->getViewModelValue($mainViewName, 'idproducto');
                $this->loadDataProductGroupingLine($view, $idproduct);
            }
        };
    }

    /**
     * Load Product List of Product Grouping Line
     *
     * @return function
     */
    public function loadDataProductGroupingLine()
    {
        return function($view, $idproduct) {
            $where = [new DataBaseWhere('idproduct', $idproduct)];
            $order = ['id' => 'DESC'];
            $view->loadData('', $where, $order);
        };
    }
}