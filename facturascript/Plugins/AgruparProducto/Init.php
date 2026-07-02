<?php
/**
 * This file is part of AgruparProducto plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * AgruparProducto Copyright (C) 2022-2022 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\AgruparProducto;

use FacturaScripts\Core\Base\AjaxForms\SalesLineHTML;
use FacturaScripts\Core\Base\InitClass;

/**
 * Description of Init
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class Init extends InitClass
{

    public function init()
    {
        $this->loadExtension(new Extension\Controller\ListProducto());
        $this->loadExtension(new Extension\Controller\EditProducto());
        $this->loadExtension(new Extension\Model\Base\BusinessDocument());
        $this->loadExtension(new Extension\Model\Base\SalesDocumentLine());
        SalesLineHTML::addMod(new Mod\SalesLineHTMLMod());
    }

    public function update()
    {
    }
}
