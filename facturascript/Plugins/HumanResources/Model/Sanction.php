<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Model;

use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelExtended;
use FacturaScripts\Core\Model\Base\ModelTrait;

/**
 * List of Sanction Concepts
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class Sanction extends ModelExtended
{

    use ModelTrait;

    /** Description of sanction
     *
     * @var string
     */
    public $name;

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'rrhh_sanctions';
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
        return parent::url($type, 'ListBasicData?activetab=' . $list);
    }
}
