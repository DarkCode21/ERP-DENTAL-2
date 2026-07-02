<?php
/**
 * This file is part of DocumentosRecurrentes plugin for FacturaScripts.
 * FacturaScripts         Copyright (C) 2015-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DocumentosRecurrentes  Copyright (C) 2020-2021 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DocumentosRecurrentes\Lib\DocumentosRecurrentes;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Lib\ExtendedController\ListController;
use FacturaScripts\Dinamic\Model\Base\DocRecurring;

/**
 * Description of ListDocRecurring
 *
 * @author Carlos Garcia Gomez  <carlos@facturascripts.com>
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
abstract class ListDocRecurring extends ListController
{

    protected function addCommonSearchFields(string $viewName)
    {
        $this->addSearchFields($viewName, ['name']);
    }


    protected function addCommonOrderBy(string $viewName)
    {
        $this->addOrderBy($viewName, ['id'], 'code');
        $this->addOrderBy($viewName, ['name'], 'description');
        $this->addOrderBy($viewName, ['nextdate', 'name'], 'next-date', 1);
        $this->addOrderBy($viewName, ['lastdate', 'name'], 'last-date');
    }

    protected function addCommonFilters(string $viewName)
    {
        $this->addFilterPeriod($viewName, 'nextdate', 'next-date', 'nextdate');
        $this->addFilterPeriod($viewName, 'lastdate', 'last-date', 'lastdate');

        $companies = $this->codeModel->all('empresas', 'idempresa', 'nombrecorto');
        if (count($companies) > 2) {
            $this->addFilterSelect($viewName, 'idempresa', 'company', 'idempresa', $companies);
        }

        $warehouses = $this->codeModel->all('almacenes', 'codalmacen', 'nombre');
        if (count($warehouses) > 2) {
            $this->addFilterSelect($viewName, 'codalmacen', 'warehouse', 'codalmacen', $warehouses);
        }

        $series = $this->codeModel->all('series', 'codserie', 'descripcion');
        if (count($series) > 2) {
            $this->addFilterSelect($viewName, 'codserie', 'series', 'codserie', $series);
        }
    }

    /**
     * Load data for view.
     * if it is the main view it assigns the date of the day to the modal form.
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);
        if ($viewName == $this->getMainViewName()) {
            $view->model->untilNextDate = $this->toolBox()->today();
        }
    }

    protected function generateDocsWhere(): array
    {
        $codes = $this->request->request->get('code', []);
        if (false === \is_array($codes) || empty($codes)) {
            return [];
        }

        $untilDate = $this->request->request->get('untilNextDate', \date('Y-m-d'));
        return [
            new DataBaseWhere('nextdate', $untilDate, '<='),
            new DataBaseWhere('termtype', DocRecurring::TERM_TYPE_MANUAL, '<>'),
            new DatabaseWhere('id', \implode(',', $codes) , 'IN'),
        ];
    }
}
