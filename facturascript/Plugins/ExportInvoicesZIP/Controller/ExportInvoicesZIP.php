<?php

namespace FacturaScripts\Plugins\ExportInvoicesZIP\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Model\FacturaCliente;
use FacturaScripts\Core\Model\FacturaProveedor;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

class ExportInvoicesZIP extends Controller
{
    public $facturas = [];

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'reports';
        $pageData['title'] = 'export-invoices';
        $pageData['icon'] = 'fas fa-file-archive';
        return $pageData;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        
        $action = $this->request->get('action', '');
        
        if ($action === 'process') {
            $this->processAction();
        } else {
            $this->setTemplate('ExportInvoicesZIP');
        }
    }

    private function processAction(): void
    {
        $this->setTemplate('ExportInvoicesZIP');
        
        $year = (int)$this->request->request->get('year');
        $quarter = (int)$this->request->request->get('quarter');
        $invoiceType = $this->request->request->get('invoiceType', 'all');

        // Calcular fechas del trimestre
        $startMonth = ($quarter * 3) - 2;
        $endMonth = $quarter * 3;
        
        $startDate = date('Y-m-d', strtotime("$year-$startMonth-01"));
        $endDate = date('Y-m-t', strtotime("$year-$endMonth-01"));

        // Obtener facturas según el tipo seleccionado
        if ($invoiceType === 'all' || $invoiceType === 'sales') {
            $this->facturas = array_merge(
                $this->facturas, 
                $this->getInvoices($startDate, $endDate, 'sales')
            );
        }

        if ($invoiceType === 'all' || $invoiceType === 'purchases') {
            $this->facturas = array_merge(
                $this->facturas, 
                $this->getInvoices($startDate, $endDate, 'purchases')
            );
        }
    }

    private function getInvoices(string $startDate, string $endDate, string $type): array
    {
        $model = ($type === 'sales') ? new FacturaCliente() : new FacturaProveedor();
        $where = [
            new DataBaseWhere('fecha', $startDate, '>='),
            new DataBaseWhere('fecha', $endDate, '<=')
        ];

        return $model->all($where, ['fecha' => 'ASC'], 0, 0);
    }
}