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
 * Amortizacion Subcuenta List model
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AmortizacionSubcuenta extends ModelClass
{

    public const GROUP_TYPE_INTANGIBLE = 1;
    public const GROUP_TYPE_MATERIAL = 2;
    public const GROUP_TYPE_REAL_ESTATE = 3;
    public const GROUP_TYPE_IN_PROGRESS = 4;

    public const GROUP_CLOSING = 1;
    public const GROUP_DEBIT = 2;
    public const GROUP_CREDIT = 3;
    public const GROUP_LOST = 4;
    public const GROUP_BENEFITS = 5;

    use ModelTrait;

    /** @var string */
    public $code;

    /** @var int */
    public $groupid;

    /** @var string */
    public $groupdesc;

    /** @var int */
    public $grouptype;

    /** @var string */
    public $grouptypedesc;

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
        $this->groupid = 0;
        $this->groupdesc = '';
        $this->grouptype = 0;
        $this->grouptypedesc = '';
    }

    /**
     *
     * @return array
     */
    public static function groupTypeList(): array
    {
        $i18n = Tools::lang();
        return [
            self::GROUP_TYPE_INTANGIBLE => $i18n->trans('intangible-assets'),
            self::GROUP_TYPE_MATERIAL => $i18n->trans('material-immobilizations'),
            self::GROUP_TYPE_REAL_ESTATE => $i18n->trans('immobilization-real-estate'),
            self::GROUP_TYPE_IN_PROGRESS => $i18n->trans('material-immobilizations-in-progress'),
        ];
    }

    /**
     *
     * @param int $id
     * @return string
     */
    public static function groupTypeDescription(int $id): string
    {
        return self::groupTypeList()[$id] ?? '';
    }

    /**
     *
     * @return array
     */
    public static function groupList(): array
    {
        $i18n = Tools::lang();
        return [
            self::GROUP_CLOSING => $i18n->trans('subaccount-closing'),
            self::GROUP_DEBIT => $i18n->trans('subaccount-debit'),
            self::GROUP_CREDIT => $i18n->trans('subaccount-credit'),
            self::GROUP_LOST => $i18n->trans('subaccount-lost'),
            self::GROUP_BENEFITS => $i18n->trans('subaccount-benefits'),
        ];
    }

    /**
     *
     * @param int $id
     * @return string
     */
    public static function groupDescription(int $id): string
    {
        return self::groupList()[$id] ?? '';
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
        return 'amortizaciones_subcuentas';
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
