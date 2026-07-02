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
 * Controller to list the items in the DisciplinaryOffense model
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditDisciplinaryOffense extends EditController
{

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'DisciplinaryOffense';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'disciplinary-offense';
        $pagedata['icon'] = 'fa-solid fa-people-arrows';
        $pagedata['menu'] = 'rrhh';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }
}
