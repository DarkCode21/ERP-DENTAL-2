<?php
/**
 * Copyright (C) 2019-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PlazosPago\Lib;

use FacturaScripts\Core\Lib\ReceiptGenerator as ParentLib;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Dinamic\Model\ReciboProveedor;
use FacturaScripts\Plugins\PlazosPago\Model\FormaPago;
use FacturaScripts\Plugins\PlazosPago\Model\FormaPagoPlazo;

/**
 * Description of ReceiptGenerator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ReceiptGenerator extends ParentLib
{
    protected function getPaymentMethod(string $codpago): FormaPago
    {
        $paymentMethod = new FormaPago();
        $paymentMethod->loadFromCode($codpago);
        return $paymentMethod;
    }

    /** @return FormaPagoPlazo[] */
    protected function getPaymentTerms(string $codpago): array
    {
        return $this->getPaymentMethod($codpago)->getPlazos();
    }

    /**
     * @param FacturaCliente $invoice
     * @param int $number
     * @param float $amount
     * @param string $expiration
     *
     * @return bool
     */
    protected function newCustomerReceipt($invoice, $number, $amount, string $expiration = ''): bool
    {
        if (empty($expiration)) {
            return parent::newCustomerReceipt($invoice, $number, $amount);
        }

        $newReceipt = new ReciboCliente();
        $newReceipt->codcliente = $invoice->codcliente;
        $newReceipt->coddivisa = $invoice->coddivisa;
        $newReceipt->codpago = $invoice->codpago;
        $newReceipt->fecha = $invoice->fecha;
        $newReceipt->idempresa = $invoice->idempresa;
        $newReceipt->idfactura = $invoice->idfactura;
        $newReceipt->importe = $amount;
        $newReceipt->nick = $invoice->nick;
        $newReceipt->numero = $number;
        $newReceipt->setExpiration($expiration);
        $newReceipt->disableInvoiceUpdate(true);
        return $newReceipt->save();
    }

    /**
     * @param FacturaProveedor $invoice
     * @param int $number
     * @param float $amount
     * @param string $expiration
     *
     * @return bool
     */
    protected function newSupplierReceipt($invoice, $number, $amount, string $expiration = ''): bool
    {
        if (empty($expiration)) {
            return parent::newSupplierReceipt($invoice, $number, $amount);
        }

        $newReceipt = new ReciboProveedor();
        $newReceipt->codproveedor = $invoice->codproveedor;
        $newReceipt->coddivisa = $invoice->coddivisa;
        $newReceipt->codpago = $invoice->codpago;
        $newReceipt->fecha = $invoice->fecha;
        $newReceipt->idempresa = $invoice->idempresa;
        $newReceipt->idfactura = $invoice->idfactura;
        $newReceipt->importe = $amount;
        $newReceipt->nick = $invoice->nick;
        $newReceipt->numero = $number;
        $newReceipt->setExpiration($expiration);
        $newReceipt->disableInvoiceUpdate(true);
        return $newReceipt->save();
    }

    /**
     * @param FacturaCliente $invoice
     *
     * @return bool
     */
    protected function updateCustomerReceipts($invoice): bool
    {
        $receipts = $invoice->getReceipts();
        $terms = $this->getPaymentTerms($invoice->codpago);
        if (count($receipts) > 0) {
            return parent::updateCustomerReceipts($invoice);
        }

        // calculate outstanding amount
        $amount = $this->getOutstandingAmount($receipts, $invoice->total);

        $number = 1;
        $pending = $amount;
        foreach ($terms as $term) {
            $partialAmount = round($amount * $term->aplazado / 100, FS_NF0);
            $pending -= $partialAmount;
            if (abs($pending) < 1) {
                $partialAmount += $pending;
                $pending = 0;
            }

            $expiration = $term->getExpiration($invoice->fecha);
            if (!$this->newCustomerReceipt($invoice, $number, $partialAmount, $expiration)) {
                return false;
            }

            $number++;
        }

        // pending amount?
        return $this->isCero($pending) || $this->newCustomerReceipt($invoice, $number, $pending);
    }

    /**
     * @param FacturaProveedor $invoice
     *
     * @return bool
     */
    protected function updateSupplierReceipts($invoice): bool
    {
        $receipts = $invoice->getReceipts();
        $terms = $this->getPaymentTerms($invoice->codpago);
        if (count($receipts) > 0) {
            return parent::updateSupplierReceipts($invoice);
        }

        // calculate outstanding amount
        $amount = $this->getOutstandingAmount($receipts, $invoice->total);

        $number = 1;
        $pending = $amount;
        foreach ($terms as $term) {
            $partialAmount = round($amount * $term->aplazado / 100, FS_NF0);
            $pending -= $partialAmount;
            if (abs($pending) < 1) {
                $partialAmount += $pending;
                $pending = 0;
            }

            $expiration = $term->getExpiration($invoice->fecha);
            if (!$this->newSupplierReceipt($invoice, $number, $partialAmount, $expiration)) {
                return false;
            }

            $number++;
        }

        // pending amount?
        return $this->isCero($pending) || $this->newSupplierReceipt($invoice, $number, $pending);
    }
}
