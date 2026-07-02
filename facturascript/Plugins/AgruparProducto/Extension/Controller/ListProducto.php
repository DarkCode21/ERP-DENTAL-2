<?php
/**
 * This file is part of AgruparProducto plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * AgruparProducto Copyright (C) 2022-2022 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\AgruparProducto\Extension\Controller;

/**
 * Class to add list view of product grouping in the product list view,
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class ListProducto
{
    /**
     * Load views
     */
    public function createViews()
    {
        return function() {
            $this->createViewProductGrouping();
        };
    }

    /**
     * Add and connfigure Commissions Groups list view
     *
     * @param string $viewName
     */
    public function createViewProductGrouping()
    {
        return function($viewName = 'ListProductGrouping') {
            $this->addView($viewName, 'ProductGrouping', 'product-grouping', 'fas fa-box');
            $this->addSearchFields($viewName, ['CAST(id AS VARCHAR)', 'name']);
            $this->addOrderBy($viewName, ['id'], 'code');
            $this->addOrderBy($viewName, ['name'], 'description', 1);
        };
    }
}
