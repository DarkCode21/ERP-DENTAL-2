<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\ControllerPermissions;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\KernelException;
use FacturaScripts\Core\Lib\AssetManager;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Attendance;
use FacturaScripts\Dinamic\Model\Employee;
use FacturaScripts\Dinamic\Model\EmployeeHoliday;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Plugins\HumanResources\Lib\DateTimeTools;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\PanelAttendance;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\PanelDocument;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\PanelHoliday;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\PanelVoucher;
use FacturaScripts\Plugins\HumanResources\Model\Report\Data\AttendanceWeeklyData;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controler to show data for one employee.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EmployeePanel extends Controller
{

    /**
     *
     * @var PanelAttendance
     */
    public $attendances;

    /**
     *
     * @var PanelDocument
     */
    public $documents;

    /**
     *
     * @var Employee
     */
    public $employee;

    /**
     *
     * @var PanelHoliday
     */
    public $holidays;

    /**
     *
     * @var PanelVoucher
     */
    public $vouchers;

    /**
     *
     * @var AttendanceWeeklyData
     */
    public $weekwork = null;

    /**
     * Initialize all objects and properties.
     *
     * @param string $className
     * @param string $uri
     */
    public function __construct(string $className, string $uri = '')
    {
        parent::__construct($className, $uri);
        $this->employee = new Employee();

        $this->attendances = new PanelAttendance();
        $this->documents = new PanelDocument();
        $this->holidays = new PanelHoliday();
        $this->vouchers = new PanelVoucher();
    }

    /**
     * Format amount value to string with divisa symbol.
     *
     * @param float $value
     * @return string
     */
    public function getMoney(float $value): string
    {
        return Tools::money($value);
    }

    /**
     * Return time into string with days, hours and minutes format.
     *
     * @param float $time
     * @return string
     */
    public function getTime(float $time): string
    {
        $timeStr = DateTimeTools::decimalTimeToString($time);
        $days = $timeStr['days'] > 0
            ? $timeStr['days'] . 'd '
            : '';
        return $days
            . sprintf("%02d", $timeStr['hours'])
            . ':'
            . sprintf("%02d", $timeStr['minutes']);
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'employee-panel';
        $pagedata['icon'] = 'fa-solid fa-solar-panel';
        $pagedata['menu'] = 'rrhh';
        $pagedata['ordernum'] = -1;
        return $pagedata;
    }

    /**
     * Runs the controller's private logic.
     *
     * @param Response $response
     * @param User $user
     * @param ControllerPermissions $permissions
     * @throws KernelException
     */
    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        AssetManager::add('js', FS_ROUTE . '/Dinamic/Assets/JS/geolocation.js');

        if ($this->loadEmployee()) {
            $this->execPreviousAction($this->request->get('action', ''));

            // load data for panels/cards
            $this->attendances->load($this->employee->id, $this->getSelectDate());
            $this->documents->load($this->employee->id);
            $this->holidays->load($this->employee->id);
            $this->vouchers->load($this->employee->id);
            $this->weekwork = $this->attendances->getWeekWork();
        }
    }

    /**
     * Exec special actions before load data.
     *
     * @param string $action
     */
    protected function execPreviousAction($action)
    {
        $idemployee = $this->request->get('idemployee', 0);
        if ($this->employee->id == $idemployee) {
            if (false === $this->validateFormToken()) {
                return;
            }
            switch ($action) {
                case 'insert-attendance':
                    $this->execInsertAttendance();
                    break;
                case 'insert-holidays':
                    $this->execInsertHolidays();
                    break;
                case 'delete-attendance':
                    $this->execDeleteAttendance();
                    break;
                case 'delete-holidays':
                    $this->execDeleteHolidays();
                    break;
            }
        }
    }

    /**
     * Insert a new attendance for employee.
     * Check that the date is equal to or less than today's date.
     */
    private function execInsertAttendance()
    {
        $data = $this->request->request->all();
        if (DateTimeTools::greaterCurrentDateTime($data['date'], $data['time'])) {
            Tools::log()->notice('date-must-be-less');
            return;
        }

        $adjust = $data['adjust'] === 'true' ?? false;
        $attendance = new Attendance();
        $attendance->idemployee = (int)$data['idemployee'];
        $attendance->origin = (int)$data['origin'];
        $attendance->kind = (int)$data['kind'];
        $attendance->location = $data['location'] ?? '';
        if ($attendance->origin == Attendance::ORIGIN_MANUAL) {
            $attendance->authorized = false;
            $attendance->checkdate = $data['date'];
            $attendance->checktime = $data['time'];
            $attendance->note = $data['note'];
        }
        $attendance->setAdjustToWordPeriod($adjust);
        $attendance->save();
    }

    /**
     * Insert a new holidays period for employee.
     */
    private function execInsertHolidays()
    {
        $data = $this->request->request->all();
        if (DateTimeTools::dateLessThan($data['startdate'])) {
            Tools::log()->notice('date-must-be-greater');
            return;
        }

        $holidays = new EmployeeHoliday();
        $holidays->authorized = false;
        $holidays->idemployee = $data['idemployee'];
        $holidays->startdate = $data['startdate'];
        $holidays->enddate = $data['enddate'];
        $holidays->note = $data['notes'];
        $holidays->save();
    }

    /**
     * Delete indicate attendance of the employee.
     */
    private function execDeleteAttendance()
    {
        $id = $this->request->get('idmodel', 0);
        if (empty($id)) {
            return;
        }
        $attendance = new Attendance();
        if ($attendance->loadFromCode($id)) {
            $attendance->delete();
        }
    }

    /**
     * Delete indicate holidays period of the employee.
     */
    private function execDeleteHolidays()
    {
        $id = $this->request->get('idmodel', 0);
        if (empty($id)) {
            return;
        }
        $holidays = new EmployeeHoliday();
        if ($holidays->loadFromCode($id)) {
            $holidays->delete();
        }
    }

    /**
     * Get date for process attendances.
     *
     * @return string
     */
    private function getSelectDate(): string
    {
        $date = $this->request->get("selectDate", date('Y-m-d'));
        $action = $this->request->get('action', '');
        switch ($action) {
            case "next-attendance":
                return date("d-m-Y", strtotime($date . "+1 days"));

            case "previous-attendance":
                return date("d-m-Y", strtotime($date . "-1 days"));
        }
        return $date;
    }

    /**
     * Load employee data structure for user logged.
     */
    private function loadEmployee(): bool
    {
        $where = [ new DataBaseWhere('nick', $this->user->nick) ];
        return $this->employee->loadFromCode('', $where);
    }
}
