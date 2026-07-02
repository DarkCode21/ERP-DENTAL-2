<?php
/**
 * ListGabinete
 */
namespace FacturaScripts\Plugins\Dental\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListGabinete extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'dental';
        $data['title'] = 'cabinets';
        $data['icon'] = 'fas fa-hospital';
        return $data;
    }

    protected function createViews()
    {
        $this->createViewGabinetes();
    }

    protected function createViewGabinetes(string $viewName = 'ListGabinete')
    {
        $this->addView($viewName, 'Gabinete', 'cabinets', 'fas fa-hospital');
        $this->addOrderBy($viewName, ['codigo'], 'code');
        $this->addOrderBy($viewName, ['nombre'], 'name');
        $this->addSearchFields($viewName, ['codigo', 'nombre', 'descripcion']);
    }
}
