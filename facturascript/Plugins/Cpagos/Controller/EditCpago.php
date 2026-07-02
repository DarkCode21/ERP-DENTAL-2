<?php

namespace FacturaScripts\Plugins\Cpagos\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\FacturaProveedor;

class EditCpago extends EditController {

    public function getModelClassName(): string 
    {
        return 'Cpago';
    }
    public function getPageData(): array 
    {
        $data = parent::getPageData();
        $data['menu'] = 'purchases';
        $data['title'] = 'Contraseñas';
        $data['icon'] = 'fas fa-money-check-alt';
        return $data;
    }
    protected function saveInsert(array $values = [])
    {
        $facturaproveedor = new FacturaProveedor();
        if ($facturaproveedor->loadFromCode($this->idfactura)) {
            $this->linestotal = $this->linestotal + $facturaproveedor->total;
        }
        return parent::saveInsert($values);
    }
    protected function createViews() {
        parent::createViews();
        $this->createViewsLineas();
        $this->setTabsPosition('bottom');
    }
    protected function loadData($viewName, $view) {
        switch ($viewName) {
            case 'ListCpago':
                $codproveedor = $this->getViewModelValue('EditCpago', 'codproveedor');
                $where = [new DataBaseWhere('codproveedor', $codproveedor)];
                $view->loadData('', $where);
                break;
            case 'EditCpago':
                parent::loadData($viewName, $view);
                if (!$this->views[$this->active]->model->exists()) {
                    $this->views[$this->active]->model->user = $this->user->nick;
                }
                break;
            case 'EditCpagoLinea':
                $where = [new DataBaseWhere('idcpago', $this->getViewModelValue('EditCpago', 'idcpago'))];
                $view->loadData('', $where);
                break;
            default:
            parent::loadData($viewName, $view);
            break;
        }
    }
    protected function createViewsLineas(string $viewName = 'EditCpagoLinea')
    {
        $this->addEditListView($viewName, 'ContraLinea', 'Factura');
        $this->views[$viewName]->setInLine(true);
    }
}