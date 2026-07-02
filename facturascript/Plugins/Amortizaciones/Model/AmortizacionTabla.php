<?php
/**
 * This file is part of Amortizaciones plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Amortizaciones  Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\Amortizaciones\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;

/**
 * Amortizaciones Tabla List model
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AmortizacionTabla extends ModelClass
{

    public const GROUP_TYPE_SIMPLE = 1;
    public const GROUP_TYPE_NORMAL = 2;

    public const GROUP_INVESTMENTS = 1;
    public const GROUP_CIVIL = 2;
    public const GROUP_CENTRAL = 3;
    public const GROUP_BUILDINGS = 4;
    public const GROUP_FACILITIES = 5;
    public const GROUP_TRANSPORT = 6;
    public const GROUP_FURNITURE = 7;
    public const GROUP_COMPUTER = 8;

    use ModelTrait;

    /** @var float */
    public $coefficient;

    /** @var int */
    public $groupid;

    /** @var string */
    public $groupdesc;

    /** @var int */
    public $grouptype;

    /** @var string */
    public $grouptypedesc;

    /** @var string */
    public $name;

    /** @var int */
    public $period;

    /**
     * Primary key.
     *
     * @var int
     */
    public $id;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->coefficient = 0;
        $this->period = 0;
        $this->groupid = 0;
        $this->groupdesc = '';
        $this->grouptype = 0;
        $this->grouptypedesc = '';
    }

    /**
     *
     * @param int $id
     * @return string
     */
    public static function groupTypeDescription(int $id): string
    {
        return [
            Tools::lang()->trans('simplified-direct-estimation'),
            Tools::lang()->trans('normal-direct-estimate'),
        ][$id - 1];
    }

    /**
     *
     * @param int $id
     * @return string
     */
    public static function groupDescription(int $id): string
    {
        return [
            Tools::lang()->trans('type-investments-assets'),
            Tools::lang()->trans('civil-works'),
            Tools::lang()->trans('centrals'),
            Tools::lang()->trans('buildings'),
            Tools::lang()->trans('facilities'),
            Tools::lang()->trans('transport-elements'),
            Tools::lang()->trans('furniture-fixtures'),
            Tools::lang()->trans('electronic-computer'),
        ][$id - 1];
    }

    /**
     * Assign the values of the $data array to the model properties.
     *
     * @param array $data
     * @param array $exclude
     */
    public function loadFromData(array $data = [], array $exclude = [])
    {
        parent::loadFromData($data, $exclude);
        $this->grouptypedesc = $this->groupTypeDescription($this->grouptype) ?? '';
        $this->groupdesc = $this->groupDescription($this->groupid) ?? '';
    }

    /**
     * Returns the name of the column that is the model's primary key.
     *
     * @return string
     */
    public static function primaryColumn(): string
    {
        return 'id';
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'amortizaciones_tablas';
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
        return parent::url($type, 'ListAmortizacion?activetab=' . $list);
    }
}
