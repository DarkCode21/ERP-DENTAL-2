<?php
/**
 * This file is part of PagosMultiples plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * PagosMultiples Copyright (C) 2020-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\PagosMultiples\Lib\Accounting;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Accounting\AccountingClass;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\CustomerBankCheck;
use FacturaScripts\Dinamic\Model\CuentaBanco;
use FacturaScripts\Dinamic\Model\CustomerReceiptGroup;
use FacturaScripts\Dinamic\Model\Subcuenta;

/**
 * Class for generate accounting of customer bank check.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class BankCheckToAccounting extends AccountingClass
{
    /**
     * Parent document.
     *
     * @var CustomerBankCheck
     */
    protected $document;

    /**
     *
     * @var CustomerReceiptGroup
     */
    protected $multiple;

    /**
     *
     * @var bool
     */
    protected $refund = false;

    /**
     * Method to launch the accounting process.
     *
     * @param CustomerBankCheck $model
     * @param bool $refund
     */
    public function generateAccounting(CustomerBankCheck $model, bool $refund = false): bool {
        $this->refund = $refund;
        return $this->generate($model);
    }

    /**
     * Method to launch the accounting process.
     *
     * @param CustomerBankCheck $model
     */
    public function generate($model) {
        parent::generate($model);
        $this->multiple = $model->getReceiptGroup();

        if (false === $this->initialCheck()) {
            return false;
        }

        $dataBase = new DataBase();
        $newTransation = false === $dataBase->inTransaction() && $dataBase->beginTransaction();
        try {
            $accountEntry = $this->getNewEntry();
            if (false === $accountEntry->save()) {
                return false;
            }
            if (false === $this->addLines($accountEntry)) {
                return false;
            }

            $this->document->identry = $this->refund ? null : $accountEntry->idasiento;
            if (false === $this->document->save()) {
                return false;
            }
            if ($newTransation) {
                $dataBase->commit();
            }
            return true;
        } finally {
            if ($newTransation) {
                $dataBase->rollback();
            }
        }
    }

    /**
     * Main process that adds the detail of the accounting entry.
     * Generates a line for each receipt or a line for the total
     * of the customer if grouping by customer is activated.
     *
     * @param Asiento $entry
     * @return bool
     */
    private function addLines(Asiento $entry): bool
    {
        $paymentSubaccount = $this->getBankAccount($this->document->idbank);
        if (empty($paymentSubaccount->codsubcuenta)) {
            return false;
        }

        $customerSubaccount = $this->getSubAccount($this->document->codsubaccount);
        if (empty($customerSubaccount->codsubcuenta)) {
            return false;
        }

        $line1 = $this->getBasicLine($entry, $paymentSubaccount, (false === $this->refund), $this->document->total);
        if (false === $line1->save()) {
            return false;
        }

        $line2 = $this->getBasicLine($entry, $customerSubaccount, $this->refund, $this->document->total);
        if (false === $line2->save()) {
            return false;
        }

        return true;
    }

    /**
     * Obtains the ledger subaccount for the posting of the bank or cashier.
     *
     * @param string $idbank
     * @return Subcuenta
     */
    private function getBankAccount(string $idbank): Subcuenta
    {
        $bank = new CuentaBanco();
        if (empty($idbank)
            || false === $bank->loadFromCode($idbank)
            || empty($bank->codsubcuenta))
        {
            return $this->getSpecialSubAccount(self::SPECIAL_PAYMENT_ACCOUNT);
        }

        return $this->getSubAccount($bank->codsubcuenta);
    }
    /**
     *
     * @return string
     */
    private function getConcept()
    {
        $customer = $this->document->getCustomer();
        $concept = $this->refund
            ? Tools::lang()->trans('check-refund')
            : Tools::lang()->trans('check-payment');

        $result = $concept
            . ': '
            . $this->document->name
            . ' - '
            . $customer->nombre;

        return substr($result, 0, 255);
    }

    /**
     * Obtains an accounting entry with the default data.
     *
     * @return Asiento
     */
    private function getNewEntry()
    {
        $entry = new Asiento();
        $entry->codejercicio = $this->exercise->codejercicio;
        $entry->idempresa = $this->exercise->idempresa;
        $entry->concepto = $this->getConcept();
        $entry->fecha = $this->document->charged;
        $entry->importe = $this->document->total;
        $entry->editable = false;
        return $entry;
    }

    /**
     *
     * @return bool
     */
    private function initialCheck(): bool
    {
        if (false === $this->loadExerciseForDate($this->multiple->idcompany, $this->document->charged)) {
            return false;
        }

        return true;
    }

    /**
     * Calculate and verify the accounting year.
     *
     * @param int $idcompany
     * @param date $date
     * @return bool
     */
    private function loadExerciseForDate($idcompany, $date) {
        $this->exercise->idempresa = $idcompany;
        if (false === $this->exercise->loadFromDate($date, false)) {
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
