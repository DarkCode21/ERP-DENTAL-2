<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Controller;

use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ExtendedController\BaseView;
use FacturaScripts\Dinamic\Model\OvertimeClosing;
use FacturaScripts\Dinamic\Model\Report\AttendanceSummaryReport;
use FacturaScripts\Plugins\HumanResources\Model\EmployeeOvertimeClosing;

/**
 * Controller to edit overtime closing
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditOvertimeClosing extends EditController
{
    private const VIEW_EMPLOYEES = 'ListEmployeeOvertimeClosing';

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'OvertimeClosing';
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
        $pagedata['title'] = 'closing-overtime';
        $pagedata['icon'] = 'fa-solid fa-user-lock';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    /**
     * Create views
     *
     * @throws Exception
     */
    protected function createViews()
    {
        parent::createViews();
        $this->createViewEmployees();
        $this->setTabsPosition('bottom');
        $this->addActionButtons();
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
            case 'add-employees':
                $this->addEmployeesAction();
                return true;

            case 'compensation':
                $this->compensationAction();
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
            case self::VIEW_EMPLOYEES:
                $view->model->idclosing = $this->getViewModelValue($this->getMainViewName(), 'id');
                $where = [ new DataBaseWhere('idclosing', $view->model->idclosing) ];
                $view->loadData('', $where);
                break;

            default:
                parent::loadData($viewName, $view);
                if ($viewName === $this->getMainViewName() && $view->count === 0) {
                    $view->model->startdate = $view->model->previousClosingDate();
                }
                break;
        }
    }

    /**
     * @throws Exception
     */
    private function addActionButtons(): void
    {
        $this->addButton($this->getMainViewName(), [
            'type' => 'action',
            'action' => 'add-employees',
            'color' => 'info',
            'icon' => 'fa-solid fa-user-plus',
            'label' => 'add-employees',
            'confirm' => true,
        ]);
    }

    private function addEmployeesAction(): void
    {
        $data = $this->request->request->all();
        $closing = new OvertimeClosing();
        if (false === $this->validateFormToken()
            || false === $closing->loadFromCode($data['id'])
        ) {
            return;
        }

        $where = [
            new DataBaseWhere('idcompany', $closing->idcompany),
            new DataBaseWhere('dischargedate', NULL),
            new DataBaseWhere('dischargedate', $closing->enddate, '>=', 'OR'),
        ];
        $summary = new AttendanceSummaryReport();
        foreach ($summary->attendanceSummary($closing->startdate, $closing->enddate, $where) as $summaryData) {
            if ($summaryData->totalDifference <= 0) {
                continue;
            }

            $whereClosing = [
                new DataBaseWhere('idclosing', $closing->id),
                new DataBaseWhere('idemployee', $summaryData->idemployee),
            ];
            $employeeClosing = new EmployeeOvertimeClosing();
            if (false === $employeeClosing->loadFromCode('', $whereClosing)) {
                $employeeClosing->idclosing = $closing->id;
                $employeeClosing->idemployee = $summaryData->idemployee;
            }
            $employeeClosing->overtime = $summaryData->totalDifference;
            $employeeClosing->save();
        }
    }

    private function compensationAction(): void
    {
        if (false === $this->validateFormToken()) {
            return;
        }

        $codes = $this->request->request->get('code', '');
        $idclosing = (int)$this->request->request->get('idclosing', 0);
        $compensation = (int)$this->request->request->get('new_compensation', -1);
        if  (empty($codes) || empty($idclosing) || $compensation < 0) {
            Tools::log()->warning('data-form-error');
            return;
        }

        $where = [
            new DataBaseWhere('idclosing', $idclosing),
            new DataBaseWhere('id', $codes, 'IN'),
        ];
        $count = 0;
        $updated = 0;
        foreach (EmployeeOvertimeClosing::all($where) as $overtime) {
            $count++;
            $overtime->compensation = $compensation;
            if (false === $overtime->save()) {
                Tools::log()->warning('record-save-error');
                continue;
            }
            $updated++;
        }
        Tools::log()->info('updated-records', [
            '%updated%' =>$updated,
            '%count%' => $count,
        ]);
    }

    private function createViewEmployees(): void
    {
        $this->addListView(self::VIEW_EMPLOYEES, 'Join\EmployeeOvertimeClosing', 'employees', 'fa-solid fa-id-card')
            ->addOrderBy(['employee.nombre'], 'employee');
    }
}