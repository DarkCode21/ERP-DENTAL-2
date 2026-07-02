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
 * List of contract of employee
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EmployeeContract extends ModelExtended
{

    use ModelTrait;

    /**
     * Company relation field
     *
     * @var integer
     */
    public $idcompany;

    /**
     * Employee relation field
     *
     * @var integer
     */
    public $idemployee;

    /**
     * Contract relation field
     *
     * @var integer
     */
    public $idcontract;

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
        new Contract();
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
        return 'rrhh_employeescontracts';
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
        return parent::test();
    }

    /**
     * Returns a list of fields to verify that they do not have html code
     *
     * @return array
     */
    protected function noHtmlFields(): array
    {
        return ['note'];
    }
}
