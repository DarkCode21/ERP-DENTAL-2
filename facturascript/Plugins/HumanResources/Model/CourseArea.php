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
 * List the training areas of the courses.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class CourseArea extends ModelExtended
{

    use ModelTrait;

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
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'rrhh_coursesareas';
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
        return parent::url($type, 'ListCourseTraining?activetab=' . $list);
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
