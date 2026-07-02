<?php
/**
 * EditGabinete
 */
namespace FacturaScripts\Plugins\Dental\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

class EditGabinete extends EditController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'dental';
        $data['title'] = 'cabinet';
        $data['icon'] = 'fas fa-hospital';
        return $data;
    }

    public function getModelClassName(): string
    {
        return 'Gabinete';
    }
}
