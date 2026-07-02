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
namespace FacturaScripts\Plugins\Ubicaciones\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\Ubicaciones\Model\Location;

/**
 * Controller to edit a single item from the Location model
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditVariantLocation extends EditController
{

    protected function createViews()
    {
        parent::createViews();

        /// disable new button
        $this->setSettings($this->getMainViewName(), 'btnNew', false);
    }

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'VariantLocation';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'variant-location';
        $pagedata['menu'] = 'warehouse';
        $pagedata['icon'] = 'fas fa-search-location';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    /**
     * Loads the data to display.
     *
     * @param string   $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);

        $mainViewName = $this->getMainViewName();
        if ($viewName == $mainViewName) {
            $view->disableColumn('code', true);  // Force disable PK column
            $view->disableColumn('product-code', true);  // Force disable Link column with product

            // Load product, variant and location data
            $this->loadProductData($viewName);
            $this->loadVariantData($viewName);
            $this->loadLocationDescription($viewName);
        }
    }

    /**
     * Run the autocomplete action.
     * Returns a JSON string for the searched values.
     *
     * @return array
     */
    protected function autocompleteAction(): array
    {
        $source = $this->request->get('source', '');
        switch ($source) {
            case 'locations':
                return $this->autocompleteForLocations();

            default:
                return parent::autocompleteAction();
        }
    }

    /**
     * Get autocomplete locations items for search user terms
     *
     * @return array
     */
    protected function autocompleteForLocations(): array
    {
        $data = $this->requestGet(['field', 'fieldcode', 'source', 'term', 'codewarehouse']);
        $where = $this->getAutocompleteWhere($data);
        $order = [ 'aisle' => 'ASC', 'rack' => 'ASC', 'shelf' => 'ASC', 'drawer' => 'ASC' ];

        $results = [];
        $location = new Location();
        foreach ($location->all($where, $order) as $row) {
            $results[] = ['key' => $row->id, 'value' => $row->descriptionComplete()];
        }

        if (empty($results)) {
            $i18n = static::toolBox()->i18n();
            $results[] = ['key' => null, 'value' => $i18n->trans('no-data')];
        }

        return $results;
    }

    /**
     * Return array of where filters from user form data
     *
     * @param array $data
     * @return DataBaseWhere[]
     */
    private function getAutocompleteWhere($data)
    {
        $result = empty($data['codewarehouse'])
            ? [ new DataBaseWhere('codewarehouse', null, 'IS') ]
            : [ new DataBaseWhere('codewarehouse', $data['codewarehouse']) ];

        foreach ($this->getColumnValuesWhere('aisle|rack|shelf|drawer', $data['term']) as $condition) {
            $result[] = $condition;
        }
        return $result;
    }

    /**
     * Get correct database where filter for user terms in base filter columns
     *
     * @param string $fields
     * @param string $values
     * @return DataBaseWhere[]
     */
    private function getColumnValuesWhere($fields, $values)
    {
        $result = [];
        $column1 = explode('|', $fields);
        $column2 = explode(' ', $values);
        $maxValues = count($column1) - 1;

        for ($index = 0; $index < count($column2); $index++) {
            $result[] = new DataBaseWhere($column1[$index], mb_strtolower($column2[$index], 'UTF8'), 'LIKE');
            if ($index == $maxValues) {
                return $result;
            }
        }

        return $result;
    }

    /**
     * Get a array list for Widget Select of all References of one product
     *
     * @param int $idproduct
     * @return array
     */
    private function getReferencesForProduct($idproduct): array
    {
        $where = [ new DataBaseWhere('idproducto', $idproduct) ];
        $order = [ 'referencia' => 'ASC' ];
        $result = [];

        $variant = new Variante();
        foreach ($variant->all($where, $order, 0, 0) as $row) {
            $description = $row->description(true);
            $title = empty($description)
                ? $row->referencia
                : $row->referencia . ' : ' . $description;

            $result[] = ['value' => $row->referencia, 'title' => $title];
        }
        return $result;
    }

    /**
     * Create variant product model and load data
     *
     * @param string $viewName
     */
    private function loadLocationDescription($viewName)
    {
        $idlocation = $this->getViewModelValue($viewName, 'idlocation');
        if (empty($idlocation)) {
            return;
        }

        $location = new Location();
        if (false === $location->loadFromCode($idlocation)) {
            return;
        }

        $this->views[$viewName]->model->storagetype = $location->storagetype;

        $columnLocation = $this->views[$viewName]->columnForName('location');
        if ($columnLocation) {
            $columnLocation->widget->setSelected($location->descriptionComplete());
        }
    }

    /**
     * Create product model and load data
     *
     * @param string $viewName
     */
    private function loadProductData($viewName)
    {
        $idproduct = $this->getViewModelValue($viewName, 'idproduct');
        if (empty($idproduct)) {
            return;
        }

        $product = new Producto();
        if ($product->loadFromCode($idproduct)) {
            // Inject the product values into the main model. Is necessary for the xml view.
            $mainModel = $this->getModel();
            $mainModel->productreference = $product->referencia;
            $mainModel->productdescription = $product->descripcion;
        }
    }

    /**
     * Create variant product model and load data
     *
     * @param string $viewName
     */
    private function loadVariantData($viewName)
    {
        $idproduct = $this->getViewModelValue($viewName, 'idproduct');
        if (empty($idproduct)) {
            return;
        }

        // Add variant data to widget select array
        $columnReference = $this->views[$viewName]->columnForName('reference');
        if ($columnReference) {
            $values = $this->getReferencesForProduct($idproduct);
            $columnReference->widget->setValuesFromArray($values, false);
        }
    }
}
