<?php
/**
 * This file is part of PagosMultiples plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * PagosMultiples  Copyright (C) 2020-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\PagosMultiples\Model\Base;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\PagoCliente;
use FacturaScripts\Dinamic\Model\PagoProveedor;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Dinamic\Model\ReciboProveedor;

/**
 * Model class base for multiple payment of receipts.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
abstract class PaymentReceiptGroup extends ModelClass
{
    public const STATUS_PENDING = 0;
    public const STATUS_CHARGED = 1;

    /**
     * Accounting entry concept.
     *
     * @var string
     */
    public $concept;

    /**
     * Primary Key of the model
     *
     * @var integer
     */
    public $id;

    /**
     * Link to bank account model.
     *
     * @var integer
     */
    public $idbank;

    /**
     * Link to company model.
     *
     * @var integer
     */
    public $idcompany;

    /**
     * Link to currency model.
     *
     * @var string
     */
    public $idcurrency;

    /**
     * Link to accounting entry model.
     *
     * @var integer
     */
    public $identry;

    /**
     * Lint to serie model.
     *
     * @var string
     */
    public $idserie;

    /**
     * Indicates if the amounts of the same client have to be grouped.
     *
     * @var boolean
     */
    public $groupreceipts;

    /**
     * Date of grouping or multiple payment.
     *
     * @var string
     */
    public $groupdate;

    /**
     * Indicates if no accounting entry has to be generated.
     *
     * @var boolean
     */
    public $noentry;

    /**
     * Notes or observations.
     *
     * @var string
     */
    public $notes;

    /**
     * Currency rate conversion.
     *
     * @var float
     */
    public $rateconv;

    /**
     * Record count of grouping.
     *
     * @var int
     */
    public $receipts;

    /**
     * Indicates the status of the group: Pending, Charged, etc.
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
     * Get a new payment and load his data.
     *
     * @return PagoCliente|PagoProveedor
     */
    abstract public function getPayment($code = 0);

    /**
     * Get a new receipt and load his data.
     *
     * @return ReciboCliente|ReciboProveedor
     */
    abstract public function getReceipt($code);

    /**
     * Get the list of all the receipts included in the multiple payment.
     */
    abstract public function getReceipts(array $orderby = []): array;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->idcompany = Tools::settings('default', 'idempresa');
        $this->idcurrency = Tools::settings('default', 'coddivisa');
        $this->groupreceipts = false;
        $this->groupdate = date(self::DATE_STYLE);
        $this->noentry = false;
        $this->rateconv = 1.0;
        $this->receipts = 0;
        $this->status = self::STATUS_PENDING;
        $this->total = 0.00;
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
        new Asiento();
        return parent::install();
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
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        $this->concept = Tools::noHtml($this->concept);
        $this->notes = Tools::noHtml($this->notes);
        return parent::test();
    }

    /**
     * Calculate total from receipts list.
     */
    public function updateTotal()
    {
        if (false === $this->exists()) {
            return;
        }

        $total = 0.0;
        $count = 0;
        foreach ($this->getReceipts() as $receipt) {
            $total += $receipt->importe;
            $count++;
        }

        if ($total != $this->total || $count != $this->receipts) {
            $this->total = $total;
            $this->receipts = $count;
            $this->save();
        }
    }
}
