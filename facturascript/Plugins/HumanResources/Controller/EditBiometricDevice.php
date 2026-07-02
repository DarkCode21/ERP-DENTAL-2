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
 * Controler to edit Biometric Device.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditBiometricDevice extends EditController
{

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'BiometricDevice';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'device';
        $pagedata['icon'] = 'fa-solid fa-fingerprint';
        $pagedata['menu'] = 'rrhh';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    /**
     * Create the view to display.
     */
    protected function createViews() {
        parent::createViews();
        $this->loadTimeZones();
    }

    private function loadTimeZones()
    {
        $mvn = $this->getMainViewName();
        $column = $this->views[$mvn]->columnForField('timezone');
        if (isset($column)) {
            $column->widget->setValuesFromArray(\DateTimeZone::listIdentifiers());
        }
    }
}
