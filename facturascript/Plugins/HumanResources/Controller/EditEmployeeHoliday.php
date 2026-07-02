<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * Controler to edit Employee Holiday.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditEmployeeHoliday extends EditController
{

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'EmployeeHoliday';
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
        $pagedata['title'] = 'holidays';
        $pagedata['icon'] = 'fa-solid fa-sun';
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
        $view->disableColumn('code', true);  // Force disable PK column
    }
}
