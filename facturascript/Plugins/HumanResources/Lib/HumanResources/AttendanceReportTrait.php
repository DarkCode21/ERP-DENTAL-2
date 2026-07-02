<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\HumanResources;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Attendance;
use FacturaScripts\Plugins\HumanResources\Lib\DateTimeTools;
use FacturaScripts\Plugins\HumanResources\Model\Employee;
use FacturaScripts\Plugins\HumanResources\Model\EmployeeWorkShift;
use FacturaScripts\Plugins\HumanResources\Model\Report\AttendanceReport;
use FacturaScripts\Plugins\HumanResources\Model\Report\Data\AttendanceSummaryData;
use FacturaScripts\Plugins\HumanResources\Model\Report\Data\AttendanceWeeklyData;

/**
 * Common methods of process employee attendances for report:
 *   - Weekly
 *   - Summary (monthly)
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
trait AttendanceReportTrait
{

    /**
     *
     * @param EmployeeWorkShift $workShift
     * @param int $employee
     * @param string $date
     * @return bool
     */
    protected function checkWorkShift($workShift, $employee, $date): bool
    {
        // Check if data its in current work shift
        if ($workShift->dateInWorkShift($date)) {
            return true;
        }

        // Search work shift for date
        $where = [
            new DataBaseWhere('idemployee', $employee),
            new DataBaseWhere('startdate', $date, '<='),
            new DataBaseWhere('enddate', $date, '>='),
            new DataBaseWhere('enddate', NULL, 'IS', 'OR')
        ];

        return $workShift->loadFromCode('', $where, ['startdate' => 'ASC', 'enddate' => 'ASC']);
    }

    /**
     * Add Attendance Summary to report
     *
     * @param AttendanceWeeklyData|AttendanceSummaryData $item
     * @param int $days
     */
    protected function fixDaysValues($item, $days)
    {
        // Calculate totals
        $item->totalDifference = round($item->totalWorked - $item->totalHours, 2);

        // Removes leftover days
        array_splice($item->hours, $days);
        array_splice($item->worked, $days);
        array_splice($item->difference, $days);
        array_splice($item->incidence, $days);
    }

    /**
     *
     * @param Employee $employee
     * @param AttendanceWeeklyData|AttendanceSummaryData $summary
     * @param string $startdate
     * @param string $enddate
     * @param int $maxDays
     * @param bool $checkJustified
     *
     * @return int
     */
    protected function processEmployeeAttendances(
        $employee,
        $summary,
        $startdate,
        $enddate,
        $maxDays = 7,
        $checkJustified = true
    ): int {
        $day = 0;
        $date = null;
        $workShift = new EmployeeWorkShift();
        $attendanceReport = new AttendanceReport();
        foreach ($attendanceReport->attendancesForEmployee($employee->id, $startdate, $enddate) as $data) {
            // We check if the column of the day has varied where to accumulate or if the work shift is missing
            if (($date !== $data->date) || empty($date) || empty($workShift->id)) {
                // Jump column of accumulation if the break is due to a date change
                if (false === empty($date) && ($date !== $data->date)) {
                    ++$day;
                }

                // Only process up to the maximum number of columns
                if ($day >= $maxDays) {
                    break;
                }

                // We collect the new date and check work shift:
                //   - If exists acumulate work shift hours
                //   - If not exits jump to next day
                $date = $data->date;
                if (false === $this->checkWorkShift($workShift, $employee->id, $date)) {
                    $summary->incidence[$day] = AttendanceIncidence::INCIDENCE_NO_WORKSHIFT;
                    continue;
                }

                $hours = $this->calculateHoursToWork($workShift, $date, $data->incidence);
                $summary->hours[$day] += $hours;
                $summary->totalInputDelay += ($data->inputdelay > 0) ? 1 : 0;
                if (false === DateTimeTools::dateGreaterThan($date, true)) {
                    $summary->totalHours += $hours;
                }

                // Look for if the day has justified attendance
                if ($checkJustified === true &&
                    empty($data->incidence) &&
                    $this->checkJustifiedAttendance($employee->id, $date))
                {
                    $summary->incidence[$day] = AttendanceIncidence::INCIDENCE_JUSTIFIED;
                }
            }

            // Collect the incidence and accumulate it on the processed day
            if (false === DateTimeTools::dateGreaterThan($date, true)) {
                $this->addIncidence($summary, $day, $data->incidence);

                // Accumulate values for the processed day
                $summary->totalWorked += round($data->total, 2);
                $summary->worked[$day] += round($data->total, 2);
                $summary->difference[$day] = round($summary->worked[$day] - $summary->hours[$day], 2);
            }
        }

        // Adjust worked hours to settings minutes for extra hours.
        $this->adjustMinForExtra($summary, $day);

        // Return the number of days processed
        return $day;
    }

    /**
     * Accumulate the incidence for the day if there is one.
     * If the incidence is of holiday type, accumulate the holiday to total holiday
     *
     * @param AttendanceSummaryData $summary
     * @param int $day
     * @param string $incidence
     */
    private function addIncidence($summary, $day, $incidence)
    {
        if (false === $this->checkValidIncidence($summary, $day, $incidence)) {
            return;
        }

        if ($incidence == AttendanceIncidence::INCIDENCE_HOLIDAY) {
            $summary->totalHoliday += 1;
        }

        if ($incidence == AttendanceIncidence::INCIDENCE_LEAVE) {
            $summary->totalLeave += 1;
        }

        $translatedIncidence = AttendanceIncidence::getIncidenceShortDesc($incidence);
        if (false == strpos($summary->incidence[$day], $translatedIncidence)) {
            $summary->hasIncidence = $summary->hasIncidence
                || false === in_array($incidence, [
                        AttendanceIncidence::INCIDENCE_PUBLICHOLIDAY,
                        AttendanceIncidence::INCIDENCE_HOLIDAY,
                        AttendanceIncidence::INCIDENCE_LEAVE,
            ]);

            if ($summary->incidence[$day] !== '') {
                $summary->incidence[$day] .= ',';
            }
            $summary->incidence[$day] .= $translatedIncidence;
        }
    }

    /**
     * Adjust the worked hours to the minimum for extra hours.
     * if the worked hours are less than the minimum for extra hours,
     * remove the difference from the total worked hours.
     *
     * @param $summary
     * @param $toDay
     * @return void
     */
    private function adjustMinForExtra($summary, $toDay): void
    {
        $minforextra = Tools::settings('rrhh', 'minforextra', 0);
        if ($minforextra <= 0) {
            return;
        }

        for ($day = 0; $day < $toDay; ++$day) {
            if ($summary->hours[$day] == 0) {
                continue;
            }

            if ($summary->difference[$day] < 0) {
                continue;
            }

            if (($summary->difference[$day] * 60) < $minforextra) {
                $summary->totalWorked -= $summary->difference[$day];
                $summary->worked[$day] = $summary->hours[$day];
                $summary->difference[$day] = 0;
            }
        }
    }

    /**
     * Returns the number of hours to work for the indicated date
     * into employee work shift
     *
     * @param EmployeeWorkShift $workShift
     * @param string $date
     * @param string $incidence
     * @return float
     */
    private function calculateHoursToWork(&$workShift, $date, $incidence): float
    {
        switch ($incidence) {
            case AttendanceIncidence::INCIDENCE_HOLIDAY:
            case AttendanceIncidence::INCIDENCE_PUBLICHOLIDAY:
            case AttendanceIncidence::INCIDENCE_LEAVE:
                return 0.00;

            default:
                return $workShift->getHoursToWork($date);
        }
    }

    /**
     * Check if the employee has justified assistance for the date
     *
     * @param int $employee
     * @param string $date
     * @return bool
     */
    private function checkJustifiedAttendance($employee, $date): bool
    {
        $attendance = new Attendance();
        $where = [
            new DataBaseWhere('idemployee', $employee),
            new DataBaseWhere('checkdate', $date),
            new DataBaseWhere('origin', Attendance::ORIGIN_JUSTIFIED)
        ];

        return $attendance->loadFromCode('', $where);
    }

    /**
     *
     * @param AttendanceSummaryData $summary
     * @param int $day
     * @param string $incidence
     * @return bool
     */
    private function checkValidIncidence($summary, $day, $incidence): bool
    {
        if (empty($incidence)) {
            return false;
        }

        if ($summary->hours[$day] == 0.00 && $incidence == AttendanceIncidence::INCIDENCE_NO_ATTENDANCE) {
            return false;
        }

        return true;
    }
}
