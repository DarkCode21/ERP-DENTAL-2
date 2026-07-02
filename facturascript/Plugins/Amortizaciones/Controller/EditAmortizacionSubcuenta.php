<?php
/**
 * This file is part of Amortizaciones plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Amortizaciones  Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\Amortizaciones\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * Controler to edit Amortize table concept.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditAmortizacionSubcuenta extends EditController
{

    /**
     * Returns the class name of the model to use in the editView.
     */
    public function getModelClassName(): string
    {
        return 'AmortizacionSubcuenta';
    }

    /**
     * Return the basic data for this page.
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'accounts';
        $pagedata['icon'] = 'fas fa-list';
        $pagedata['menu'] = 'accounting';
        return $pagedata;
    }
}