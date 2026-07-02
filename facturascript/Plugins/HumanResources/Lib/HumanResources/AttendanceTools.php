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
use FacturaScripts\Plugins\HumanResources\Lib\DateTimeTools;
use FacturaScripts\Plugins\HumanResources\Model\Attendance;
use FacturaScripts\Plugins\HumanResources\Model\EmployeeWorkShift;

/**
 * Complementary processes (Utilities) for the management of attendance
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AttendanceTools
{
    /**
     * Return a date for automatic attendance when the new attendance is in a new day.
     *
     * @param int $king
     * @param int $idemployee
     * @param string $checkdate
     * @return string
     */
    public static function autoAttendance(int $king, int $idemployee, string $checkdate): string
    {
        if ($king === Attendance::KIND_INPUT) {
            return '';
        }

        $attendance = Attendance::lastAttendance($idemployee);
        if ($attendance->kind != Attendance::KIND_INPUT) {
            return '';
        }

        return DateTimeTools::dateGreaterThan($checkdate, false, $attendance->checkdate)
            ? $attendance->checkdate
            : '';
    }

    /**
     * Return the text representation of the attendance kind.
     *
     * @param int $kind
     * @return string
     */
    public static function kindText(int $kind): string
    {
        switch ($kind) {
            case Attendance::KIND_INPUT:
                return Tools::lang()->trans('input');
            case Attendance::KIND_OUTPUT:
                return Tools::lang()->trans('output');
            default:
                return (string)$kind;
        }
    }

    public static function originText(int $origin): string
    {
        switch ($origin) {
            case Attendance::ORIGIN_MANUAL:
                return Tools::lang()->trans('manual');
            case Attendance::ORIGIN_JUSTIFIED:
                return Tools::lang()->trans('justified');
            case Attendance::ORIGIN_EXTERNAL:
                return Tools::lang()->trans('external');
            default:
                return (string)$origin;
        }
    }

    /**
     * Calculate the minutes delayed for an input attendance.
     *   - Search for work shift for the employee and date.
     *   - Search for the work period for the attendance.
     *   - Check if the first attendance for the period.
     *   - Calculate the minutes delayed.
     *
     * @param Attendance $attendance
     * @return int
     */
    public static function minutesInputDelayed(Attendance $attendance): int
    {
        if ($attendance->kind === Attendance::KIND_OUTPUT) {
            return 0;
        }

        $inputDelay = (int)Tools::settings('rrhh', 'inputdelay', 0) ?? 0;
        if ($inputDelay === 0) {
            return 0;
        }

        $workShift = EmployeeWorkShift::workShiftForEmployee($attendance->idemployee, $attendance->checkdate);
        if (false === empty($workShift->primaryColumnValue())) {
            $day = DateTimeTools::dayOfWeek($attendance->checkdate);
            foreach ($workShift->getPeriod($day) as $workPeriod) {
                if ($workPeriod->isInPeriod($attendance->checktime)) {
                    $where = [
                        new DataBaseWhere('idemployee', $attendance->idemployee),
                        new DataBaseWhere('kind', Attendance::KIND_INPUT),
                        new DataBaseWhere('checkdate', $attendance->checkdate),
                        new DataBaseWhere('checktime', $attendance->checktime, '<'),
                    ];

                    // If it's a modification, exclude the current attendance
                    if (false === empty($attendance->id)) {
                        $where[] = new DataBaseWhere('id', $attendance->id, '<>');
                    }

                    $previousAttendance = new Attendance();
                    if ($previousAttendance->loadFromCode('', $where)) {
                        return 0;  // there is previous attendance
                    }

                    $delay = $workPeriod->minutesInputDelayed($attendance->checktime);
                    return ($delay < $inputDelay) ? 0 : $delay;
                }
            }
        }
        return 0;
    }
}