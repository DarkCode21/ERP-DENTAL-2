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
 * List of disciplinary sanctions of the employee
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EmployeeSanction extends ModelExtended
{

    /*
     * NOTICE Field: possible values
     */
    public const NOTICE_NONE = 0;
    public const NOTICE_WRITTEN = 1;
    public const NOTICE_VERBAL = 2;

    /*
     * SANCTION Field: possible values
     */
    public const SANCTION_MILD = 0;
    public const SANCTION_MEDIUM = 1;
    public const SANCTION_SEVERE = 2;
    public const SANCTION_VERY_SEVERE = 3;

    use ModelTrait;

    /**
     * Employee relation field
     *
     * @var integer
     */
    public $idemployee;

    /**
     * Link to Disciplinary Offense Model
     *
     * @var int
     */
    public $idoffense;

    /**
     * Link to Santion Model
     *
     * @var int
     */
    public $idsanction;

    /**
     *
     * @var int
     */
    public $level;

    /**
     *
     * @var string
     */
    public $note;

    /**
     *
     * @var int
     */
    public $notice;

    /**
     *
     * @var string
     */
    public $notification;

    /**
     *
     * @var string
     */
    public $startdate;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->startdate = date(self::DATE_STYLE);
        $this->notice = self::NOTICE_NONE;
        $this->level = self::SANCTION_MILD;
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
        new DisciplinaryOffense();
        new Sanction();
        new Employee();
        return parent::install();
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'rrhh_employeessanctions';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        if (empty($this->notification)) {
            $this->notice = self::NOTICE_NONE;
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
