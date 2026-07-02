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
 * List the training courses.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class CourseTraining extends ModelExtended
{

    use ModelTrait;

    /**
     * Link to the area of the course model.
     *
     * @var int
     */
    public $idarea;

    /**
     * Link to the cost of the course model.
     *
     * @var int
     */
    public $idcost;

    /**
     * Link to the method of the course model.
     *
     * @var int
     */
    public $idmethod;

    /**
     * Link to the objective of the course model.
     *
     * @var int
     */
    public $idobjective;

    /**
     * Description of area
     *
     * @var string
     */
    public $name;

    /**
     * Notes area of the course
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
        new CourseArea();
        new CourseCost();
        return parent::install();
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'rrhh_coursestrainings';
    }

    /**
     * Returns a list of fields to verify that they do not have html code
     *
     * @return array
     */
    protected function noHtmlFields(): array
    {
        return array_merge(parent::noHtmlFields(), ['note']);
    }
}
