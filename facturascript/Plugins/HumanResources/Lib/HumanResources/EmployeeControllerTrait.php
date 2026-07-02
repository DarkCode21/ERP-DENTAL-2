<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\HumanResources;

/**
 * Auxiliar Method for edit Employee.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
trait EmployeeControllerTrait
{

    protected function createViewEdit($viewName, $modelName, $viewTitle, $viewIcon, $disableColumns = ['code', 'employee'])
    {
        $this->addEditListView($viewName, $modelName, $viewTitle, $viewIcon);
        $this->views[$viewName]->setInLine(false);

        foreach ($disableColumns as $keyColumn) {
            $this->views[$viewName]->disableColumn($keyColumn, true);
        }
    }

    protected function createViewList($viewName, $modelName, $viewTitle, $viewIcon, $disableColumns = ['code', 'employee'])
    {
        $this->addListView($viewName, $modelName, $viewTitle, $viewIcon);
        foreach ($disableColumns as $keyColumn) {
            $this->views[$viewName]->disableColumn($keyColumn, true);
        }
    }

    protected function orderByForView($viewName)
    {
        switch ($viewName) {
            case 'EditEmployeeSalary':
                return ['channel' => 'ASC', 'calculation' => 'ASC', 'idsalaryconcept' => 'ASC'];

            case 'EditEmployeeContract':
            case 'ListEmployeeHoliday':
            case 'EditEmployeeLeave':
            case 'EditEmployeeSanction':
            case 'EditEmployeeWorkShift':
                return ['startdate' => 'DESC'];

            default:
                return [];
        }
    }
}
