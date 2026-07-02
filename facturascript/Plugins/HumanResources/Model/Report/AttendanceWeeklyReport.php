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
use FacturaScripts\Plugins\HumanResources\Lib\DateTimeTools;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\AttendanceReportTrait;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelReport;
use FacturaScripts\Plugins\HumanResources\Model\Employee;
use FacturaScripts\Plugins\HumanResources\Model\EmployeeWorkShift;
use FacturaScripts\Plugins\HumanResources\Model\Report\Data\AttendanceWeeklyData;

/**
 * Class to calculate employee attendance weekly
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AttendanceWeeklyReport extends ModelReport
{

    use AttendanceReportTrait;

    public function all($filters, $where, $order, $offset, $limit): array {
        return [];
    }

    /**
     *
     * @param string $startdate
     * @param int $idemployee
     * @return AttendanceWeeklyData
     */
    public function attendanceWeeklyForEmployee($startdate, $idemployee)
    {
        // load attendance data
        $where = [ new DataBaseWhere('id', $idemployee) ];
        $result = $this->attendanceWeekly($startdate, $where)[0];

        // load work periods
        $mondayDate = DateTimeTools::mondayFromDate($startdate);
        $workshift = new EmployeeWorkShift();
        for ($day = 0; $day < 7; ++$day) {
            $date = date('d-m-Y', strtotime('+' . $day .' days', strtotime($mondayDate)));
            if (false === $this->checkWorkShift($workshift, $idemployee, $date)) {
                continue;
            }
            $result->workperiods[$day] = $workshift->getPeriod($day + 1);
        }
        return $result;
    }

    /**
     *
     * @param string $startdate
     * @param DataBaseWhere[] $where
     * @param array $order
     * @return AttendaceWeeklyData[]
     */
    public function attendanceWeekly($startdate, $where, $order = ['id' => 'ASC']): array
    {
        $result = [];
        $mondayDate = DateTimeTools::mondayFromDate($startdate);
        $sundayDate = date('d-m-Y', strtotime('+7 days', strtotime($mondayDate)));

        $employeeModel = new Employee();
        foreach ($employeeModel->all($where, $order, 0, 0) as $employee) {
            $summary = new AttendanceWeeklyData($employee->id, $employee->nombre, $mondayDate);
            $day = $this->processEmployeeAttendances(
                $employee,
                $summary,
                $mondayDate,
                $sundayDate,
                AttendanceWeeklyData::ATT_MAXDAYS,
                false
            );
            $this->fixDaysValues($summary, $day + 1);
            $result[] = $summary;
       }
        return $result;
    }
}
