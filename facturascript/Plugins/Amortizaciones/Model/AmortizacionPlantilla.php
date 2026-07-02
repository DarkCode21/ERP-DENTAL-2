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
 * Amortization Template model
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AmortizacionPlantilla extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $benefits_subaccount;

    /** @var string */
    public $closing_subaccount;

    /** @var string */
    public $credit_subaccount;

    /** @var string */
    public $debit_subaccount;

    /**
     * Primary key.
     *
     * @var int
     */
    public $id;

    /** @var string */
    public $loss_subaccount;

    /** @var string */
    public $name;

    /** @var int */
    public $periods;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->periods = 0;
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
     * Returns the name of the column that describes the model, such as name, description...
     *
     * @return string
     */
    public function primaryDescriptionColumn(): string
    {
        return 'name';
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'amortizaciones_plantillas';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        $this->name = Tools::noHtml($this->name);
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
        return parent::url($type, 'ListAmortizacion?activetab=' . $list);
    }
}
