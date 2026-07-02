<?php
/**
 * Copyright (C) 2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Etiquetas\Extension\Controller;

use Closure;
use FacturaScripts\Plugins\Etiquetas\Extension\Traits\CommonSalesPurchasesControllerTrait;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditAlbaranProveedor
{
    use CommonSalesPurchasesControllerTrait;

    public function createViews(): Closure
    {
        return function () {
            $viewName = 'Etiquetas';
            $this->addHtmlView($viewName, 'Tab/' . $viewName, 'AlbaranProveedor', 'tags', 'fas fa-barcode');
            $this->setSettings($viewName, 'card', false);
        };
    }
}
