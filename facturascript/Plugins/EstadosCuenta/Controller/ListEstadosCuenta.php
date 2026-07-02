<?php

namespace FacturaScripts\Plugins\EstadosCuenta\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListEstadosCuenta extends ListController
{

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'sales';
        $data['title'] = 'Estados de Cuenta';
        $data['icon'] = 'fas fa-calculator';
        return $data;
    }

    protected function createViews()
    {
        $this->createViewCustomers2();

    }

    protected function createViewCustomers2($viewName = 'ListEstadosCuenta')
    {
        $this->addView($viewName, 'EstadosCuenta', 'customers', 'fas fa-users');
        $this->addSearchFields($viewName, ['cifnif', 'nombre', 'razonsocial']);
        //$this->addOrderBy($viewName, ['riesgoalcanzado'], 'current-risk');
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnPrint', false);
        $this->setSettings($viewName, 'checkBoxes', false);
    }
}