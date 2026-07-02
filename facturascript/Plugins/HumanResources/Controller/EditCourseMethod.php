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
 * Controller to edit Course Method.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditCourseMethod extends EditController
{

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'CourseMethod';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'method';
        $pagedata['icon'] = 'fa-solid fa-person-dots-from-line';
        $pagedata['menu'] = 'rrhh';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }
}
