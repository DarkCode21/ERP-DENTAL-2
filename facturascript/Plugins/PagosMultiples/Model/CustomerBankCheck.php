<?php
/**
 * This file is part of PagosMultiples plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * PagosMultiples  Copyright (C) 2020-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\PagosMultiples\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\CustomerReceiptGroup;

/**
 * Class that manages the data model of the bank check of customer receipts group.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class CustomerBankCheck extends ModelClass
{

    public const STATUS_PENDING = 10;
    public const STATUS_EXPIRED = 20;
    public const STATUS_CHARGED = 90;

    use ModelTrait;

    /**
     * Charged date.
     *
     * @var string
     */
    public $charged;

    /**
     * Subaccount for effects wallet.
     *
     * @var string
     */
    public $codsubaccount;

    /**
     * Delivered date.
     *
     * @var string
     */
    public $delivered;

    /**
     * Payment due date.
     *
     * @var string
     */
    public $expiration;

    /**
     * Primary Key of the model
     *
     * @var integer
     */
    public $id;

    /**
     * Link to bank accounts model.
     *
     * @var string
     */
    public $idbank;

    /**
     * Link to customer model.
     *
     * @var string
     */
    public $idcustomer;

    /**
     * Link to accounting entry model.
     *
     * @var integer
     */
    public $identry;

    /**
     * Link to customer receipts group model.
     *
     * @var integer
     */
    public $idmultiple;

    /**
     * Human identifier for the payment.
     *
     * @var string
     */
    public $name;

    /**
     *
     * @var int
     */
    public $status;

    /**
     * Total amount of grouping.
     *
     * @var double
     */
    public $total;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->delivered = date(self::DATE_STYLE);
        $this->total = 0.00;
    }

    /**
     * Get customer.
     *
     * @return Cliente
     */
    public function getCustomer(): Cliente
    {
        $customer = new Cliente();
        $customer->loadFromCode($this->idcustomer);
        return $customer;
    }

    /**
     * Get parent receipt group.
     *
     * @return CustomerReceiptGroup
     */
    public function getReceiptGroup(): CustomerReceiptGroup
    {
        $group = new CustomerReceiptGroup();
        $group->loadFromCode($this->idmultiple);
        return $group;
    }

    /**
     * Assign the values of the $data array to the model properties.
     *
     * @param array $data
     * @param array $exclude
     */
    public function loadFromData(array $data = array(), array $exclude = array())
    {
        parent::loadFromData($data, $exclude);
        $this->status = $this->getStatus();
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
     * Descriptive identifier for humans of the data record
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
        return 'ppmm_customer_bankchecks';
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

        if (empty($this->codsubaccount)) {
            $this->charged = $this->delivered;
        } elseif (empty($this->identry)) {
            $this->charged = null;
        }

        if (empty($this->idbank)) {
            $this->idbank =  $this->getReceiptGroup()->idbank;
        }

        return parent::test();
    }

    /**
     * Returns the url where to see / modify the data.
     *
     * @param string $type
     * @param string $list
     * @return string
     */
    public function url(string $type = 'auto', string $list = 'List'): string
    {
        $list = 'EditCustomerReceiptGroup?code=' . $this->idmultiple . '&active=List';
        return parent::url($type, $list);
    }

    /**
     * Obtains the status of the payment based on different conditions.
     *    - If there is an accounting entry or there is no expiration date, it is charged.
     *    - If the expiration date is less or equal than the current date, it is expired.
     *    - In any other case it is pending.
     *
     * return int;
     */
    private function getStatus()
    {
        if (false === empty($this->identry) || empty($this->expiration)) {
            return self::STATUS_CHARGED;
        }

        $currentDate = date('Y-m-d');
        if (strtotime($this->expiration) <= strtotime($currentDate)) {
            return self::STATUS_EXPIRED;
        }

        return self::STATUS_PENDING;
    }
}
