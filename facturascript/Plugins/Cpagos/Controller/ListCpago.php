<?php

namespace FacturaScripts\Plugins\Cpagos\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListCpago extends ListController {

    public function getPageData(): array 
    {
        $data = parent::getPageData();
        $data['menu'] = 'purchases';
        $data['title'] = 'Contraseñas';
        $data['icon'] = 'fas fa-money-check-alt';
        return $data;
    }

    protected function CreateViews() {
        $this->createViewCpago();

    }

    protected function createViewCpago($viewName = 'ListCpago') {
        $this->addView($viewName, 'Cpago');
        //Para el buscador por numero de factura
        $this->addSearchFields($viewName, ['invoice', 'numproveedor']);
        //Para el buscador por Proveedor
        $this->addSearchFields($viewName, ['supplier', 'codproveedor']);
        $this->addSearchFields($viewName, ['description', 'description']);
        //Ordenar por fecha
        $this->addOrderBy($viewName, ['creationdate'], 'creation-date', 2);
        //Ordenar por fecha de pago
        $this->addOrderBy($viewName, ['paymentdate'], 'Fecha de Pago', 2);
        //Ordenado por total
        $this->addOrderBy($viewName, ['total'], 'total');

        //Filtros
        //Pagado
        $this->addFilterCheckbox($viewName, 'verified', 'Pagado', 'verified', 'TRUE');
        //No pagado
        $this->addFilterCheckbox($viewName, 'verified', 'No Pagado', 'verified', 'IS NOT');

        //filtro por fecha de pago
        $this->addFilterPeriod($viewName, 'paymentdate', 'period', 'Fecha de pago');
        //Filtro por Usuario
      //  $user = $this->codeModel->all('user', 'nick', 'nick');
       // $this->addFilterSelect($viewName, 'user', 'user', 'nick', $user);

        //Filtro por proveedor
        $this->addFilterAutocomplete($viewName, 'codproveedor', 'supplier', 'codproveedor', 'Proveedor', 'codproveedor', 'nombre');
    }

}
