<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Model;

use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\HumanResources\Lib\DateTimeTools;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelExtended;

/**
 * List of work hours (period) of employee
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EmployeeWorkPeriod extends ModelExtended
{
    use ModelTrait;

    /**
     * Day of the week.
     * 1 - Monday ... 7 - Sunday
     *
     * @var int
     */
    public $dayweek;

    /**
     * Time end
     *
     * @var string
     */
    public $endtime;

    /**
     * Work Shift relation field
     *
     * @var integer
     */
    public $idworkshift;

    /**
     * Time start
     *
     * @var string
     */
    public $starttime;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->dayweek = DateTimeTools::dayOfWeek();
        $this->endtime = date("H:i:s", mktime(0, 0, 0));
        $this->starttime = date("H:i:s", mktime(0, 0, 0));
    }

    /**
     * Check if the indicated time is within the period.
     * Don't check if the date is the same day of the week.
     *
     * @param string $time
     * @return bool
     */
    public function isInPeriod(string $time): bool
    {
        $start = strtotime($this->starttime);
        $end = strtotime($this->endtime);
        $check = strtotime($time);
        return $check >= $start && $check <= $end;
    }

    /**
     * Calculate the minutes delayed for an input attendance.
     *
     * @param string $time
     * @return int
     */
    public function minutesInputDelayed(string $time): int
    {
        $start = strtotime($this->starttime);
        $check = strtotime($time);
        return $check > $start ? round(($check - $start) / 60) : 0;
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
        new EmployeeWorkShift();
        return parent::install();
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'rrhh_employeesworkperiods';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        if (empty($this->dayweek)) {
            Tools::log()->warning('dayofweek-required');
            return false;
        }
        return parent::test();
    }
}
