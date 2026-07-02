<?php
/**
 * Copyright (C) 2022-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditProductoLote extends EditController
{
    public function getModelClassName(): string
    {
        return 'ProductoLote';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'warehouse';
        $data['title'] = 'batch-serial-number';
        $data['icon'] = 'fa-solid fa-fingerprint';
        return $data;
    }

    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        // ocultamos los botones de la pestaña principal
        $mvn = $this->getMainViewName();
        $this->setSettings($mvn, 'btnNew', false);
        $this->setSettings($mvn, 'btnUndo', false);
        $this->setSettings($mvn, 'btnSave', false);

        $this->createViewsDocument('ListLoteMovimiento', 'movements', 'fa-solid fa-truck-loading');
        $this->createViewsDocument('ListLoteMovimiento-2', 'delivery-notes', 'fa-solid fa-dolly-flatbed');
        $this->createViewsDocument('ListLoteMovimiento-3', 'invoices', 'fa-solid fa-file-invoice-dollar');
        $this->createViewsDocument('ListLoteMovimiento-4', 'transfers', 'fa-solid fa-exchange-alt');
        $this->createViewsDocument('ListLoteMovimiento-5', 'stock-counts', 'fa-solid fa-scroll');
    }

    protected function createViewsDocument(string $viewName, string $title, string $icon): void
    {
        $this->addListView($viewName, 'ProductoLoteMovimiento', $title, $icon)
            ->addSearchFields(['documento'])
            ->addOrderBy(['docfecha', 'id'], 'date', 2)
            ->addOrderBy(['cantidad'], 'quantity')
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false)
            ->setSettings('checkBoxes', false);

        if ('ListLoteMovimiento' === $viewName) {
            $this->tab($viewName)
                ->disableColumn('quantity', true)
                ->disableColumn('invoiced', true)
                ->disableColumn('returned', true);
            return;
        }

        if ('ListLoteMovimiento-2' === $viewName) {
            $models = [
                ['code' => 'AlbaranCliente', 'description' => Tools::lang()->trans('customer-delivery-notes')],
                ['code' => 'AlbaranProveedor', 'description' => Tools::lang()->trans('supplier-delivery-notes')],
            ];
            $this->listView($viewName)
                ->addFilterSelect('docmodel', 'document', 'docmodel', $models)
                ->disableColumn('movement', true)
                ->disableColumn('doc-model', true)
                ->disableColumn('doc-id', true)
                ->addColor('docmodel', 'AlbaranProveedor', 'success', 'supplier-delivery-notes')
                ->addColor('docmodel', 'AlbaranCliente', 'danger', 'customer-delivery-notes');
            return;
        }

        if ('ListLoteMovimiento-3' === $viewName) {
            $models = [
                ['code' => 'FacturaCliente', 'description' => Tools::lang()->trans('sales-invoice')],
                ['code' => 'FacturaProveedor', 'description' => Tools::lang()->trans('supplier-invoices')],
            ];
            $this->listView($viewName)
                ->addFilterSelect('docmodel', 'document', 'docmodel', $models)
                ->disableColumn('movement', true)
                ->disableColumn('doc-model', true)
                ->disableColumn('doc-id', true)
                ->disableColumn('invoiced', true)
                ->addColor('docmodel', 'FacturaProveedor', 'success', 'supplier-invoices')
                ->addColor('docmodel', 'FacturaCliente', 'danger', 'sales-invoice');
            return;
        }

        if ('ListLoteMovimiento-4' === $viewName) {
            $this->tab($viewName)
                ->disableColumn('invoiced', true)
                ->disableColumn('returned', true)
                ->disableColumn('movement', true)
                ->disableColumn('doc-model', true)
                ->disableColumn('doc-id', true);
            return;
        }

        if ('ListLoteMovimiento-5' === $viewName) {
            $this->tab($viewName)
                ->disableColumn('invoiced', true)
                ->disableColumn('returned', true)
                ->disableColumn('movement', true)
                ->disableColumn('doc-model', true)
                ->disableColumn('doc-id', true);
        }
    }

    protected function loadData($viewName, $view)
    {
        $mvn = $this->getMainViewName();
        switch ($viewName) {
            case 'ListLoteMovimiento':
                $where = [new DataBaseWhere('idlote', $this->getViewModelValue($mvn, 'idlote')),];
                $view->loadData('', $where);
                break;

            case 'ListLoteMovimiento-2':
                $where = [
                    new DataBaseWhere('idlote', $this->getViewModelValue($mvn, 'idlote')),
                    new DataBaseWhere('docmodel', 'AlbaranCliente,AlbaranProveedor', 'IN')
                ];
                $view->loadData('', $where);
                $view->setSettings('active', $view->model->count($where) > 0);
                break;

            case 'ListLoteMovimiento-3':
                $where = [
                    new DataBaseWhere('idlote', $this->getViewModelValue($mvn, 'idlote')),
                    new DataBaseWhere('docmodel', 'FacturaCliente,FacturaProveedor', 'IN')
                ];
                $view->loadData('', $where);
                $view->setSettings('active', $view->model->count($where) > 0);
                break;

            case 'ListLoteMovimiento-4':
                $where = [
                    new DataBaseWhere('idlote', $this->getViewModelValue($mvn, 'idlote')),
                    new DataBaseWhere('docmodel', 'TransferenciaStock')
                ];
                $view->loadData('', $where);
                $view->setSettings('active', $view->model->count($where) > 0);
                break;

            case 'ListLoteMovimiento-5':
                $where = [
                    new DataBaseWhere('idlote', $this->getViewModelValue($mvn, 'idlote')),
                    new DataBaseWhere('docmodel', 'ConteoStock')
                ];
                $view->loadData('', $where);
                $view->setSettings('active', $view->model->count($where) > 0);
                break;

            case $mvn:
                parent::loadData($viewName, $view);
                break;
        }
    }
}
