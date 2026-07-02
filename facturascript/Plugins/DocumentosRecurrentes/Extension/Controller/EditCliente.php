<?php
/**
 * This file is part of DocumentosRecurrentes plugin for FacturaScripts.
 * FacturaScripts         Copyright (C) 2015-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DocumentosRecurrentes  Copyright (C) 2020-2021 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DocumentosRecurrentes\Extension\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

/**
 * Description of EditCliente
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Jose Antonio Cuello <yopli2000@gmail.com>
 */
class EditCliente
{

    public function createViews()
    {
        return function() {
            $viewName = 'ListDocRecurringSale';
            $this->addListView($viewName, 'DocRecurringSale', 'recurring', 'fas fa-calendar-plus');
            $this->views[$viewName]->addSearchFields(['name']);
            $this->views[$viewName]->addOrderBy(['id'], 'code');
            $this->views[$viewName]->addOrderBy(['name'], 'description');
            $this->views[$viewName]->addOrderBy(['nextdate', 'name'], 'next-date', 1);
            $this->views[$viewName]->addOrderBy(['lastdate', 'name'], 'last-date');

            /// disable columns
            $this->views[$viewName]->disableColumn('customer');
        };
    }

    public function loadData()
    {
        return function($viewName, $view) {
            if ($viewName === 'ListDocRecurringSale') {
                $codcliente = $this->getViewModelValue($this->getMainViewName(), 'codcliente');
                $where = [new DataBaseWhere('codcliente', $codcliente)];
                $view->loadData('', $where);
            }
        };
    }
}
