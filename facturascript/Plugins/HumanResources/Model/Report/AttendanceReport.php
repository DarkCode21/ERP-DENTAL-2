<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Model\Report;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ListFilter\BaseFilter;
use FacturaScripts\Core\Lib\ListFilter\PeriodFilter;
use FacturaScripts\Core\Model\CodeModel;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelReport;
use FacturaScripts\Plugins\HumanResources\Lib\DateTimeTools;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\AttendanceIncidence;
use FacturaScripts\Plugins\HumanResources\Model\Attendance;
use FacturaScripts\Plugins\HumanResources\Model\Employee;
use FacturaScripts\Plugins\HumanResources\Model\EmployeeWorkShift;
use FacturaScripts\Plugins\HumanResources\Model\PublicHoliday;
use FacturaScripts\Plugins\HumanResources\Model\Report\Data\AttendanceData;

/**
 * Class to manage and report employee attendance
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AttendanceReport extends ModelReport
{

    /**
     * id of the employees to report.
     *
     * @var array
     */
    public array $codes = [];

    /**
     * Class for management of incidences
     *
     * @var AttendanceIncidence
     */
    private $incidenceModel;

    /**
     * Class for set attendance data
     *
     * @var AttendanceData
     */
    private $attendanceData;

    /**
     * Class for Employee WorkShift
     *
     * @var EmployeeWorkShift
     */
    private $workShift;

    /**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->incidenceModel = new AttendanceIncidence();
        $this->attendanceData = new AttendanceData();

        $this->attendanceData->idemployee = 0;
        $this->attendanceData->date = '01-01-1990';
        $this->clear();
    }

    /**
     * Execute the load data for report
     *
     * @param BaseFilter[] $filters
     * @param DataBaseWhere[] $where
     * @param array $order
     * @param int $offset
     * @param int $limit
     * @return AttendanceData[]
     */
    public function all(array $filters, array $where, array $order, int $offset, int $limit): array
    {
        /// Get date filter
        $startdate = $filters['date']->getValue(PeriodFilter::START_DATE_ID);
        if (empty($startdate)) {
            Tools::log()->warning('no-period-report');
            return [];
        }

        $enddate = $filters['date']->getValue(PeriodFilter::END_DATE_ID);
        if (empty($enddate)) {
            $enddate = $startdate;
        }

        /// Get filters
        $filterWhere = [];
        $idemployee = $filters['employee']->getValue();
        if ($idemployee > 0) {
            $filterWhere[] = new DataBaseWhere('id', $filters['employee']->getValue());
        } else if (false === empty($this->codes)) {
            $filterWhere[] = new DataBaseWhere('id', implode(',', $this->codes), 'IN');
        }

        $filters['status']->getDataBaseWhere($filterWhere);

        $result = [];
        $employeeModel = new Employee();
        foreach ($employeeModel->all($filterWhere, ['nombre' => 'ASC'], 0, 0) as $employee) {
            $employeeAttendances = $this->attendancesForEmployee($employee->id, $startdate, $enddate);
            if ($order['checkdate'] === 'DESC') {
                $employeeAttendances = array_reverse($employeeAttendances);
            }
            $result = array_merge($result, $employeeAttendances);
        }

        return $result;
    }

    /**
     * Attendance ratio (entry and exit) of an employee by date
     *
     * @param int $idemployee
     * @param string $startdate
     * @param string $enddate
     * @return AttendanceData[]
     */
    public function attendancesForEmployee($idemployee, $startdate, $enddate): array
    {
        $result = [];
        $this->attendanceData->idemployee = $idemployee;
        $this->attendanceData->name = $this->getEmployeeName();
        $this->attendanceData->date = date(Employee::DATE_STYLE, strtotime($startdate));

        $this->workShift = new EmployeeWorkShift();
        $this->checkWorkShift($this->attendanceData->idemployee, $this->attendanceData->date);

        $where = $this->getAttendancesWhere($startdate, $enddate);
        $order = $this->getAttendancesOrderBy();

        // Main process
        $attendance = new Attendance();
        foreach ($attendance->all($where, $order, 0, 0) as $row) {
            // Control breaking day or full assistance data
            if (strtotime($this->attendanceData->date) < strtotime($row->checkdate) || ($this->attendanceData->idexit !== 0)) {
                // If there is a record with complete attendance data
                if ($this->attendanceData->idexit !== 0) {
                    $this->checkIncidence();
                    $this->addAttendanceToSummary($result);
                }

                // If the day to be processed has changed
                if (strtotime($this->attendanceData->date) < strtotime($row->checkdate)) {
                    // If there is input data from the previous date, Incidence: Exit data is missing
                    if ($this->attendanceData->idinput !== 0) {
                        $this->attendanceData->incidence = AttendanceIncidence::INCIDENCE_NO_EXIT;
                        $this->addAttendanceToSummary($result);
                    }

                    // Process the next day (if not missing the first day)
                    if (isset($result[0]) > 0) {
                        $this->nextDay();
                    }

                    // For each day missing assistance
                    $this->checkMissingDays($result, $row->checkdate);
                }
            }

            // now, process the read attendance of the model
            $this->processAttendance($result, $row);
        }

        // If there is assistance with entry and no exit
        if ($this->attendanceData->idinput !== 0 && $this->attendanceData->idexit == 0) {
            $this->attendanceData->incidence = AttendanceIncidence::INCIDENCE_NO_EXIT;
            $this->addAttendanceToSummary($result);
            $this->nextDay();
        }

        // If there is assistance to return
        if ($this->attendanceData->idexit !== 0) {
            // Search if there is an incidence for the date
            $this->checkIncidence();
            $this->addAttendanceToSummary($result);
            $this->nextDay();
        }

        // For each day missing assistance
        $this->checkMissingDays($result, date('d-m-Y', strtotime($enddate . ' + 1 day')));

        return $result;
    }

    /**
     * @return string
     */
    public function url(): string
    {
        return 'ReportAttendance';
    }

    /**
     * Add new assistence to summary
     *
     * @param array $summary
     */
    private function addAttendanceToSummary(&$summary)
    {
        $this->calculateTotal();
        $this->checkIncidenceError();
        $this->translateIncidence();
        $summary[] = clone $this->attendanceData;
        $this->clear();
    }

    /**
     * Calculate total hour since input to exit attendance
     */
    private function calculateTotal()
    {
        $data = &$this->attendanceData;
        if ($data->input == '00:00:00' || $data->exit == '00:00:00') {
            $data->total = 0.00;
            return;
        }
        $data->total = DateTimeTools::timeDifferenceInHours($data->input, $data->exit);
        $data->totalday += $data->total;
    }

    /**
     * Check for Incidence in attendance date
     */
    private function checkIncidence()
    {
        if (false === empty($this->attendanceData->incidence)) {
            return;
        }

        $this->attendanceData->incidence = $this->incidenceModel->getIncidence(
            $this->attendanceData->idemployee,
            $this->attendanceData->date
        );

        if (false === empty($this->attendanceData->incidence)) {
            $this->attendanceData->inputdelay = 0;
        }

        // Control for special incidences
        if ($this->attendanceData->incidence == AttendanceIncidence::INCIDENCE_LEAVE) {
            $this->attendanceData->input = '00:00:00';
            $this->attendanceData->exit = '00:00:00';
            $this->attendanceData->total = 0.0000;
        }
    }

    /**
     * Check if incidence its an attendance error.
     * Its an error if:
     *   - the day has a work shift with assigned hours
     *   - the day is not a public holiday
     *   - the day is not a employee holiday
     */
    private function checkIncidenceError()
    {
        if (empty($this->attendanceData->incidence)) {
            return;
        }

        $this->checkWorkShift($this->attendanceData->idemployee, $this->attendanceData->date);
        if ($this->workShift->getHoursToWork($this->attendanceData->date) == 0) {
            return;
        }

        $publicHoliday = new PublicHoliday();
        $where = [ new DataBaseWhere('holiday', $this->attendanceData->date) ];
        $publicHoliday->loadFromCode('', $where);
        if (empty($publicHoliday->id)) {
            return;
        }

        $this->attendanceData->incidenceError = ($this->attendanceData->incidence !== AttendanceIncidence::INCIDENCE_HOLIDAY);
    }

    /**
     * Check and add attendance to summary for missing date
     *
     * @param array $summary
     * @param string $endDate
     */
    private function checkMissingDays(&$summary, $endDate)
    {
        $data = &$this->attendanceData;
        while (strtotime($data->date) < strtotime($endDate)) {
            // Search if there is an incidence for the date
            $data->incidence = $this->incidenceModel->getIncidence($data->idemployee, $data->date);

            if ($data->incidence == '') {
                $data->incidence = AttendanceIncidence::INCIDENCE_NO_ATTENDANCE;
            }
            $this->addAttendanceToSummary($summary);
            $data->incidence = '';
            $this->nextDay();
        }
    }

    /**
     * Set attendance values to initial values
     */
    private function clear()
    {
        $this->attendanceData->authorized_input = false;
        $this->attendanceData->authorized_exit = false;
        $this->attendanceData->input = '00:00:00';
        $this->attendanceData->exit = '00:00:00';
        $this->attendanceData->total = 0.0000;
        $this->attendanceData->inputdelay = 0;
        $this->attendanceData->idinput = 0;
        $this->attendanceData->idexit = 0;
        $this->attendanceData->incidence = '';
        $this->attendanceData->incidenceError = false;
    }

    /**
     *
     * @return array
     */
    private function getAttendancesOrderBy(): array
    {
        return [
            'checkdate' => 'ASC',
            'checktime' => 'ASC',
            'kind' => 'ASC'
        ];
    }

    /**
     *
     * @param string $startdate
     * @param string $enddate
     * @return DataBaseWhere[]
     */
    private function getAttendancesWhere($startdate, $enddate): array
    {
        return [
            new DataBaseWhere('idemployee', $this->attendanceData->idemployee),
            new DataBaseWhere('checkdate', $startdate, '>='),
            new DataBaseWhere('checkdate', $enddate, '<=')
        ];
    }

    /**
     *
     */
    private function getEmployeeName()
    {
        $model = new CodeModel();
        return $model->getDescription('rrhh_employees', 'id', $this->attendanceData->idemployee, 'nombre');
    }

    /**
     * Add one day to attendance date
     */
    private function nextDay()
    {
        $this->attendanceData->date = date('d-m-Y', strtotime($this->attendanceData->date . ' + 1 day'));
        $this->attendanceData->totalday = 0.0000;
    }

    /**
     *
     * @param AttendanceData[] $summary
     * @param Attendance $model
     */
    private function processAttendance(&$summary, $model)
    {
        $data = &$this->attendanceData;
        switch ($model->kind) {
            case Attendance::KIND_INPUT:
                // If there is previous input data, Incidence: Output data is missing
                if ($data->idinput !== 0) {
                    $data->incidence = AttendanceIncidence::INCIDENCE_NO_EXIT;
                    $this->addAttendanceToSummary($summary);
                }

                // Set attendance data
                $data->idinput = $model->id;
                $data->input = $model->checktime;
                $data->authorized_input = $model->authorized;
                $data->inputdelay = $model->inputdelay;
                return;

            case Attendance::KIND_OUTPUT:
                // If there is previous exit data, Incidence: Input data is missing
                if ($data->idexit !== 0) {
                    $data->incidence = AttendanceIncidence::INCIDENCE_NO_INPUT;
                    $this->addAttendanceToSummary($summary);
                }

                // Set attendance data
                $data->idexit = $model->id;
                $data->exit = $model->checktime;
                $data->authorized_exit = $model->authorized;

                if ($data->idinput == 0) {
                    $data->incidence = AttendanceIncidence::INCIDENCE_NO_INPUT;
                    $this->addAttendanceToSummary($summary);
                }
                return;
        }
    }

    /**
     * Add incidence user language
     */
    private function translateIncidence()
    {
        $this->attendanceData->translatedIncidence = AttendanceIncidence::getIncidenceShortDesc($this->attendanceData->incidence);
        $this->attendanceData->translatedIncidenceDesc = AttendanceIncidence::getIncidenceDescription($this->attendanceData->incidence);
    }

    /**
     *
     * @param int $employee
     * @param string $date
     * @return bool
     */
    private function checkWorkShift($employee, $date): bool
    {
        // Check if data its in current work shift
        if ($this->workShift->dateInWorkShift($date)) {
            return true;
        }

        // Search work shift for date
        $where = [
            new DataBaseWhere('idemployee', $employee),
            new DataBaseWhere('startdate', $date, '<='),
            new DataBaseWhere('enddate', $date, '>='),
            new DataBaseWhere('enddate', NULL, 'IS', 'OR')
        ];

        return $this->workShift->loadFromCode('', $where, ['startdate' => 'ASC', 'enddate' => 'ASC']);
    }
}
