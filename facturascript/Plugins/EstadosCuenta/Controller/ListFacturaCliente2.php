<?php

namespace FacturaScripts\Plugins\EstadosCuenta\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Plugins\EstadosCuenta\Model\Join\DetallesCuenta;

class ListFacturaCliente2 extends ListController {

    public function getPageData(): array 
    {
        $data = parent::getPageData();
        $data['showonmenu'] = false;
        return $data;
    }
    protected function createViews() {
        $this->createViewSales('ListDetallesCuenta', 'Join\DetallesCuenta', 'invoices');
    }
    protected function createViewSales(string $viewName, $modelName, $label) {
        parent::createViewSales($viewName, $modelName, $label);
        $this->addFilterCheckbox('ListDetallesCuenta', 'pagada', 'unpaid', 'pagada', '=', false);
        $this->addFilterCheckbox('ListDetallesCuenta', 'idasiento', 'invoice-without-acc-entry', 'idasiento', 'IS', null);
        $this->addButtonLockInvoice('ListDetallesCuenta');
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'btnDelete', false);
    }
}