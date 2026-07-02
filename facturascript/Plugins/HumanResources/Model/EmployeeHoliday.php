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
use FacturaScripts\Plugins\HumanResources\Lib\DateTimeTools;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelExtended;

/**
 * List of holidays for employee
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EmployeeHoliday extends ModelExtended
{

    use ModelTrait;

    /**
     * indicates if the record is authorized.
     *
     * @var bool
     */
    public $authorized;

    /**
     * Indicates if the calculation of days is automatic
     * or the user has entered the value
     *
     * @var boolean
     */
    public $automatic;
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
     * Total days
     *
     * @var integer
     */
    public $totaldays;

    /**
     * Note and long descriptions
     *
     * @var string
     */
    public $note;

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
        return 'rrhh_employeesholidays';
    }

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->totaldays = 0;
        $this->authorized = true;
        $this->automatic = true;
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        if ($this->errorInPeriod($this->startdate, $this->enddate)) {
            return false;
        }

        if (is_null($this->automatic)) {
            $this->automatic = true;
        }

        if ($this->automatic) {
            $this->totaldays = DateTimeTools::daysBetween($this->startdate, $this->enddate, true);
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
        return parent::url($type, 'ListEmployee?activetab=' . $list);
    }
}
