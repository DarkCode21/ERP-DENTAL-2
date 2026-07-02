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
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\KernelException;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\OrdenProducto;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\LoadProductDataTrait;

/**
 * Class to indicate the numserie of the produced material.
 *
 * @author Jose Antonio Cuello <yopli2000@gmail.com>
 */
class OrdenNumSerie extends ModelClass
{
    use LoadProductDataTrait;
    use ModelTrait;

    public const DESTINATION = false;

    public const ORIGIN = true;

    /**
     * Primary key.
     *
     * @var int
     */
    public $id;

    /**
     * Link to delivery note model.
     *
     * @var int
     */
    public $iddelivery;

    /**
     * Link to product order line model.
     *
     * @var int
     */
    public $idline;

    /**
     * Link to product order model.
     *
     * @var int
     */
    public $idorden;

    /**
     * Link to product order model.
     * The order where the numserie is consumed.
     *
     * @var int
     */
    public $idusedinorder;

    /** @var string */
    public $numserie;

    /** @var string */
    public $reference;

    /** @var bool */
    public $verified;

    /** @var string */
    public $verifydate;

    /** @var string */
    public $verifynick;

    /**
     * Disable status control for update the NumSerie.
     *
     * @var bool
     */
    private bool $updatingNumSerie = false;

    /**
     * Reset the values of all model properties.
     */
    public function clear(): void
    {
        parent::clear();
        $this->verified = false;
    }

    /**
     * Remove the model data from the database.
     *
     * @return bool
     */
    public function delete(): bool
    {
        if ($this->getOrder()->estado > OrdenProduccion::STATUS_VERIFYING) {
            Tools::log()->error('error-order-status');
            return false;
        }

        return parent::delete();
    }

    /**
     * Return the delivery note model associated with this numserie.
     * If numserie don't have an iddelivery return an empty delivery note.
     *
     * @return AlbaranCliente
     */
    public function getDeliveryNote(): AlbaranCliente
    {
        $deliveryNote = new AlbaranCliente();
        if (false === empty($this->iddelivery)) {
            $deliveryNote->load($this->iddelivery);
        }
        return $deliveryNote;
    }

    /**
     * Returns the order model associated with this numserie.
     * Use ORIGIN constant to get the order where it was produced (default).
     * Use DESTINATION constant to get the order where it was consumed as ingredient.
     *
     * @return OrdenProduccion
     */
    public function getOrder(bool $origin = self::ORIGIN): OrdenProduccion
    {
        $order = new OrdenProduccion();
        $order->load($origin ? $this->idorden : $this->idusedinorder);
        return $order;
    }

    /**
     * Returns the order line model associated with this numserie.
     *
     * @return OrdenProducto
     */
    public function getOrderProductLine(): OrdenProducto
    {
        $line = new OrdenProducto();
        $line->load($this->idline);
        return $line;
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
        new OrdenProducto();
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
     * Stores the model data in the database.
     *
     * @return bool
     */
    public function save(): bool
    {
        if (false === $this->updatingNumSerie
            && $this->getOrder()->estado > OrdenProduccion::STATUS_VERIFYING
        ) {
            Tools::log()->error('error-order-status');
            return false;
        }

        if (parent::save()) {
            $this->updatingNumSerie = false;
            return true;
        }
        return false;
    }

    /**
     * Sets the delivery note id.
     * If current iddelivery is not empty, can't assign a new value, only unassigned.
     *
     * @param int $iddelivery
     * @return bool
     */
    public function setDelivery(int $iddelivery): bool
    {
        if (false === empty($iddelivery)
            && false === empty($this->iddelivery)
        ) {
            return false;
        }

        $value = empty($iddelivery)
            ? 'NULL'
            : $iddelivery;

        $sql = 'UPDATE ' . self::tableName()
            . ' SET iddelivery = ' . $value
            . ' WHERE id = ' . $this->id;
        return self::$dataBase->exec($sql);
    }

    /**
     * Disable control status for update numserie.
     * Only since next modal save().
     *
     * @return void
     */
    public function setUpdatingNumSerie(): void
    {
        $this->updatingNumSerie = true;
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'produccion_ordenesnumseries';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *   - checks that the numserie is not empty
     *   - checks that the reference is not empty
     *   - gets the line id from the reference. Checks that it is not empty
     *   - if verified is true, sets the verifydate and verifynick
     *
     * @return bool
     * @throws Exception
     */
    public function test(): bool
    {
        if (empty($this->reference)) {
            Tools::log()->error('missing-reference');
            return false;
        }

        $this->numserie = Tools::noHtml($this->numserie);
        if (empty($this->numserie)) {
            Tools::log()->error('missing-numserie', ['%product%' => $this->reference]);
            return false;
        }

        $this->idline = $this->getLineFromReference();
        if (empty($this->idline)) {
            Tools::log()->error('missing-production-order');
            return false;
        }

        if (false === empty($this->iddelivery) && false === empty($this->idusedinorder)) {
            Tools::log()->error('numserie-used-and-delivery');
            return false;
        }

        if (empty($this->verified)) {
            $this->verifydate = null;
            $this->verifynick = null;
        } elseif (empty($this->verifydate)) {
            $this->verifydate = Tools::dateTime();
            $this->verifynick = Session::user()->nick;
        }

        return parent::test();
    }

    /**
     * Get the line id from the reference.
     *
     * @return int
     */
    private function getLineFromReference(): int
    {
        $where = [
            new DataBaseWhere('idorden', $this->idorden),
            new DataBaseWhere('referencia', $this->reference),
        ];
        $line = new OrdenProducto();
        return $line->loadWhere($where)
            ? $line->id
            : 0;
    }
}
