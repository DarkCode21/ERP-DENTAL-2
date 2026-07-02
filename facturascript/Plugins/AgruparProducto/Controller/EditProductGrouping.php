<?php
/**
 * This file is part of AgruparProducto plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * AgruparProducto Copyright (C) 2022-2022 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\AgruparProducto\Controller;

use FacturaScripts\Dinamic\Lib\ExtendedController\EditController;

/**
 * Class to edit the product grouping items in the agent edit view,
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditProductGrouping extends EditController
{
    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'ProductGrouping';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'product-group';
        $pagedata['icon'] = 'fas fa-box';
        $pagedata['menu'] = 'warehouse';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }
}
