<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * Controler to edit Employee Pay Roll.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditEmployeePayRoll extends EditController
{

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'EmployeePayRoll';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['menu'] = 'rrhh';
        $pagedata['title'] = 'employee-salary';
        $pagedata['icon'] = 'fa-solid fa-money-bill-alt';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    /**
     * Create the view to display.
     */
    protected function createViews()
    {
        /// Add Views
        parent::createViews();
        $this->views['EditEmployeePayRoll']->setReadOnly(true);
        $this->setSettings('EditEmployeePayRoll', 'btnNew', false);
        $this->setTabsPosition('bottom');

        $this->addEditListView('EditEmployeePayRollSalary', 'EmployeePayRollSalary', 'payroll-salary');
        $this->views['EditEmployeePayRollSalary']->setInline(true);
    }

    /**
     * Loads the data to display.
     *
     * @param string   $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'EditEmployeePayRollSalary':
                $this->loadDataPayRollSalary($view);
                break;

            default:
                parent::loadData($viewName, $view);
                $view->disableColumn('code', true);
                break;
        }
    }

    /**
     *
     * @param BaseView $view
     */
    private function loadDataPayRollSalary(&$view)
    {
        $idsalary = $this->getViewModelValue('EditEmployeePayRoll', 'id');
        $where = [new DataBaseWhere('idemployeepayroll', $idsalary)];
        $view->loadData(false, $where, ['channel' => 'ASC', 'calculation' => 'ASC', 'id' => 'ASC']);
        $view->disableColumn('code', true);
        $view->disableColumn('employeepayroll', true);
        $view->disableColumn('employee', true);
    }
}
