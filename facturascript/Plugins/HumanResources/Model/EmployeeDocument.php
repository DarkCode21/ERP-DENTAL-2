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
use FacturaScripts\Dinamic\Model\AttachedFileRelation;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelExtended;

/**
 * Model for Documents of the employee
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EmployeeDocument extends ModelExtended
{
    use ModelTrait;

    /**
     * Indicates if the document is downloadable by the employee.
     *
     * @var bool
     */
    public $downloadable;

    /**
     * Indicate the date on which the document expires.
     *
     * @var string
     */
    public $expires;

    /**
     * Link to Employee Model
     *
     * @var int
     */
    public $idemployee;

    /**
     * Link to Document Type Model
     *
     * @var int
     */
    public $iddoctype;

    /**
     * Human significative text
     *
     * @var string
     */
    public $note;

    /**
     *
     * @var integer
     */
    public $year_group;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->downloadable = false;
        $this->year_group = date('Y');
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
        return parent::install();
    }

    /**
     *
     * @return AttachedFileRelation
     */
    public function getFile()
    {
        $where = [
            new DataBaseWhere('model', 'EmployeeDocument'),
            new DataBaseWhere('modelid', $this->id),
        ];
        $file = new AttachedFileRelation();
        $file->loadFromCode('', $where);
        return $file;
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'rrhh_employeesdocs';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        if (false === is_null($this->expires) && empty($this->expires)) {
            $this->expires = null;
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
