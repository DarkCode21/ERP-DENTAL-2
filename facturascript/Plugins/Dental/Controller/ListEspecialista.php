<?php
/**
 * ListEspecialista
 */
namespace FacturaScripts\Plugins\Dental\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListEspecialista extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'dental';
        $data['title'] = 'specialists';
        $data['icon'] = 'fas fa-user-md';
        return $data;
    }

    protected function createViews()
    {
        $this->createViewEspecialistas();
    }

    protected function createViewEspecialistas(string $viewName = 'ListEspecialista')
    {
        $this->addView($viewName, 'Especialista', 'specialists', 'fas fa-user-md');
        $this->addOrderBy($viewName, ['nombre', 'apellidos'], 'name');
        $this->addOrderBy($viewName, ['numero_colegiado'], 'license');
        $this->addSearchFields($viewName, ['nombre', 'apellidos', 'numero_colegiado', 'email']);
    }
}
