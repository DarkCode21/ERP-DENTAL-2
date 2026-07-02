<?php
/**
 * This file is part of PagosMultiples plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * PagosMultiples  Copyright (C) 2020-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\PagosMultiples\Lib\PagosMultiples;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Plugins\PagosMultiples\Lib\PagosMultiples\PaymentReceiptGroupActions;
use FacturaScripts\Plugins\PagosMultiples\Model\Base\PaymentReceiptGroup;

/**
 * Edit Controller class base for multiple payment of receipts.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
abstract class EditPaymentReceiptGroup extends EditController
{

    protected const VIEW_ACCOUNTING = 'ListAccountingDetail';

    // defined in child class
    protected const VIEW_ADD = '';
    protected const VIEW_LIST = '';
    protected const VIEW_NOTE = '';

    /**
     * Create the view to display.
     *
     * Disable company column from main view, if there is only one company.
     * Set tabs to bottom position.
     */
    protected function createViews()
    {
        parent::createViews();
        $this->createViewReceipts();
        $this->createViewReceiptsAdd();
        $this->createViewNotes();
        $this->setTabsPosition('bottom');

        if ($this->empresa->count() < 2) {
            $this->views[$this->getMainViewName()]->disableColumn('company');
        }
    }

    /**
     * Add accounting entry view.
     * Load view data.
     *
     * @param string $viewName
     */
    protected function createViewAccounting()
    {
        $this->addListView(static::VIEW_ACCOUNTING, 'Join\AccountingDetail', 'accounting-entry', 'fas fa-balance-scale');
        $this->setSettings(static::VIEW_ACCOUNTING, 'btnDelete', false);
        $this->setSettings(static::VIEW_ACCOUNTING, 'btnNew', false);
    }

    /**
     * Add notes view.
     *
     * @param string $viewName
     */
    protected function createViewNotes()
    {
        $this->addEditView(static::VIEW_NOTE, $this->getModelClassName(), 'observations', 'fas fa-sticky-note');
        $this->setSettings(static::VIEW_NOTE, 'btnDelete', false);
    }

    /**
     * Add receipts selected list.
     */
    protected function createViewReceipts()
    {
        $this->createViewReceiptsAddView(static::VIEW_LIST, 'receipts', 'fas fa-file-invoice-dollar');

        $view = $this->views[static::VIEW_LIST];
        $view->addSearchFields(['codigofactura', 'observaciones']);
        $view->addOrderBy(['codigofactura'], 'code');
        $view->addOrderBy(['vencimiento'], 'expiration');
        $view->addOrderBy(['fecha'], 'date');
    }

    /**
     * Add receipts pending list.
     */
    protected function createViewReceiptsAdd()
    {
        $this->createViewReceiptsAddView(static::VIEW_ADD, 'add', 'fas fa-folder-plus');

        $view = $this->views[static::VIEW_ADD];
        $view->addSearchFields(['facturas.codigo', 'facturas.observaciones', 'subject.nombre', 'subject.razonsocial']);
        $view->addOrderBy(['recibos.fecha'], 'date', 2);
        $view->addOrderBy(['recibos.importe'], 'amount');
        $view->addOrderBy(['recibos.vencimiento'], 'expiration');

        $view->addFilterPeriod('vencimiento', 'expiration', 'recibos.vencimiento');
        $view->addFilterNumber('total', 'amount', 'recibos.importe', '>=');
        $view->addFilterNumber('total2', 'amount', 'recibos.importe', '<=');

        $paymentMethods = $this->codeModel->all('formaspago', 'codpago', 'descripcion');
        $view->addFilterSelect('codpago', 'payment-method', 'recibos.codpago', $paymentMethods);

        $this->addButton(static::VIEW_ADD, [
            'action' => 'add-receipts',
            'color' => 'success',
            'icon' => 'fas fa-folder-plus',
            'label' => 'add'
        ]);
    }

    /**
     * Run the actions that alter data before reading it.
     *
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'add-receipts':
            case 'charged-receipts':
            case 'remove-receipts':
            case 'reopen-receipts':
                $id = $this->request->query->get('code');
                if (empty($id)) {
                    return true;
                }
                $data = $this->request->request->all();
                $data['nick'] = $this->user->nick;
                $paymentActions = new PaymentReceiptGroupActions($this->getModelClassName(), $id, $data);
                $paymentActions->exec($action);
                return true;

            default:
                return parent::execPreviousAction($action);
        }
    }

    /**
     * Load view data procedure
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $mainViewName = $this->getMainViewName();
        if ($viewName === $mainViewName) {
            parent::loadData($viewName, $view);
            if ($view->count > 0) {
                $this->addActionButton();
            }
            return;
        }

        $idmultiple = $this->getViewModelValue($mainViewName, 'id');
        $status = $this->getViewModelValue($mainViewName, 'status');
        switch ($viewName) {
            case static::VIEW_ACCOUNTING:
                $identry = $this->getViewModelValue($mainViewName, 'identry');
                $this->loadDataAccounting($identry, $viewName);
                break;

            case static::VIEW_NOTE:
                $view->loadData($idmultiple);
                $view->count = empty($view->model->notes) ? -1 : 1;
                break;

            case static::VIEW_LIST:
                $where = [new DataBaseWhere('idmultiple', $idmultiple)];
                $view->loadData('', $where);
                break;

            case static::VIEW_ADD:
                $this->loadDataReceiptAdd(
                    $status,
                    $this->getViewModelValue($mainViewName, 'idcompany'),
                    $this->getViewModelValue($mainViewName, 'idcurrency'),
                    $this->getViewModelValue($mainViewName, 'idserie') ?? '',
                    $viewName
                );
                break;
        }
    }

    /**
     * Add action button depending on the state of the grouping.
     */
    private function addActionButton()
    {
        $mainViewName = $this->getMainViewName();
        $status = $this->getViewModelValue($mainViewName, 'status');
        switch ($status) {
            case PaymentReceiptGroup::STATUS_PENDING:
                $this->addButton($mainViewName, [
                    'action' => 'charged-receipts',
                    'color' => 'info',
                    'icon' => 'fas fa-check-square',
                    'label' => 'approve',
                    'confirm' => 'true',
                ]);

                $this->addButton(static::VIEW_LIST, [
                    'action' => 'remove-receipts',
                    'color' => 'danger',
                    'icon' => 'fas fa-folder-minus',
                    'label' => 'remove-from-list'
                ]);
                break;

            case PaymentReceiptGroup::STATUS_CHARGED:
                $this->addButton($mainViewName, [
                    'action' => 'reopen-receipts',
                    'color' => 'warning',
                    'icon' => 'fas fa-undo',
                    'label' => 're-open',
                    'confirm' => 'true',
                ]);
                break;
        }
    }

    /**
     *
     * @param string $viewName
     * @param string $title
     * @param string $icon
     */
    private function createViewReceiptsAddView(string $viewName, string $title, string $icon)
    {
        $modelClass =  $viewName === static::VIEW_ADD
            ? 'Join\\'. $this->getModel()->getReceipt()->modelClassName() . 'Add'
            : $this->getModel()->getReceipt()->modelClassName();
        $this->addListView($viewName, $modelClass, $title, $icon);
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);
    }

    /**
     * Load data for accounting tab.
     *
     * @param int $identry
     * @param string $viewName
     */
    private function loadDataAccounting($identry, string $viewName)
    {
        if (empty($identry)) {
            $this->setSettings($viewName, 'active', false);
            return;
        }

        $where = [new DataBaseWhere('partidas.idasiento', $identry)];
        $this->views[$viewName]->loadData('', $where, ['idpartida' => 'ASC']);
    }

    /**
     *
     * @param int $status
     * @param int $idcompany
     * @param string $idcurrency
     * @param string $idserie
     * @param string $viewName
     */
    private function loadDataReceiptAdd(int $status, int $idcompany, string $idcurrency, string $idserie, string $viewName)
    {
        if ($status != PaymentReceiptGroup::STATUS_PENDING) {
            $this->setSettings($viewName, 'active', false);
            return;
        }

        $where = [
            new DataBaseWhere('recibos.idmultiple', null, 'IS'),
            new DataBaseWhere('recibos.pagado', false),
            new DataBaseWhere('recibos.coddivisa', $idcurrency),
            new DataBaseWhere('recibos.idempresa', $idcompany),
        ];

        if (false === empty($idserie)) {
            $where[] = new DataBaseWhere('facturas.codserie', $idserie);
        }
        $this->views[$viewName]->loadData('', $where);
    }
}
