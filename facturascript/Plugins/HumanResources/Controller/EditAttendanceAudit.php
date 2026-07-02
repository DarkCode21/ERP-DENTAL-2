<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Controller;

use FacturaScripts\Dinamic\Lib\ExtendedController\EditController;

/**
 * Controller to show one attendance audit log.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditAttendanceAudit extends EditController
{

    /**
     * Returns the class name of the model to use in the editView.
     */
    public function getModelClassName(): string
    {
        return 'AttendanceAudit';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'audit';
        $pagedata['icon'] = 'fa-solid fa-file-alt';
        $pagedata['menu'] = 'rrhh';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    /**
     * Create the view to display.
     */
    protected function createViews()
    {
        parent::createViews();
        $this->views[$this->getMainViewName()]->setReadOnly(true)
            ->setSettings('btnNew', false)
            ->setSettings('btnOptions', false);
    }
}