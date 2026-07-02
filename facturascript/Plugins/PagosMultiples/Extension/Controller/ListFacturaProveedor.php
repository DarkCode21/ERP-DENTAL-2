<?php
/**
 * This file is part of PagosMultiples plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * PagosMultiples Copyright (C) 2022-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\PagosMultiples\Extension\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;

/**
 *  Controller to list the items in the List Invoice controller
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class ListFacturaProveedor
{
    /**
     * Load views
     */
    public function createViews()
    {
        return function() {
            $this->createViewReceiptGroup();
        };
    }

    /**
     * Add and configure Receipt Group list view
     *
     * @param string $viewName
     */
    public function createViewReceiptGroup()
    {
        return function($viewName = 'ListSupplierReceiptGroup') {
            $this->addView($viewName, 'SupplierReceiptGroup', 'multiple-payments', 'fas fa-coins');
            $this->addSearchFields($viewName, ['id', 'concept', 'groupdate', 'total', 'notes']);
            $this->addOrderBy($viewName, ['groupdate'], 'date', 2);
            $this->addOrderBy($viewName, ['total'], 'total');
            $this->addOrderBy($viewName, ['idserie', 'groupdate'], 'serie');
            $this->addOrderBy($viewName, ['id'], 'code');

            $this->addFilterAutocomplete($viewName, 'idserie', 'serie', 'idserie', 'series', 'codserie', 'descripcion');
            $this->addFilterAutocomplete($viewName, 'idbank', 'bank-account', 'idbank', 'cuentasbanco', 'codcuenta', 'descripcion');
            $this->addFilterPeriod($viewName, 'groupdate', 'date', 'groupdate');

            $this->addFilterSelectWhere($viewName, 'status', [
                ['label' => Tools::lang()->trans('all'), 'where' => []],
                ['label' => Tools::lang()->trans('pending'), 'where' => [new DataBaseWhere('status', 0)]],
                ['label' => Tools::lang()->trans('approved'), 'where' => [new DataBaseWhere('status', 1)]],
            ]);

            if ($this->empresa->count() < 2) {
                $this->views[$viewName]->disableColumn('company');
            }
        };
    }
}
