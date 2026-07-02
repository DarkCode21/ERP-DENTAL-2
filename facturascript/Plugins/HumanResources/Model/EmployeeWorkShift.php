<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\HumanResources\Lib\DateTimeTools;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelExtended;

/**
 * List of work shift of employee
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EmployeeWorkShift extends ModelExtended
{

    use ModelTrait;

    /**
     * Employee relation field
     *
     * @var integer
     */
    public $idemployee;

    /**
     * Date start
     *
     * @var string
     */
    public $startdate;

    /**
     * Date end
     *
     * @var string
     */
    public $enddate;

    /**
     * Hours to work on Monday
     *
     * @var float
     */
    public $monday;

    /**
     * Hours to work on Tuesday
     *
     * @var float
     */
    public $tuesday;

    /**
     * Hours to work on Wednesday
     *
     * @var float
     */
    public $wednesday;

    /**
     * Hours to work on Thursday
     *
     * @var float
     */
    public $thursday;

    /**
     * Hours to work on Friday
     *
     * @var float
     */
    public $friday;

    /**
     * Hours to work on Saturday
     *
     * @var float
     */
    public $saturday;

    /**
     * Hours to work on Sunday
     *
     * @var float
     */
    public $sunday;

    /**
     * Returns the work shift for the indicated employee and date.
     *
     * @param int $idemployee
     * @param string $date
     * @return EmployeeWorkShift
     */
    public static function workShiftForEmployee(int $idemployee, string $date): EmployeeWorkShift
    {
        $where = [
            new DataBaseWhere('idemployee', $idemployee),
            new DataBaseWhere('startdate', $date, '<='),
            new DataBaseWhere('enddate', $date, '>='),
            new DataBaseWhere('enddate', null, 'IS', 'OR'),
        ];

        $orderBy = ['startdate' => 'ASC', 'enddate' => 'ASC'];

        $workShift = new EmployeeWorkShift();
        $workShift->loadFromCode('', $where, $orderBy);
        return $workShift;
    }

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->startdate = date('d-m-Y');
        $this->monday = 0.00;
        $this->tuesday = 0.00;
        $this->wednesday = 0.00;
        $this->thursday = 0.00;
        $this->friday = 0.00;
        $this->saturday = 0.00;
        $this->sunday = 0.00;
    }

    /**
     * Check if the indicated date is within the period of the work shift
     *
     * @param string $date
     * @return bool
     */
    public function dateInWorkShift(string $date): bool
    {
        $error = empty($this->id)
            || (strtotime($date) < strtotime($this->startdate))
            || (!empty($this->enddate) && strtotime($date) > strtotime($this->enddate));
        return !$error;
    }

    /**
     * Returns the number of hours to work for the indicated date
     *
     * @param string $date
     * @return float
     */
    public function getHoursToWork($date): float
    {
        $dow = DateTimeTools::dayOfWeek($date);
        switch ($dow) {
            case 1:
                return $this->monday;
            case 2:
                return $this->tuesday;
            case 3:
                return $this->wednesday;
            case 4:
                return $this->thursday;
            case 5:
                return $this->friday;
            case 6:
                return $this->saturday;
            case 7:
                return $this->sunday;
        }
        return 0.00;
    }

    /**
     * Gets the work hours for a day into work shift.
     *
     * @param int $day
     * @return EmployeeWorkPeriod[]
     */
    public function getPeriod(int $day): array
    {
        $result = [];
        $where = [
            new DataBaseWhere('idworkshift', $this->id),
            new DataBaseWhere('dayweek', $day),
        ];
        $order = ['starttime' => 'ASC' ];

        foreach (EmployeeWorkPeriod::all($where, $order, 0, 0) as $row) {
            $result[] = $row;
        }
        return $result;
    }

    /**
     * This function is called when creating the model table. Returns the SQL
     * that will be executed after the creation of the table. Useful to insert values
     * default.
     *
     * @return string
     */
    public function install(): string
    {
        new Employee();
        parent::install();

        return '';
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'rrhh_employeesworkshifts';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        if ($this->errorInPeriod($this->startdate, $this->enddate) ||
            $this->errorInWorkShift())
        {
            return false;
        }
        return parent::test();
    }

    /**
     * Returns the url where to see / modify the data.
     *
     * @param string $type
     * @param string $list
     *
     * @return string
     */
    public function url(string $type = 'auto', string $list = 'List'): string
    {
        $list = 'EditEmployee?code=' . $this->idemployee . '&activetab=List';
        return parent::url($type, $list);
    }

    /**
     * Check hours of work shift.
     *
     * @return bool
     */
    private function errorInWorkShift(): bool
    {
        $values = [
            $this->monday, $this->tuesday, $this->wednesday, $this->thursday,
            $this->friday, $this->saturday, $this->sunday,
        ];
        foreach ($values as $hours) {
            if ($hours < 0 || $hours > 24) {
                Tools::log()->warning('workshift-out-range');
                return true;
            }
        }
        return false;
    }
}
