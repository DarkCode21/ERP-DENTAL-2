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

/**
 *  Controller to list the items in the List Product controller
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
            $this->addView($viewName, 'Join\VariantLocation', 'locations', 'fas fa-search-location');
            $this->addSearchFields($viewName, ['aisle', 'rack', 'shelf', 'drawer']);
            $this->addOrderBy($viewName, ['codewarehouse', 'aisle', 'rack', 'shelf', 'drawer'], 'warehouse');
            $this->addOrderBy($viewName, ['aisle', 'rack', 'shelf', 'drawer', 'codewarehouse'], 'location');

            $warehouseValues = $this->codeModel->all('almacenes', 'codalmacen', 'nombre');
            $this->addFilterSelect($viewName, 'warehouse', 'warehouse', 'codewarehouse', $warehouseValues);

            $aisleValues = $this->codeModel->all('locations', 'aisle', 'aisle');
            $this->addFilterSelect($viewName, 'aisle', 'aisle', 'aisle', $aisleValues);

            $this->addFilterAutocomplete($viewName, 'product', 'product', 'productos.referencia', 'productos', 'referencia', 'descripcion');
            $this->addFilterAutocomplete($viewName, 'reference', 'reference', 'reference', 'Variante', 'referencia', 'referencia');

            /// disable buttons
            $this->setSettings($viewName, 'btnNew', false);
            $this->setSettings($viewName, 'btnDelete', false);
            $this->setSettings($viewName, 'checkBoxes', false);
        };
    }
}
