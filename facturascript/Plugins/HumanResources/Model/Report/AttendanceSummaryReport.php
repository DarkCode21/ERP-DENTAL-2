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
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\AttendanceIncidence;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\AttendanceReportTrait;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelReport;
use FacturaScripts\Plugins\HumanResources\Model\Employee;
use FacturaScripts\Plugins\HumanResources\Model\Report\Data\AttendanceSummaryData;

/**
 * Class to calculate employee attendance summary
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AttendanceSummaryReport extends ModelReport
{

    use AttendanceReportTrait;

    /**
     * Class for management of incidences
     *
     * @var AttendanceIncidence
     */
    private $incidenceModel;

    /**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->incidenceModel = new AttendanceIncidence();
    }

    /**
     * Execute the load data for report
     *
     * @param BaseFilter[] $filters
     * @param DataBaseWhere[] $where
     * @param array $order
     * @param int $offset
     * @param int $limit
     * @return ModelReport[]
     */
    public function all($filters, $where, $order, $offset, $limit): array
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

        /// Get others filters
        $filterWhere = [];
        $filters['status']->getDataBaseWhere($filterWhere);

        $idemployee = $filters['employee']->getValue();
        if ($idemployee > 0) {
            $filterWhere[] = new DataBaseWhere('id', $filters['employee']->getValue());
        }

        return $this->attendanceSummary($startdate, $enddate, $filterWhere, $order);
    }

    /**
     * Detailed report and with totals on the attendance of
     * the employees in a period of time
     *
     * @param string $startdate
     * @param string $enddate
     * @param DataBaseWhere[] $where
     * @param array $order
     * @return AttendanceSummaryData[]
     */
    public function attendanceSummary($startdate, $enddate, $where, $order = ['id' => 'ASC']): array
    {
        $result = [];
        $employeeModel = new Employee();
        foreach ($employeeModel->all($where, $order, 0, 0) as $employee) {
            $summary = new AttendanceSummaryData($employee->id, $employee->nombre, $startdate);
            $day = $this->processEmployeeAttendances(
                $employee,
                $summary,
                $startdate,
                $enddate,
                AttendanceSummaryData::ATT_SUMMARY_MAXDAYS
            );
            $this->fixDaysValues($summary, $day + 1);
            $result[] = $summary;
        }
        return $result;
    }

    /**
     * @return string
     */
    public function url(): string
    {
        return 'ReportAttendance';
    }
}
