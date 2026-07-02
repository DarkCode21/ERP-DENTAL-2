<?php
/**
 * This file is part of PagosMultiples plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * PagosMultiples Copyright (C) 2020-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\PagosMultiples\Lib\Accounting;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Subcuenta;
use FacturaScripts\Dinamic\Model\SupplierReceiptGroup;

/**
 * Class for generate accounting of supplier receipt grouping.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class SupplierReceiptGroupToAccounting extends ReceiptGroupToAccounting
{

    /**
     * Parent document.
     *
     * @var SupplierReceiptGroup
     */
    protected $document;

    /**
     * Add to the accounting entry the total payment line.
     *
     * @param Asiento $entry
     * @return bool
     */
    protected function addHeaderLine(Asiento $entry): bool {
        $debit = $this->getBasicLine($entry, $this->bankAccount, false, $this->document->total);
        $debit->concepto = $entry->concepto;
        return $debit->save();
    }

    /**
     * Returns the concept for the accounting entry
     *
     * @return string
     */
    protected function getConcept(): string
    {
        return Tools::lang()->trans('multiple-payment') . ': ' . $this->document->id;
    }

    /**
     * Add to the accounting entry the list of receipts grouped
     * by supplier code.
     *
     * @param Asiento $entry
     * @return bool
     */
    protected function addLinesAgrupped(Asiento $entry): bool
    {
        $supplier = null;
        $subaccount = null;
        $receiptCodes = '';
        $total = 0.00;
        $receiptList = $this->document->getReceipts(['codproveedor' => 'ASC']);

        foreach ($receiptList as $receipt) {
            if (empty($supplier) || $supplier->codproveedor != $receipt->codproveedor) {
                if (false === empty($supplier)) {
                    $debit = $this->getNewLineForSupplier(
                        $entry, $subaccount, $supplier->nombre, $receiptCodes, $total
                    );
                    if (false === $debit->save()) {
                        return false;
                    }
                }
                $supplier = $receipt->getSubject();
                $subaccount = $this->getSupplierAccount($supplier);
                if (false === $subaccount->exists()) {
                    Tools::log()->warning('supplier-account-not-found');
                    return false;
                }
                $total = 0.00;
                $receiptCodes = '';
                $comma = '';
            }
            $total += $receipt->importe;
            $receiptCodes .= $comma . $receipt->getCode();
            $comma = ', ';
        }

        // process the last accumulated supplier.
        if (false === empty($receiptList)) {
            $debit = $this->getNewLineForSupplier(
                $entry, $subaccount, $supplier->nombre, $receiptCodes, $total
            );
            return $debit->save();
        }
        return true;
    }

    /**
     * Add the list of receipts to the accounting entry without grouping them.
     *
     * @param Asiento $entry
     * @return bool
     */
    protected function addLinesWithoutGrouping(Asiento $entry): bool
    {
        foreach ($this->document->getReceipts() as $receipt) {
            $supplier = $receipt->getSubject();
            $subaccount = $this->getSupplierAccount($supplier);
            if (false === $subaccount->exists()) {
                Tools::log()->warning('supplier-account-not-found');
                return false;
            }

            $debit = $this->getNewLineForSupplier(
                $entry, $subaccount, $supplier->nombre, $receipt->getCode(), $receipt->importe
            );
            if (false === $debit->save()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Obtains an accounting entry line for customer receipts
     * with the default data.
     *
     * @param Asiento $entry
     * @param Subcuenta $subaccount
     * @param string $name
     * @param string $documents
     * @param float $total
     * @return Partida
     */
    private function getNewLineForSupplier(Asiento $entry, Subcuenta $subaccount, string $name, string $documents, float $total)
    {
        $line = $this->getBasicLine($entry, $subaccount, true, $total);
        $concept = Tools::lang()->trans('supplier-payment-concept', ['%document%' => $documents])
            . ' - '
            . $name;
        $line->concepto = substr($concept, 0, 255);
        return $line;
    }
}
