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
use FacturaScripts\Dinamic\Lib\Accounting\AccountingClass;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Plugins\HumanResources\Model\EmployeeVoucher;
use FacturaScripts\Plugins\HumanResources\Model\EmployeeVoucherPaid;

/**
 * Class for the generation of accounting entries of employees vouchers.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class VoucherToAccounting extends AccountingClass
{

    /** @var EmployeeVoucher|EmployeeVoucherPaid */
    protected $document;

    const SPECIAL_PREPAYMENT_ACCOUNT = 'ANPAGO';

    /**
     *
     * @var array
     */
    protected $accounts = [];

    /**
     * Method to launch the accounting process
     *
     * @param EmployeeVoucher|EmployeeVoucherPaid $model
     */
    public function generate($model)
    {
        parent::generate($model);
        if (!$this->initialChecks()) {
            return;
        }

        $accountEntry = $this->getNewEntry();
        if ($accountEntry->save() && $this->addLines($accountEntry)) {
            $accountEntry->editable = false;
            $accountEntry->save();
            $this->document->identry = $accountEntry->idasiento;
            return;
        }

        /// Error, delete saved info
        Tools::log()->warning('accounting-entry-error');
        $accountEntry->delete();
    }

    /**
     *
     * @param Asiento $entry
     * @return bool
     */
    protected function addLines($entry): bool
    {
        $debit = $this->getNewLine($entry, 'payment', 'prepayment', false);
        $credit = $this->getNewLine($entry, 'prepayment', 'payment', true);
        return ($debit->save() && $credit->save());
    }

    /**
     * Perform the initial checks to continue with the accounting process
     *
     * @return bool
     */
    protected function initialChecks(): bool
    {
        /// Set company and fiscal exercise
        if (!$this->loadExerciseForDate($this->document->idcompany, $this->document->startdate)) {
            return false;
        }

        /// Set accounts
        if (!$this->loadAccounts()) {
            return false;
        }

        return true;
    }

    /**
     * Returns the concept for the accounting entry
     *
     * @return string
     */
    protected function getConcept(): string
    {
       $employee = $this->document->getEmployee();
       return Tools::lang()->trans('voucher') . ': '
            . $this->document->name . ' - '
            . $employee->nombre;
    }

    /**
     *
     * @return Asiento
     */
    protected function getNewEntry(): Asiento
    {
        $entry = new Asiento();
        $entry->canal = $this->document->channel;
        $entry->iddiario = Tools::settings('rrhh', 'journal');
        $entry->codejercicio = $this->exercise->codejercicio;
        $entry->idempresa = $this->exercise->idempresa;
        $entry->fecha = $this->document->startdate;
        $entry->concepto = $this->getConcept();
        $entry->importe = $this->document->amount;
        return $entry;
    }

    /**
     *
     * @param Asiento $entry
     * @param string $account
     * @param string $offsetting
     * @param bool $isDebit
     * @return Partida
     */
    protected function getNewLine($entry, $account, $offsetting, $isDebit): Partida
    {
        $line = $this->getBasicLine($entry, $this->accounts[$account], $isDebit, $this->document->amount);
        $line->idcontrapartida = $this->accounts[$offsetting]->idsubcuenta;
        $line->codcontrapartida = $this->accounts[$offsetting]->codsubcuenta;
        $line->concepto = $entry->concepto;
        return $line;
    }

    /**
     * Search and load subaccount data for accounting entry
     *
     * @return bool
     */
    private function loadAccounts(): bool
    {
        $result = true;
        $this->accounts['prepayment'] = $this->getSpecialSubAccount(self::SPECIAL_PREPAYMENT_ACCOUNT);
        if (empty($this->accounts['prepayment']->idsubcuenta)) {
            Tools::log()->warning('subaccount-not-found', ['%subAccountCode%' => self::SPECIAL_PREPAYMENT_ACCOUNT]);
            $result = false;
        }

        $this->accounts['payment'] = $this->getSpecialSubAccount(self::SPECIAL_PAYMENT_ACCOUNT);
        if (empty($this->accounts['payment']->idsubcuenta)) {
            Tools::log()->warning('subaccount-not-found', ['%subAccountCode%' => self::SPECIAL_PAYMENT_ACCOUNT]);
            $result = false;
        }

        return $result;
    }

    /**
     *
     * @param int $idcompany
     * @param string $date
     * @return bool
     */
    protected function loadExerciseForDate($idcompany, $date): bool
    {
        $this->exercise->idempresa = $idcompany;
        if (!$this->exercise->loadFromDate($date, false)) {
            Tools::log()->warning('exercise-not-found');
            return false;
        }

        if (!$this->exercise->isOpened()) {
            Tools::log()->warning('closed-exercise', ['%exerciseName%' => $this->exercise->codejercicio]);
            return false;
        }
        return true;
    }
}
