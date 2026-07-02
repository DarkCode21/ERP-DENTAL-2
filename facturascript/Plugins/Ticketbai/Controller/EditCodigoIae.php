<?php
/**
 * Copyright (C) 2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Ticketbai\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * @author Alayn Gortazar Huete - Barnetik Koop <alayn@barnetik.com>
 */
class EditCodigoIae extends EditController
{
    public function getModelClassName(): string
    {
        return "CodigoIae";
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'code-iae';
        $data['icon'] = 'fa-solid fa-wallet';
        $data['showonmenu'] = false;
        return $data;
    }
}
