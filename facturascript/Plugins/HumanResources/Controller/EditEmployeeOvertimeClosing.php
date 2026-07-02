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
use FacturaScripts\Dinamic\Model\Attendance;
use FacturaScripts\Plugins\HumanResources\Model\EmployeeOvertimeClosing;
use FacturaScripts\Plugins\HumanResources\Model\EmployeeWorkPeriod;
use FacturaScripts\Plugins\HumanResources\Model\EmployeeWorkShift;
use FacturaScripts\Plugins\HumanResources\Model\OvertimeClosing;

/**
 * Controller to edit the overtime closing for an employee
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditEmployeeOvertimeClosing extends EditController
{
    /** @var EmployeeWorkPeriod[] */
    public array $workperiods = [];

    private const VIEW_ATTENDANCES = 'ListOvertimeClosingAttendances';

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'EmployeeOvertimeClosing';
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
        $pagedata['title'] = 'overtime-compensation';
        $pagedata['icon'] = 'fa-solid fa-user-lock';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    /**
     * Create the view to display.
     */
    protected function createViews()
    {
        parent::createViews();
        $this->createViewsAttendances();

        $mvn = $this->getMainViewName();
        $this->views[$mvn]->setSettings('btnNew', false);
        $this->setTabsPosition('bottom');
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
            case 'new-attendance':
                $this->newAttendanceAction();
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
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case self::VIEW_ATTENDANCES:
                $mvn = $this->getMainViewName();
                $view->model->employee_id = (int)$this->getViewModelValue($mvn,'idemployee');
                $view->model->closing_id = (int)$this->getViewModelValue($mvn,'idclosing');
                $view->model->overtime_id = (int)$this->getViewModelValue($mvn,'id');
                $compensation = $this->getViewModelValue($mvn,'compensation');

                $where = [
                    new DataBaseWhere('idemployee', $view->model->employee_id),
                    new DataBaseWhere('idclosing', $view->model->closing_id),
                ];
                $view->loadData('', $where);
                if ($compensation < EmployeeOvertimeClosing::COMPENSATION_HOLIDAY) {
                    $view->setSettings('active', false);
                    return;
                }
                $this->setWorkPeriods($view->model->closing_id, $view->model->employee_id);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    /**
     * Add the attendance list related to the overtime closing.
     *
     * @param string $viewName
     * @return void
     */
    private function createViewsAttendances(string $viewName = self::VIEW_ATTENDANCES): void
    {
        $this->addListView($viewName, 'Attendance', 'compensation-attendance', 'fa-solid fa-unlink')
            ->addOrderBy(['checkdate', 'checktime'], 'date')
            ->setSettings('modalInsert', 'new-attendance');
    }

    /**
     * Add the compensation attendances.
     *
     * @return void
     */
    private function newAttendanceAction(): void
    {
        if ($this->validateFormToken()) {
            $data = $this->request->request->all();
            Attendance::justifiedFromData($data, true);
        }
    }

    /**
     * Set into the workperiods array the work periods for the employee.
     *
     * @param int $idclosing
     * @param int $idemployee
     * @return void
     */
    private function setWorkPeriods(int $idclosing, int $idemployee): void
    {
        $closing = new OvertimeClosing();
        if (false === $closing->loadFromCode($idclosing)) {
            return;
        }

        $workshift = EmployeeWorkShift::workShiftForEmployee($idemployee, $closing->startdate);
        if (empty($workshift->id)) {
            return;
        }

        for ($day = 0; $day < 7; ++$day) {
            $this->workperiods[$day] = $workshift->getPeriod($day + 1);
        }
    }
}