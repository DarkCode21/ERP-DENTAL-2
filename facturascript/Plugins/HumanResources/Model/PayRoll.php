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
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelExtended;

/**
 * List of payroll payments to employees
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class PayRoll extends ModelExtended
{

    use ModelTrait;

    /**
     * Date of creation of the payroll
     *
     * @var string
     */
    public $creationdate;

    /**
     * End date of the period to liquidate
     *
     * @var string
     */
    public $enddate;

    /** @var string */
    public $name;

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
     * Notes about the payroll
     *
     * @var string
     */
    public $note;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->creationdate = date('d-m-Y');
        $this->idcompany = Tools::settings('default', 'idempresa');
        $this->enddate = date('d-m-Y');
        $this->startdate = date('d-m-Y');
    }

    /**
     * Remove the model data from the database.
     *
     * @return bool
     */
    public function delete(): bool
    {
        if (parent::delete()) {
            $where = [ new DataBaseWhere('idpayroll', $this->id) ];
            $payrollaccounting = new PayRollAccounting();
            foreach ($payrollaccounting->all($where, [], 0, 0) as $row) {
                if (!$row->delete()) {
                    return false;
                }
            }
           return true;
        }
        return false;
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
        new Empresa();
        parent::install();

        return '';
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
        return 'rrhh_payroll';
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
        return parent::url($type, 'ListEmployee?activetab=' . $list);
    }
}
