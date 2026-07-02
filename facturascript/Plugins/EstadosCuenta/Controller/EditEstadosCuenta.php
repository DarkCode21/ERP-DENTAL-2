<?php

namespace FacturaScripts\Plugins\EstadosCuenta\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\EditController;

class EditEstadosCuenta extends EditController
{
    public function getModelClassName(): string
    {
        return 'EstadosCuenta';
    }
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'sales';
        $data['title'] = 'Estados de Cuenta';
        $data['icon'] = 'fas fa-calculator';        
        return $data;
    }
    protected function createInvoiceView($viewName)
    {
        $this->addListView($viewName, 'ReciboCliente', 'invoices');
    }
    protected function createViews()
    {
        parent::createViews();
        $this->createInvoiceView('ListDetallesCuenta');
        //$this->views['ListDetallesCuenta']->addFilterPeriod('ListDetallesCuenta', 'date', 'fecha');
        //$this->views['ListDetallesCuenta']->addFilterCheckbox('pagado', 'unpaid', '', '!=');
        $this->views['ListDetallesCuenta']->addOrderBy(['fecha'], 'date', 2);
        //$this->setSettings('ListDetallesCuenta', 'btnPrint', true);
        $this->setSettings('ListDetallesCuenta', 'btnNew', false);
        $this->setSettings('ListDetallesCuenta', 'btnDelete', false);
        $this->setSettings('EditEstadosCuenta', 'btnUndo', false);
        $this->setSettings('EditEstadosCuenta', 'btnDelete', false);
        $this->setSettings('EditEstadosCuenta', 'btnSave', false);
        $this->setTabsPosition('bottom');
    }
    protected function editAction()
    {
        $return = parent::editAction();
        if ($return && $this->active === $this->getMainViewName()) {
            $this->updateContact($this->views[$this->active]->model);
        }
        return $return;
    }

    protected function loadData($viewName, $view)
    {
        $codcliente = $this->getViewModelValue('EditEstadosCuenta', 'codcliente');
        $where = [
            new DataBaseWhere('codcliente', $codcliente),
            new DataBaseWhere('diferencia', 1, '>'),
            new DataBaseWhere('pagado', 0, '=')
        ];

        switch ($viewName) {
            case 'ListDetallesCuenta':
                $this->dataBase->exec('UPDATE recibospagoscli SET diferencia=DATEDIFF(NOW(), vencimiento) where vencimiento is NOT null');
                //$this->toolBox()->log()->info('Dias Vencidos Actualizados');
                $view->loadData('', $where);
                break;
            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}