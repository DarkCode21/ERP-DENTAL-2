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
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Plugins\HumanResources\Lib\Accounting\PayRollToAccounting;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\PayRollSalary;
use FacturaScripts\Plugins\HumanResources\Model\Employee;
use FacturaScripts\Plugins\HumanResources\Model\EmployeePayRoll;
use FacturaScripts\Plugins\HumanResources\Model\PayRoll;

/**
 * Controller to edit payroll
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditPayRoll extends EditController
{

    const VIEWNAME_PAYROLLSUMMARY = 'ListPayRollSummary';
    const VIEWNAME_PAYROLLACCOUNTING = 'ListPayRollAccountingSummary';

    const INSERT_EMPLOYEE_SELECTED = 'SELECTED';
    const INSERT_EMPLOYEE_ACTIVE = 'ACTIVE';

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'PayRoll';
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
        $pagedata['title'] = 'payroll';
        $pagedata['icon'] = 'fa-solid fa-file-invoice-dollar';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    /**
     * Create views
     */
    protected function createViews()
    {
        parent::createViews();

        $this->addPayRollSummary();
        $this->addPayRollAccounting();
        $this->setTabsPosition('bottom');
    }

    /**
     * Run the controller after actions.
     *
     * @param string $action
     */
    protected function execAfterAction($action)
    {
        $payRollAccounting = $this->views[self::VIEWNAME_PAYROLLACCOUNTING];

        if ($payRollAccounting->count == 0) {
            $this->setSettings(self::VIEWNAME_PAYROLLSUMMARY, 'btnNew', true);
            $this->setSettings(self::VIEWNAME_PAYROLLSUMMARY, 'btnDelete', true);
            $this->addActionButtons();
        }

        parent::execAfterAction($action);
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
            case 'insertemployees':
                $data = $this->request->request->all();
                $this->insertEmployees($data);
                return true;

            case 'recalculate':
                $data = $this->request->request->all();
                $this->calculateEmployees($data);
                return true;

            case 'accounting-payroll':
                $this->accountingPayRoll();
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
            case self::VIEWNAME_PAYROLLACCOUNTING:
                $this->loadDataPayRollAccountingSummary($view);
                break;

            case self::VIEWNAME_PAYROLLSUMMARY:
                $this->loadDataPayRollSummary($view);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    /**
     * Insert employees in the payroll
     *
     * @param array $data
     */
    private function insertEmployees($data)
    {
        $idpayroll = $this->request->get('code');
        if (empty($idpayroll)) {
            return;
        }

        $where = [];
        switch ($data['option']) {
            case self::INSERT_EMPLOYEE_SELECTED:
                $employee = $data['idemployee'];
                if (empty($employee)) {
                    Tools::log()->error('no-employee-informed');
                    return;
                }
                $where[] = new DataBaseWhere('id', $employee);
                break;

            case self::INSERT_EMPLOYEE_ACTIVE:
                $where[] = new DataBaseWhere('dischargedate', NULL);
                break;
        }

        /// Insert employee and his/her salary concepts into payroll
        $payroll = new EmployeePayRoll();
        if ($payroll->addEmployee($idpayroll, $where)) {
            /// Calculate employees salaries
            $idEmployees = Employee::getIdFromDataBaseWhere($where);
            $employeeWhere = [ new DataBaseWhere('idemployee', implode(',', $idEmployees), 'IN') ];
            $payrollSalary = new PayRollSalary();
            $payrollSalary->calculate($idpayroll, $employeeWhere);
        }
    }

    /**
     * Payroll accounts for employees.
     */
    private function accountingPayRoll()
    {
        $idpayroll = $this->request->get('code');
        if (empty($idpayroll)) {
            return;
        }

        $payroll = new PayRoll();
        if ($payroll->loadFromCode($idpayroll)) {
            $accounting = new PayRollToAccounting();
            $accounting->generate($payroll);
        }
    }

    /**
     * Add an action buttons collection according to the state of the views
     */
    private function addActionButtons()
    {
        if (empty($this->getViewModelValue('EditPayRoll', 'id'))) {
            return;
        }

        $this->addButton(self::VIEWNAME_PAYROLLSUMMARY, [
            'action' => 'recalculate',
            'icon' => 'fa-solid fa-retweet',
            'label' => 'recalculate',
            'type' => 'action',
            'color' => 'info',
            'confirm' => true,
        ]);

        if ($this->views[self::VIEWNAME_PAYROLLSUMMARY]->count > 0) {
            $this->addButton($this->getMainViewName(), [
                'action' => 'accounting-payroll',
                'icon' => 'fa-solid fa-align-left',
                'label' => 'accounting-payroll',
                'type' => 'action',
                'color' => 'info',
                'confirm' => true,
            ]);
        }
    }

    /**
     * Add the view with accounting data
     *
     * @param string $viewName
     */
    private function addPayRollAccounting($viewName = self::VIEWNAME_PAYROLLACCOUNTING)
    {
        $this->addListView($viewName, 'Join\PayRollAccountingSummary', 'accounting-entries', 'fa-solid fa-balance-scale');
        $this->setSettings($viewName, 'btnNew', false);
        $this->setCurrentView($viewName, 'megasearch', false);
        $this->setCurrentView($viewName, 'checkBoxes', false);
    }

    /**
     * Add view with payroll totals per employee
     *
     * @param string $viewName
     */
    private function addPayRollSummary($viewName = self::VIEWNAME_PAYROLLSUMMARY)
    {
        $this->addListView($viewName, 'Join\PayRollSummary', 'employee-salaries', 'fa-solid fa-money-bill-alt');
        $this->setSettings($viewName, 'modalInsert', 'insertemployees');
        $this->setCurrentView($viewName, 'megasearch', false);
        $this->setCurrentView($viewName, 'checkBoxes', false);
    }

    /**
     * Calculate the amounts for employees
     *
     * @param array $data
     */
    private function calculateEmployees($data)
    {
        $idpayroll = $this->request->get('code');
        if (empty($idpayroll)) {
            return;
        }

        $codes = $data['code'] ?? [];
        $employees = implode(',', $codes);
        if (empty($employees)) {
            return;
        }

        $where = [ new DataBaseWhere('id', $employees, 'IN') ];

        $payrollSalary = new PayRollSalary();
        $payrollSalary->calculate($idpayroll, $where);
    }

    /**
     * Load data to view with payroll totals per employee
     *
     * @param BaseView $view
     */
    private function loadDataPayRollAccountingSummary($view)
    {
        /// Get master data
        $mainViewName = $this->getMainViewName();
        $idpayroll = $this->getViewModelValue($mainViewName, 'id');

        /// Load view data
        $where = [new DataBaseWhere('idpayroll', $idpayroll)];
        $view->loadData(false, $where, ['asientos.canal' => 'ASC', 'asientos.numero' => 'ASC']);
    }

    /**
     * Load data to view with payroll totals per employee
     *
     * @param BaseView $view
     */
    private function loadDataPayRollSummary($view)
    {
        /// Get master data
        $mainViewName = $this->getMainViewName();
        $idpayroll = $this->getViewModelValue($mainViewName, 'id');

        /// Set master values to insert modal view
        $view->model->idpayroll = $idpayroll;
        $view->model->option = self::INSERT_EMPLOYEE_SELECTED;

        /// Load view data
        $where = [new DataBaseWhere('idpayroll', $idpayroll)];
        $view->loadData(false, $where, ['idemployee' => 'ASC']);
    }
}
