<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\HumanResources;

use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\HumanResources\Lib\DateTimeTools;
use FacturaScripts\Plugins\HumanResources\Model\Attendance;
use FacturaScripts\Plugins\HumanResources\Model\Report\AttendanceReport;
use FacturaScripts\Plugins\HumanResources\Model\Report\AttendanceWeeklyReport;
use FacturaScripts\Plugins\HumanResources\Model\Report\Data\AttendanceData;
use FacturaScripts\Plugins\HumanResources\Model\Report\Data\AttendanceWeeklyData;

/**
 * Class for management Attendances data of the employee panel
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class PanelAttendance
{

    /** @var AttendanceData[] */
    public $data;

    /** @var string */
    public $date;

    /** @var int */
    public $idemployee;

    /** @var bool */
    public $isBeforeDate;

    /** @var AttendanceData */
    public $previousAttendance;

    /** @var float */
    public $total;

    /** @var array */
    public $time;

    /**
     * Constructor and inicializate values
     */
    public function __construct()
    {
        $this->data = [];
        $this->date = date('d-m-Y');
        $this->idemployee = 0;
        $this->isBeforeDate = false;
        $this->previousAttendance = new AttendanceData();
        $this->total = 0.00;
        $this->time = ['days' => 0, 'hours' => 0, 'minutes' => 0];
    }

    /**
     * Get week work data for employee and data loaded.
     *
     * @return AttendanceWeeklyData
     */
    public function getWeekWork(): AttendanceWeeklyData
    {
        $week = new AttendanceWeeklyReport();
        return $week->attendanceWeeklyForEmployee($this->date, $this->idemployee);
    }

    /**
     * Indicate if the employee has delay warning.
     *
     * @return bool
     */
    public function hasDelayWarning(): bool
    {
        $maxdelay = (int)Tools::settings('rrhh', 'maxdelay', 0) ?? 0;
        $perioddelay = (int)Tools::settings('rrhh', 'perioddelay', 0) ?? 0;
        if ($maxdelay === 0 || $perioddelay === 0) {
            return false;
        }

        $from = ' -' . $perioddelay . ' days';
        $fromDate = date('Y-m-d', strtotime($this->date . $from));
        return Attendance::countDelays($this->idemployee, $fromDate) > $maxdelay;
    }

    /**
     * Load attendances data structure for employee and selected date.
     *
     * @param int $idemployee
     * @param string $date
     */
    public function load(int $idemployee, string $date)
    {
        $attendanceReport = new AttendanceReport();
        $attendancesData = $attendanceReport->attendancesForEmployee(
            $idemployee,
            $date,
            $date
        );

        $this->idemployee = $idemployee;
        $this->date = $date;
        $this->isBeforeDate = DateTimeTools::dateLessThan($date);
        $deleteDate = date('Y-m-d', strtotime(date('Y-m-d') . '-1 days'));
        foreach ($attendancesData as $item) {
            if (empty($item->idexit) && empty($item->idinput)) {
                continue;
            }
            $item->canDelete = DateTimeTools::dateGreaterThan($item->date, $deleteDate);
            $this->data[] = $item;
            $this->total += $item->total;
        }
        $this->time = DateTimeTools::decimalTimeToString($this->total);
        $this->setPreviousAttendance();
    }

    /**
     * Return periode: monday to sunday in format 'dd/mm - dd/mm'.
     *
     * @return string
     */
    public function workshiftPeriode(): string
    {
        $mondayDate = DateTimeTools::mondayFromDate($this->date);
        $sundayDate = date('d-m-Y', strtotime('+6 days', strtotime($mondayDate)));
        return '('
            . date("d", strtotime($mondayDate)) . '/' . date("m", strtotime($mondayDate))
            . ' - '
            . date("d", strtotime($sundayDate)) . '/' . date("m", strtotime($sundayDate))
            . ')';
    }

    /**
     * Set previous attendance data from last attendance.
     *
     * @return void
     */
    private function setPreviousAttendance()
    {
        $previousDate = date('Y-m-d', strtotime($this->date . ' -1 days'));
        $attendance = Attendance::lastAttendance($this->idemployee, $previousDate);
        $this->previousAttendance->idemployee = $attendance->idemployee;
        $this->previousAttendance->date = $attendance->checkdate;

        if ($attendance->kind === Attendance::KIND_INPUT) {
            $this->previousAttendance->authorized_input = $attendance->authorized;
            $this->previousAttendance->idinput = $attendance->id;
            $this->previousAttendance->input = $attendance->checktime;
            return;
        }

        $this->previousAttendance->authorized_exit = $attendance->authorized;
        $this->previousAttendance->idexit = $attendance->id;
        $this->previousAttendance->exit = $attendance->checktime;
    }
}
