<?php
/**
 * This file is part of PagosMultiples plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * PagosMultiples  Copyright (C) 2020-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\PagosMultiples\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Dinamic\Model\PagoProveedor;
use FacturaScripts\Dinamic\Model\ReciboProveedor;

/**
 * Class that manages the data model of the supplier receipts group.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class SupplierReceiptGroup extends Base\PaymentReceiptGroup
{

    use ModelTrait;

    /**
     * Get a new receipt and load his data.
     *
     * @param int $code
     * @return PagoProveedor
     */
    public function getPayment($code = 0): PagoProveedor
    {
        $payment = new PagoProveedor();
        if (false === empty($code)) {
            $payment->loadFromCode($code);
        }
        return $payment;
    }

    /**
     * Get a new receipt and load his data.
     *
     * @param int $code
     * @return ReciboProveedor
     */
    public function getReceipt($code = 0): ReciboProveedor
    {
        $receipt = new ReciboProveedor();
        if (false === empty($code)) {
            $receipt->loadFromCode($code);
        }
        return $receipt;
    }

    /**
     * Get the list of all the receipts included in the multiple payment.
     *
     * @param array $orderby
     * @return ReciboProveedor[]
     */
    public function getReceipts(array $orderby = []): array
    {
        $receipt = new ReciboProveedor();
        $where = [new DataBaseWhere('idmultiple', $this->id)];
        return $receipt->all($where, $orderby, 0, 0);
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'ppmm_supplier_receipts';
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
        return parent::url($type, 'ListFacturaProveedor?activetab=' . $list);
    }
}
