<?php

namespace FacturaScripts\Plugins\Cpagos\Controller;

use FacturaScripts\Core\Controller\ListFacturaProveedor as ParentListFacturaProveedor;

class ListFacturaProveedor extends ParentListFacturaProveedor {

    protected function createViews() {
        parent::createViews();
        $this->createViewCpagos();
    }
    //agregar pestaña de contraseñas a lista de facturas
    protected function createViewCpagos(string $viewName = 'ListCpago') {
        $this->addView($viewName, 'Cpago', 'Contraseñas', 'fas fa-money-check-alt');
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
        //Filtro por proveedor
        $this->addFilterAutocomplete($viewName, 'codproveedor', 'supplier', 'codproveedor', 'Proveedor', 'codproveedor', 'nombre');
    }
}