<?php
/**
 * This file is part of the Produccion plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 * All Rights Reserved.
 */
namespace FacturaScripts\Plugins\Produccion\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Lib\ExtendedController\BaseView;

/**
 * Class to list the recipe items in the Producto edit view
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * @method addListView(string $viewName, string $modelName, string $viewTitle, string $viewIcon = 'fa-solid fa-list'): ListView
 * @method getMainViewName(): string
 * @method getModel(): Producto
 */
class EditProducto
{
    /**
     * Load views
     */
    public function createViews(): Closure
    {
        return function (): void {
            $this->addListView('ListReceta', 'Join\Receta', 'recipes', 'fa-solid fa-clipboard-list')
                ->setSettings('btnNew', false)
                ->setSettings('btnDelete', false);
        };
    }

    /**
     * Load view data procedure
     *
     * @return Closure
     */
    public function loadData(): Closure
    {
        /**
         * @param string $viewName
         * @param BaseView $view
         */
        return function (string $viewName, $view): void {
            if ($viewName == 'ListReceta') {
                $this->loadDataRecipes($view, $this->getModel()->idproducto);
                return;
            }

            if ($viewName == $this->getMainViewName()) {
                if (false === (bool)$this->getModel()->numserie ?? false) {
                    $view->disableColumn('prefix');
                }
            }
        };
    }

    /**
     * Load Recipes List for Product
     *
     * @return Closure
     */
    protected function loadDataRecipes(): Closure
    {
        return function ($view, $idproduct): void {
            $sql = 'SELECT DISTINCT lr.idreceta'
                . ' FROM produccion_lineasrecetas lr LEFT JOIN variantes v2 ON v2.referencia = lr.referencia'
                . ' WHERE v2.idproducto = ' . $idproduct;

            $ids = [];
            $database = new DataBase();
            foreach ($database->select($sql) as $row) {
                $ids[] = $row['idreceta'];
            }

            if (false === empty($ids)) {
                $where = [new DataBaseWhere('recetas.idreceta', implode(',', $ids), 'IN')];
                $order = ['recetas.codreceta' => 'ASC'];
                $view->loadData('', $where, $order);
            }
        };
    }
}
