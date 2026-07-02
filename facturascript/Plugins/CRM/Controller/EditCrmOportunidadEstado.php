<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CRM\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * Description of EditCrmOportunidadEstado
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditCrmOportunidadEstado extends EditController
{

    public function getModelClassName(): string
    {
        return 'CrmOportunidadEstado';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'crm';
        $data['title'] = 'status';
        $data['icon'] = 'fas fa-tags';
        return $data;
    }
}
