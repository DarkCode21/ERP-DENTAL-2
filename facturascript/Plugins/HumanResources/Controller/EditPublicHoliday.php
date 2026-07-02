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
 * Controler to edit PublicHoliday.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditPublicHoliday extends EditController
{

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'PublicHoliday';
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
        $pagedata['title'] = 'public-holiday';
        $pagedata['icon'] = 'fa-solid fa-calendar-times';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }
}
