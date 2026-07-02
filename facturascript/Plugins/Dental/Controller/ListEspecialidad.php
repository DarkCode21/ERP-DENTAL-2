<?php
/**
 * ListEspecialidad
 */
namespace FacturaScripts\Plugins\Dental\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListEspecialidad extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'dental';
        $data['title'] = 'specialties';
        $data['icon'] = 'fas fa-tooth';
        return $data;
    }

    protected function createViews()
    {
        $this->createViewEspecialidades();
    }

    protected function createViewEspecialidades(string $viewName = 'ListEspecialidad')
    {
        $this->addView($viewName, 'Especialidad', 'specialties', 'fas fa-tooth');
        $this->addOrderBy($viewName, ['nombre'], 'name');
        $this->addSearchFields($viewName, ['nombre', 'descripcion']);
    }
}
