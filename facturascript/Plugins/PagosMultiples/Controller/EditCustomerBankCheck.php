<?php
/**
 * This file is part of PagosMultiples plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * PagosMultiples  Copyright (C) 2020-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\PagosMultiples\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CustomerBankCheck;
use FacturaScripts\Plugins\PagosMultiples\Lib\Accounting\BankCheckToAccounting;

/**
 * Controller to edit the items in the CustomerBankCheck model
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class EditCustomerBankCheck extends EditController
{

    protected const VIEW_ACCOUNTING = 'ListAccountingDetail';

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'CustomerBankCheck';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'bank-check';
        $pagedata['icon'] = 'fas fa-money-check';
        $pagedata['menu'] = 'sales';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    /**
     * Run the actions that alter data before reading it.
     *
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction($action) {
        switch ($action) {
            case 'charged-bankcheck':
                $this->chargedBankCheckAction();
                return true;

            case 'refound-bankcheck':
                $this->refundBankCheckAction();
                return true;

            default:
                return parent::execPreviousAction($action);
        }
    }

    /**
     * Loads the data to display.
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view) {
        $mainViewName = $this->getMainViewName();
        if ($viewName === $mainViewName) {
            parent::loadData($viewName, $view);
            $this->addAccountingActions();
            return;
        }

        switch ($viewName) {
            case static::VIEW_ACCOUNTING:
                $identry = $this->getViewModelValue($mainViewName, 'identry');
                $this->loadDataAccounting($identry, $viewName);
                break;
        }
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
        $this->createViewAccounting();
        $this->setTabsPosition('bottom');
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
     * Create accounting entry.
     *
     * @param CustomerBankCheck $bankCheck
     * @param bool $refund
     */
    private function accountingBankCheck(CustomerBankCheck $bankCheck, bool $refund)
    {
        $accounting = new BankCheckToAccounting();
        if (false === $accounting->generateAccounting($bankCheck, $refund)) {
            Tools::log()->warning('accounting-entry-error');
            return;
        }

        Tools::log()->notice('record-updated-correctly');
    }

    /**
     * Add Accounting button depending on the status of the bank check.
     */
    private function addAccountingActions()
    {
        $mainViewName = $this->getMainViewName();
        $model = $this->views[$mainViewName]->model;
        if (empty($model->idbank) || empty($model->codsubaccount)) {
            return;
        }

        switch ($model->status) {
            case CustomerBankCheck::STATUS_CHARGED:
                $this->addButton($mainViewName, [
                    'action' => 'refound-bankcheck',
                    'color' => 'warning',
                    'icon' => 'fas fa-undo',
                    'label' => 'refund',
                    'confirm' => 'true',
                ]);
                break;

            default:
                $this->addButton($mainViewName, [
                    'type' => 'modal',
                    'action' => 'charged-bankcheck',
                    'label' => 'charge',
                    'color' => 'info',
                    'icon' => 'fas fa-check-square',
                ]);
                // set values to modal form
                $model->charged_bank = $model->idbank;
                $model->charged_date = \date(CustomerBankCheck::DATE_STYLE);
                break;
        }
    }

    /**
     * Make the accounting entry of the collection.
     */
    private function chargedBankCheckAction()
    {
        $bankCheck = null;
        if (false === $this->loadBankCheck($bankCheck)) {
            return;
        }

        if (false === empty($bankCheck->identry)) {
            return;
        }

        $data = $this->request->request->all();
        $bankCheck->charged = $data['charged_date'];
        $bankCheck->idbank = $data['charged_bank'];
        $this->accountingBankCheck($bankCheck, false);
    }

    /**
     * Load custumer bank check from request code.
     *
     * @param CustomerBankCheck $bankCheck
     * @return bool
     */
    private function loadBankCheck(&$bankCheck): bool
    {
        $id = $this->request->query->get('code');
        if (empty($id)) {
            return false;
        }

        $bankCheck = new CustomerBankCheck();
        return $bankCheck->loadFromCode($id);
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
     * Make the accounting entry of the refund of the collection.
     */
    private function refundBankCheckAction()
    {
        $bankCheck = null;
        if (false === $this->loadBankCheck($bankCheck)) {
            return;
        }

         if (empty($bankCheck->identry)) {
            return;
        }

        $bankCheck->charged = \date(CustomerBankCheck::DATE_STYLE);
        $this->accountingBankCheck($bankCheck, true);
   }
}
