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
use FacturaScripts\Core\KernelException;
use FacturaScripts\Core\Lib\ListFilter\BaseFilter;
use FacturaScripts\Core\Lib\ListFilter\PeriodFilter;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelReport;
use FacturaScripts\Plugins\HumanResources\Model\Employee;
use FacturaScripts\Plugins\HumanResources\Model\Attendance;
use FacturaScripts\Plugins\HumanResources\Model\Report\Data\AttendanceIncidenceData;

/**
 * Class to manage and report employee incidence
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AttendanceIncidenceReport extends ModelReport
{

    /**
     * Starting date of the period to be listed
     *
     * @var string
     */
    private $startdate;

    /**
     * Final date of the period to be listed
     *
     * @var string
     */
    private $enddate;

    /**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->startdate = date('d-m-Y');
        $this->enddate = null;
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
     * @throws KernelException
     */
    public function all(array $filters, array $where, array $order, int $offset, int $limit): array
    {
        /// Get date filter
        $this->startdate = $filters['date']->getValue(PeriodFilter::START_DATE_ID);
        if (empty($this->startdate)) {
            Tools::log()->warning('no-period-report');
            return [];
        }

        $this->enddate = $filters['date']->getValue(PeriodFilter::END_DATE_ID);
        if (empty($this->enddate)) {
            $this->enddate = $this->startdate;
        }

        /// Get other filters
        $filterWhere = [];
        $idemployee = $filters['employee']->getValue();
        if (!empty($idemployee)) {
            $filterWhere[] = new DataBaseWhere('id', $idemployee);
        }
        $filters['status']->getDataBaseWhere($filterWhere);
        return $this->incidencesInPeriod($filterWhere, $order);
    }

    /**
     *
     * @param DataBaseWhere[] $where
     * @param array $order
     * @return AttendanceIncidenceData[]
     * @throws KernelException
     */
    public function incidencesInPeriod(array $where, array $order = ['id' => 'ASC']): array
    {
        $result = [];

        /// Select the list of employees to list
        foreach (Employee::all($where, $order, 0, 0) as $employee) {
            $incidence = new AttendanceIncidenceData($employee->id, $employee->nombre);

            $data = $this->calculateEntriesDays($employee->id);
            $incidence->totaldays = (int) $data['totaldays'];
            $incidence->entries = (int) $data['entries'];
            $incidence->delays = (int) $data['delays'];
            $incidence->justified = (int) $data['justified'];
            $incidence->withoutrest = $incidence->entries > 0 ? $this->calculateNoRest($employee->id) : 0;
            $incidence->holidays = $this->calculateHolidays($employee->id);
            $incidence->leaves =  $this->calculateLeaves($employee->id);

            $result[] = $incidence;
            unset($incidence);
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

    /**
     *
     * @param int $idemployee
     * @return array
     * @throws KernelException
     */
    private function calculateEntriesDays(int $idemployee): array
    {
        $fields = 'COUNT(DISTINCT checkdate) totaldays,'
            . ' COUNT(checkdate) entries,'
            . ' SUM(CASE WHEN inputdelay > 0 THEN 1 ELSE 0 END) delays,'
            . ' SUM(CASE WHEN origin = ' . Attendance::ORIGIN_JUSTIFIED . ' THEN 1 ELSE 0 END) justified';

        $sql = 'SELECT ' . $fields
            . ' FROM ' . Attendance::tableName()
            . ' WHERE idemployee = ' . $idemployee
            . ' AND kind = ' . Attendance::KIND_INPUT
            . ' AND checkdate BETWEEN ' . self::$dataBase->var2str($this->startdate) . ' AND ' . self::$dataBase->var2str($this->enddate);

        $data = self::$dataBase->select($sql);
        if (empty($data)) {
            return [
                'totaldays' => 0,
                'entries' => 0,
                'delays' => 0,
                'justified' => 0,
            ];
        }
        return $data[0];
    }

    /**
     *
     * @param int $idemployee
     * @return int
     * @throws KernelException
     */
    private function calculateHolidays(int $idemployee): int
    {
       $sql = 'SELECT SUM(totaldays) days'
            . ' FROM rrhh_employeesholidays'
            . ' WHERE idemployee = ' . $idemployee
            . ' AND startdate BETWEEN ' . self::$dataBase->var2str($this->startdate) . ' AND ' . self::$dataBase->var2str($this->enddate);

        $data = self::$dataBase->select($sql);
        return empty($data) ? 0 : (int)$data[0]['days'];
    }

    /**
     *
     * @param int $idemployee
     * @return int
     * @throws KernelException
     */
    private function calculateLeaves(int $idemployee): int
    {
       $sql = 'SELECT SUM(totaldays) days'
            . ' FROM rrhh_employeesleaves'
            . ' WHERE idemployee = ' . $idemployee
            . ' AND startdate BETWEEN ' . self::$dataBase->var2str($this->startdate) . ' AND ' . self::$dataBase->var2str($this->enddate);

        $data = self::$dataBase->select($sql);
        return empty($data) ? 0 : (int)$data[0]['days'];
    }

    /**
     *
     * @param int $idemployee
     * @return integer
     * @throws KernelException
     */
    private function calculateNoRest(int $idemployee): int
    {
        $sql1 = 'SELECT checkdate, COUNT(*)'
            . ' FROM ' . Attendance::tableName()
            . ' WHERE idemployee = ' . $idemployee
            . ' AND kind = ' . Attendance::KIND_INPUT
            . ' AND checkdate BETWEEN ' . self::$dataBase->var2str($this->startdate) . ' AND ' . self::$dataBase->var2str($this->enddate)
            . ' GROUP BY checkdate'
            . ' HAVING COUNT(*) < 2';

        $sql2 = 'SELECT COUNT(*) days FROM (' . $sql1 . ') noRest';
        $data = self::$dataBase->select($sql2);
        return empty($data) ? 0 : $data[0]['days'];
    }
}
