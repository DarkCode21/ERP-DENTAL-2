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
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\CustomerReceiptGroup;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Dinamic\Model\Subcuenta;

/**
 * Class for generate accounting of customer receipt grouping.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class CustomerReceiptGroupToAccounting extends ReceiptGroupToAccounting
{

    /**
     * Parent document.
     *
     * @var CustomerReceiptGroup
     */
    protected $document;

    /**
     *
     * @var Agente
     */
    protected $agent = null;

    /**
     * Add to the accounting entry the total payment line.
     *
     * @param Asiento $entry
     * @return bool
     */
    protected function addHeaderLine(Asiento $entry): bool {
        $debit = $this->getBasicLine($entry, $this->bankAccount, true, $this->document->total);
        $debit->concepto = substr($entry->concepto, 0, 255);
        return $debit->save();
    }

    /**
     * Main process that adds the detail of the accounting entry.
     * Generates a line for each receipt or a line for the total
     * of the customer if grouping by customer is activated.
     *
     * @param Asiento $entry
     * @return bool
     */
    protected function addLines(Asiento $entry, $groupreceipts): bool {
        if (false === parent::addLines($entry, $groupreceipts)) {
            return false;
        }

        if (isset($this->agent) && false === empty($this->agent->codagente)) {
            if (false === $this->addLinesDifference($entry, $this->bankAccount)) {
                return false;
            }
        }

        if (false === $this->addLinesBankChecks($entry, $this->bankAccount)) {
            return false;
        }

        return true;
    }

    /**
     * Add to the accounting entry the list of receipts grouped
     * by customer code.
     *
     * @param Asiento $entry
     * @return bool
     */
    protected function addLinesAgrupped(Asiento $entry): bool
    {
        $customer = null;
        $subaccount = null;
        $receiptCodes = '';
        $total = 0.00;
        $receiptList = $this->document->getReceipts(['codcliente' => 'ASC']);

        foreach ($receiptList as $receipt) {
            if (empty($customer) || $customer->codcliente != $receipt->codcliente) {
                if (false === empty($customer)) {
                    $credit = $this->getNewLineForCustomer(
                        $entry, $subaccount, $customer->nombre, $receiptCodes, $total
                    );
                    if (false === $credit->save()) {
                        return false;
                    }
                }
                $customer = $receipt->getSubject();
                $subaccount = $this->getCustomerAccount($customer);
                if (false === $subaccount->exists()) {
                    Tools::log()->warning('customer-account-not-found');
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

        // process the last accumulated customer.
        if (false === empty($receiptList)) {
            $credit = $this->getNewLineForCustomer(
                $entry, $subaccount, $customer->nombre, $receiptCodes, $total
            );
            return $credit->save();
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
            $customer = $receipt->getSubject();
            $subaccount = $this->getCustomerAccount($customer);
            if (false === $subaccount->exists()) {
                Tools::log()->warning('customer-account-not-found');
                return false;
            }

            $credit = $this->getNewLineForCustomer(
                $entry, $subaccount, $customer->nombre, $receipt->getCode(), $receipt->importe
            );
            if (false === $credit->save()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Obtains the ledger subaccount for the posting of the bank or cashier.
     *
     * @param string $idbank
     * @return Subcuenta
     */
    protected function getBankAccount(string $idbank): Subcuenta
    {
        if (false === isset($this->agent)) {
            $this->agent = $this->document->getAgent();
        }

        if (false === empty($this->agent->codsubaccount_wallet)) {
            return $this->getSubAccount($this->agent->codsubaccount_wallet);
        }

        return parent::getBankAccount($idbank);
    }

    /**
     * Returns the concept for the accounting entry
     *
     * @return string
     */
    protected function getConcept(): string
    {
        return Tools::lang()->trans('multiple-charge') . ': ' . $this->document->id;
    }

    /**
     *
     * @param Asiento $entry
     * @param Subcuenta $subaccount
     * @return bool
     */
    private function addLinesBankChecks(Asiento $entry, Subcuenta $subaccount): bool
    {
        foreach ($this->document->getBankChecks() as $bankCheck) {
            if (empty($bankCheck->codsubaccount)) {
                continue;
            }

            $paymentSubaccount = $this->getSubAccount($bankCheck->codsubaccount);
            if (empty($paymentSubaccount->codsubcuenta)) {
                Tools::log()->warning('subaccount-not-found', ['%subAccountCode%' => $bankCheck->codsubaccount]);
                return false;
            }

            $customer = $bankCheck->getCustomer();
            $concept = Tools::lang()->trans('bank-check')
                . ': '
                . $bankCheck->name
                . ' - '
                . $customer->nombre;

            $debit = $this->getBasicLine($entry, $paymentSubaccount, true, $bankCheck->total);
            $debit->concepto = substr($concept, 0, 255);
            if (false === $debit->save()) {
                return false;
            }

            $credit = $this->getBasicLine($entry, $subaccount, false, $bankCheck->total);
            $credit->concepto = substr($concept, 0, 255);
            if (false === $credit->save()) {
                return false;
            }
        }
        return true;
    }

    /**
     *
     * @param Asiento $entry
     * @param Subcuenta $subaccount
     * @return bool
     */
    private function addLinesDifference(Asiento $entry, Subcuenta $subaccount): bool
    {
        $settlement = $this->document->getSettlement();
        if ($settlement->difference == 0.00) {
            return true;
        }

        if (empty($this->agent->codsubaccount_difference)) {
            Tools::log()->warning(
                'subaccount-not-informed',
                ['%group%' => Tools::lang()->trans('difference')]
            );
            return false;
        }

        $differenceSubaccount = $this->getSubAccount($this->agent->codsubaccount_difference);
        if (empty($differenceSubaccount->codsubcuenta)) {
            Tools::log()->warning(
                'subaccount-not-informed',
                ['%group%' => Tools::lang()->trans('difference')]
            );
            return false;
        }

        $concept = $this->getConcept()
            . ' (' . Tools::lang()->trans('difference') . ')'
            . ' - ' . $this->agent->nombre;

        $debit = $this->getBasicLine($entry, $differenceSubaccount, true, (-1 * $settlement->difference));
        $debit->concepto = substr($concept, 0, 255);
        if (false === $debit->save()) {
            return false;
        }

        $credit = $this->getBasicLine($entry, $subaccount, false, (-1 * $settlement->difference));
        $credit->concepto = substr($concept, 0, 255);
        if (false === $credit->save()) {
            return false;
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
    private function getNewLineForCustomer(Asiento $entry, Subcuenta $subaccount, string $name, string $documents, float $total)
    {
        $line = $this->getBasicLine($entry, $subaccount, false, $total);
        $concept = Tools::lang()->trans('customer-payment-concept', ['%document%' => $documents])
            . ' - '
            . $name;
        $line->concepto = substr($concept, 0, 255);
        return $line;
    }
}
