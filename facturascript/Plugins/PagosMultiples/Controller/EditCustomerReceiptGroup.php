<?php
/**
 * This file is part of PagosMultiples plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * PagosMultiples Copyright (C) 2020-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\PagosMultiples\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\AgentSettlement;
use FacturaScripts\Dinamic\Model\CustomerReceiptGroup;
use FacturaScripts\Plugins\PagosMultiples\Lib\PagosMultiples\EditPaymentReceiptGroup;

/**
 * Controller to list the items in the CustomerReceiptGroup model
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditCustomerReceiptGroup extends EditPaymentReceiptGroup
{

    protected const VIEW_ADD = 'ListReciboCliente-add';
    protected const VIEW_AGENT = 'EditAgentSettlement';
    protected const VIEW_BANKCHECK = 'ListCustomerBankCheck';
    protected const VIEW_LIST = 'ListReciboCliente';
    protected const VIEW_NOTE = 'EditCustomerReceiptGroupNote';

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'CustomerReceiptGroup';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'multiple-charge';
        $pagedata['icon'] = 'fas fa-coins';
        $pagedata['menu'] = 'sales';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    /**
     * Create the view to display.
     *
     * Disable company column from main view, if there is only one company.
     * Set tabs to bottom position.
     */
    protected function createViews()
    {
        parent::createViews();
        $this->createViewAgentSettlement();
        $this->createViewBankCheck();
        $this->createViewAccounting();
    }

    /**
     * Add receipts pending list.
     *
     * @param string $viewName
     */
    protected function createViewReceiptsAdd()
    {
        parent::createViewReceiptsAdd();

        $viewName = static::VIEW_ADD;
        $this->views[$viewName]->addOrderBy(['recibospagoscli.codcliente'], 'customer');
        $this->views[$viewName]->addFilterAutocomplete('codcliente', 'customer', 'recibos.codcliente', 'clientes', 'codcliente', 'razonsocial');
        $this->views[$viewName]->addFilterAutocomplete('codagente', 'agent', 'facturas.codagente', 'agentes', 'codagente', 'nombre');
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
            case 'cash-calculate':
                $this->setTemplate(false);
                $this->response->setContent(json_encode($this->cashCalculateAction()));
                return false;

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
        switch ($viewName) {
            case self::VIEW_AGENT:
                $idmultiple = $this->getViewModelValue($mainViewName, 'id');
                $idagent = $this->getViewModelValue($mainViewName, 'idagent');
                $this->loadDataAgent($idmultiple, $idagent);
                $view->count = -1;
                break;

            case self::VIEW_BANKCHECK:
                $idmultiple = $this->getViewModelValue($mainViewName, 'id');
                $status = $this->getViewModelValue($mainViewName, 'status');
                $this->loadDataBankCheck($status, $idmultiple);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    /**
     * Calculare cash for AJAX call.
     *
     * @return array
     */
    private function cashCalculateAction(): array
    {
        $totalGroup = $this->request->get('total', 0.00);
        $data = $this->request->get('cash', []);
        $data['bankchecks'] = $this->request->get('checks', 0.00);
        $data['total_diets'] = $this->request->get('diets', 0.00);
        $settlement = new AgentSettlement($data);

        $settlement->total = $settlement->calculateSettlement();
        $settlement->difference = round(($settlement->total + $settlement->total_diets + $settlement->bankchecks) - $totalGroup, FS_NF0);
        return [
            'total' => $settlement->total,
            'difference' => $settlement->difference,
        ];
    }

    /**
     * Add agent settlement view.
     *
     * @param string $viewName
     */
    private function createViewAgentSettlement(string $viewName = self::VIEW_AGENT)
    {
        $this->addEditView($viewName, 'AgentSettlement', 'settlement', 'fas fa-cash-register');
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);
    }

    /**
     * Add bank check view.
     *
     * @param string $viewName
     */
    private function createViewBankCheck(string $viewName = self::VIEW_BANKCHECK)
    {
        $this->addListView($viewName, 'CustomerBankCheck', 'bank-checks', 'fas fa-money-check');
    }

    /**
     * Load data for agent settlement tab.
     *
     * @param int $idmultiple
     * @param int $idagent
     * @param string $viewName
     */
    private function loadDataAgent(int $idmultiple, $idagent, string $viewName = self::VIEW_AGENT)
    {
        if (empty($idagent)) {
            $this->setSettings($viewName, 'active',false);
            return;
        }
        $this->views[$viewName]->loadData($idmultiple);
        $readOnly = $this->views[$viewName]->model->automatic ? 'true' : 'false';
        $this->views[$viewName]->disableColumn('total', false, $readOnly);
    }

    /**
     * Load data for custom bank checks.
     *
     * @param int $status
     * @param int $idmultiple
     */
    private function loadDataBankCheck(int $status, int $idmultiple, string $viewName = self::VIEW_BANKCHECK)
    {
        if ($status != CustomerReceiptGroup::STATUS_PENDING) {
            $this->setSettings($viewName, 'btnDelete', false);
            $this->setSettings($viewName, 'btnNew', false);
        }

        $where = [new DataBaseWhere('idmultiple', $idmultiple)];
        $this->views[$viewName]->loadData('', $where);
    }
}