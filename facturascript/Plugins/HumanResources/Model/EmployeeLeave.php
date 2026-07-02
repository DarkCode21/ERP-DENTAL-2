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
 * List of temporary employee sick leave
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EmployeeLeave extends ModelExtended
{
    use ModelTrait;

    const CAUSE_ACCIDENT = 1;
    const CAUSE_DISEASE = 2;
    const CAUSE_OTHERS = 99;

    /**
     * Type of claculation.
     * 1 -> accident
     * 2 -> disease
     *
     * @var integer
     */
    public $cause;

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
     * Notes and long descriptions
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
        return 'rrhh_employeesleaves';
    }

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->startdate = date('d-m-Y');
        $this->cause = self::CAUSE_ACCIDENT;
        $this->totaldays = 0;
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

        $this->totaldays = DateTimeTools::daysBetween($this->startdate, $this->enddate, ($this->cause == self::CAUSE_DISEASE));
        return parent::test();
    }
}
