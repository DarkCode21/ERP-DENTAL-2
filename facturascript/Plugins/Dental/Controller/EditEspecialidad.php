<?php
/**
 * EditEspecialidad
 */
namespace FacturaScripts\Plugins\Dental\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

class EditEspecialidad extends EditController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'dental';
        $data['title'] = 'specialty';
        $data['icon'] = 'fas fa-tooth';
        return $data;
    }

    public function getModelClassName(): string
    {
        return 'Especialidad';
    }
}
