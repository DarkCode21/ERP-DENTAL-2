<?php
/**
 * Copyright (C) 2020-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TarifasAvanzadas\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * Description of EditDescuentoCliente
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditDescuentoCliente extends EditController
{
    public function getModelClassName(): string
    {
        return 'DescuentoCliente';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'sales';
        $data['title'] = 'discount';
        $data['icon'] = 'fas fa-tag';
        return $data;
    }
}
