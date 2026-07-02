<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\Accounting;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Plugins\HumanResources\Model\EmployeeVoucher;

/**
 * Class for the generation of accounting entries of employees vouchers payments.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class VoucherPaidToAccounting extends VoucherToAccounting
{
    /**
     *
     * @var EmployeeVoucher
     */
    private $voucher;

    /**
     *
     * @param Asiento $entry
     * @return bool
     */
    protected function addLines($entry): bool
    {
        $debit = $this->getNewLine($entry, 'prepayment', 'payment', false);
        $credit = $this->getNewLine($entry, 'payment', 'prepayment', true);
        return ($debit->save() && $credit->save());
    }

    /**
     * Perform the initial checks to continue with the accounting process
     *
     * @return bool
     */
    protected function initialChecks(): bool
    {
        /// Add to payment model (document), needed parent fields
        $this->voucher = $this->document->getVoucher();
        $this->document->idcompany = $this->voucher->idcompany;
        $this->document->channel = $this->voucher->channel;
        $this->document->name = $this->voucher->name;
        return parent::initialChecks();
    }

    /**
     * Returns the concept for the accounting entry
     *
     * @return string
     */
    protected function getConcept(): string
    {
       $employee = $this->voucher->getEmployee();

       return Tools::lang()->trans('payment') . ': '
            . $this->voucher->name . ' - '
            . $employee->nombre;
    }
}
