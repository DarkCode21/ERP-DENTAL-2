<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\HumanResources\Model\EmployeeVoucherPaid;

/**
 * Controler to edit Employee Contract.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditEmployeeVoucher extends EditController
{

    const VIEWNAME_EMPLOYEEVOUCHERPAID = 'ListEmployeeVoucherPaid';

    const ACTION_INSERTPAID = 'insertpaid';

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'EmployeeVoucher';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['menu'] = 'rrhh';
        $pagedata['title'] = 'voucher';
        $pagedata['icon'] = 'fa-solid fa-hand-holding-usd';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    /**
     * Create the view to display.
     */
    protected function createViews()
    {
        parent::createViews();

        $this->addVoucherPaidView();
        $this->setTabsPosition('bottom');
    }

    /**
     * Run the actions that alter data before reading it.
     *
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case self::ACTION_INSERTPAID:
                $this->insertVoucherPaid();
                return true;

            default:
                return parent::execPreviousAction($action);
        }
    }

    /**
     * Loads the data to display.
     *
     * @param string   $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case self::VIEWNAME_EMPLOYEEVOUCHERPAID:
                $this->loadEmployeeVoucherPaid($view);
                break;

            default:
                parent::loadData($viewName, $view);
                $readOnly = $view->model->paid || ($view->model->amount != $view->model->pending);
                $view->setReadOnly($readOnly);
                break;
        }
    }

    /**
     * Add voucher paid view
     *
     * @param string $viewName
     */
    private function addVoucherPaidView($viewName = self::VIEWNAME_EMPLOYEEVOUCHERPAID)
    {
        $this->addListView($viewName, 'EmployeeVoucherPaid', 'payments');
        $this->setSettings($viewName, 'modalInsert', self::ACTION_INSERTPAID);
        $this->setSettings($viewName, 'clickable', false);
    }

    /**
     * Inset a new payment to voucher employee
     */
    private function insertVoucherPaid()
    {
        $idvoucher = $this->request->request->get('modalVoucher', 0);
        $amount = $this->request->request->get('modalAmount', 0.00);
        if (empty($idvoucher) || empty($amount)) {
            return;
        }

        $date = $this->request->request->get('modalDate');
        $payment = new EmployeeVoucherPaid();
        $payment->idvoucher = $idvoucher;
        $payment->amount = $amount;
        $payment->nick = $this->user->nick;
        if (false === empty($date)) {
            $payment->startdate = $date;
            $payment->starttime = '00:00:00';
        }

        if ($payment->save()) {
            Tools::log()->notice('record-updated-correctly');
        }
    }

    /**
     * Load data to view with paid for voucher employee
     *
     * @param BaseView $view
     */
    private function loadEmployeeVoucherPaid($view)
    {
        /// Get master data
        $mainViewName = $this->getMainViewName();
        $idvoucher = $this->getViewModelValue($mainViewName, 'id');
        $pending = $this->getViewModelValue($mainViewName, 'pending');

        /// Set master values to insert modal view
        $view->model->modalVoucher = $idvoucher;
        $view->model->modalAmount = $pending;

        /// Load view data
        $where = [new DataBaseWhere('idvoucher', $idvoucher)];
        $view->loadData(false, $where, ['startdate' => 'DESC', 'starttime' => 'DESC']);
    }
}
