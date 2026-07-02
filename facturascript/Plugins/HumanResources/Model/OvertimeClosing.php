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
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelExtended;

/**
 * List of Overtime Closing to employees
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class OvertimeClosing extends ModelExtended
{
    use ModelTrait;

    /**
     * End date of the period to liquidate
     *
     * @var string
     */
    public $enddate;

    /**
     * Start date of the period to liquidate
     *
     * @var string
     */
    public $startdate;

    /**
     * Link to company model
     *
     * @var integer
     */
    public $idcompany;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->idcompany = Tools::settings('default', 'idempresa');
        $this->enddate = date('d-m-Y');
    }

    /**
     * Returns the previous closing date.
     *
     * @return string
     */
    public function previousClosingDate(): string
    {
        $where = [
            new DataBaseWhere('idcompany', $this->idcompany),
            new DataBaseWhere('enddate', $this->startdate, '<')
        ];
        $closing = new self();
        if (false === $closing->loadFromCode('', $where, ['enddate' => 'DESC'])) {
            return date('01-01-Y');
        }
        return $closing->enddate;
    }

    /**
     * This function is called when creating the model table. Returns the SQL
     * that will be executed after the creation of the table. Useful to insert values
     * default.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'rrhh_overtimeclosing';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        if ($this->errorInPeriod($this->startdate, $this->enddate, true)) {
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
        return parent::url($type, 'ListAttendance?activetab=' . $list);
    }
}