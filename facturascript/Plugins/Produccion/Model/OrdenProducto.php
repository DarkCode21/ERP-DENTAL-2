<?php
/**
 * This file is part of the Produccion plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 * All Rights Reserved.
 */

namespace FacturaScripts\Plugins\Produccion\Model;

use Exception;
use FacturaScripts\Core\KernelException;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\LoadProductDataTrait;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\VariantRelationTrait;

/**
 * Class to indicate produced material.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class OrdenProducto extends ModelClass
{
    use ModelTrait;
    use VariantRelationTrait;
    use LoadProductDataTrait;

    /**
     * The Amount of raw material required.
     *
     * @var float
     */
    public $cantidad;

    /**
     * Primary key.
     *
     * @var int
     */
    public $id;

    /**
     * Link to product order model.
     *
     * @var int
     */
    public $idorden;

    /**
     * Indicates the type of serial number calculation.
     *
     * @var int
     */
    public $numserietype;

    /**
     * Reset the values of all model properties.
     */
    public function clear(): void
    {
        parent::clear();
        $this->cantidad = 1.00;
        $this->numserietype = NumSerieCounter::NS_TYPE_NONE;
    }

    /**
     * Remove the model data from the database.
     *
     * @return bool
     */
    public function delete(): bool
    {
        if (false === $this->getOrder()->canDelete()) {
            Tools::log()->error('error-order-status');
            return false;
        }

        return parent::delete();
    }

    /**
     * Returns the production order.
     *
     * @return OrdenProduccion
     */
    public function getOrder(): OrdenProduccion
    {
        $order = new OrdenProduccion();
        $order->load($this->idorden);
        return $order;
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
        new OrdenProduccion();
        return parent::install();
    }

    /**
     * Assign the values of the $data array to the model properties.
     *
     * @param array $data
     * @param array $exclude
     * @throws KernelException
     */
    public function loadFromData(array $data = [], array $exclude = []): void
    {
        parent::loadFromData($data, $exclude);
        if (self::$loadProductData) {
            $this->loadProductData();
        }
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
        return 'produccion_ordenesproductos';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     * @throws Exception
     */
    public function test(): bool
    {
        if (empty($this->idorden)) {
            Tools::log()->error('missing-production-order');
            return false;
        }

        if (is_null($this->numserietype)) {
            $this->numserietype = NumSerieCounter::NS_TYPE_NONE;
        }

        $this->referencia = Tools::noHtml($this->referencia);
        return parent::test();
    }

    /**
     * Stores the model data in the database.
     *
     * @return bool
     */
    public function save(): bool
    {
        $save = [OrdenProduccion::STATUS_PENDING, OrdenProduccion::STATUS_STARTED];
        if (false === in_array($this->getOrder()->estado, $save)) {
            Tools::log()->error('error-order-status');
            return false;
        }

        return parent::save();
    }
}
