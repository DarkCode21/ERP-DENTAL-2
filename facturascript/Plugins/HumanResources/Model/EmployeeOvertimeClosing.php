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
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelExtended;

/**
 * Overtime closing for an employee.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EmployeeOvertimeClosing extends ModelExtended
{
    use ModelTrait;

    public const COMPENSATION_NONE = 0;
    public const COMPENSATION_MONETARY = 1;
    public const COMPENSATION_HOLIDAY = 2;

    /**
     * Indicates the compensation mode.
     * 0: Not compensated
     * 1: Monetary compensation
     * 2: Holiday compensation
     *
     * @var integer
     */
    public $compensation;

    /**
     * If compensation is holiday, this field indicates the end date of the holiday.
     * (date - time)
     * @var string
     */
    public $enddate;

    /**
     * If compensation is holiday, this field link to the attendance record that starts the holiday.
     *
     * @var integer
     */
    public $idattendace_start;

    /**
     * If compensation is holiday, this field link to the attendance record that ends the holiday.
     *
     * @var integer
     */
    public $idattendace_end;

    /**
     * Employee relation field.
     *
     * @var integer
     */
    public $idemployee;

    /**
     * Overtime Closing relation field.
     *
     * @var integer
     */
    public $idclosing;

    /**
     * Total hours of overtime to compensate.
     *
     * @var float
     */
    public $overtime;

    /**
     * If compensation is holiday, this field indicates the start date of the holiday.
     * (date - time)
     * @var string
     */
    public $startdate;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->compensation = self::COMPENSATION_NONE;
        $this->overtime = 0.00;
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
        new OvertimeClosing();
        return parent::install();
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'rrhh_employeesovertimeclosing';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        if ($this->compensation === self::COMPENSATION_HOLIDAY) {
            if ($this->errorInPeriod($this->startdate, $this->enddate, true)) {
                return false;
            }
        } else {
            $this->startdate = null;
            $this->enddate = null;
        }

        return parent::test();
    }

    /**
     * Returns the url where to see / modify the data.
     *
     * @param string $type
     * @param string $list
     * @return string
     */
    public function url(string $type = 'auto', string $list = 'List'): string
    {
        $list = 'EditOvertimeClosing?code=' . $this->idclosing . '&active=List';
        return parent::url($type, $list);
    }
}