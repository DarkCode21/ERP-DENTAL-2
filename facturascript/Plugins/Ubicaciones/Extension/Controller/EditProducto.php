<?php
/**
 * This file is part of Ubicaciones plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Ubicaciones    Copyright (C) 2019-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\Plugins\Ubicaciones\Extension\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

/**
 * Controller to edit a single item from the Producto controller
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
            $this->createViewVariantLocations();
        };
    }

    /**
     * Add and configure Variant Location list view
     *
     * @param string $viewName
     */
    public function createViewVariantLocations()
    {
        return function($viewName = 'ListVariantLocation') {
            $this->addListView($viewName, 'Join\VariantLocation', 'locations', 'fas fa-search-location');
            $this->views[$viewName]->addOrderBy(['codewarehouse', 'aisle', 'rack', 'shelf', 'drawer'], 'warehouse');
            $this->views[$viewName]->addOrderBy(['aisle', 'rack', 'shelf', 'drawer', 'codewarehouse'], 'location');
            $this->views[$viewName]->searchFields = ['aisle', 'rack', 'shelf', 'drawer'];

            /// disable column
            $this->views[$viewName]->disableColumn('product');
        };
    }

    /**
     * Load view data procedure
     *
     * @param string                      $viewName
     * @param ExtendedController\BaseView $view
     */
    public function loadData()
    {
        return function($viewName, $view) {
            if ($viewName == 'ListVariantLocation') {
                $mainViewName = $this->getMainViewName();
                $idproduct = $this->getViewModelValue($mainViewName, 'idproducto');
                $where = [new DataBaseWhere('idproduct', $idproduct)];
                $order = ['codewarehouse' => 'ASC', 'reference' => 'ASC'];
                $view->loadData('', $where, $order);
            }
        };
    }
}
