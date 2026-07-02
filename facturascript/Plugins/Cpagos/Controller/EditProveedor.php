<?php

namespace FacturaScripts\Plugins\Cpagos\Controller;

use FacturaScripts\Core\Controller\EditProveedor as ParentEditProveedor;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

class EditProveedor extends ParentEditProveedor{
    protected function createViews() {
        parent::createViews();
        
        $this->addListView('ListCpago', 'Cpago', 'Contraseñas', 'fas fa-money-check-alt');
    }
    protected function loadData($viewName, $view) {
        switch ($viewName) {
            case 'ListCpago':
                $codproveedor = $this->getViewModelValue('EditProveedor', 'codproveedor');
                $where = [new DataBaseWhere('codproveedor', $codproveedor)];
                $view->loadData('', $where);
                break;

                default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}
