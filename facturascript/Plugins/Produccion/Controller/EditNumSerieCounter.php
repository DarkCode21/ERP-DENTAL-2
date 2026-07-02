<?php
/**
 * This file is part of the Produccion plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 * All Rights Reserved.
 */
namespace FacturaScripts\Plugins\Produccion\Controller;

use FacturaScripts\Dinamic\Lib\ExtendedController\EditController;

/**
 * Description of EditNumSerieCounter
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class EditNumSerieCounter extends EditController
{
    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'NumSerieCounter';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'admin';
        $pageData['title'] = 'numserie-counter';
        $pageData['icon'] = 'fa-solid fa-code';
        return $pageData;
    }
}
