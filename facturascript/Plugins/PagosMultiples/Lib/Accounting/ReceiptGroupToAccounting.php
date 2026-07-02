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
use FacturaScripts\Dinamic\Model\CuentaBanco;
use FacturaScripts\Dinamic\Model\PaymentReceiptGroup;
use FacturaScripts\Dinamic\Model\Subcuenta;
use FacturaScripts\Dinamic\Model\Serie;

/**
 * Class base for generate accounting of receipt grouping.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
abstract class ReceiptGroupToAccounting extends AccountingClass
{

    /**
     * Main bank subaccount for total payment.
     *
     * @var Subcuenta
     */
    protected $bankAccount;

    /**
     * Parent document.
     *
     * @var PaymentReceiptGroup
     */
    protected $document;

    /**
     * Add to the accounting entry the total payment line.
     *
     * @param Asiento $entry
     * @return bool
     */
    abstract protected function addHeaderLine(Asiento $entry): bool;

    /**
     * Add the list of receipts to the accounting entry grouping them.
     *
     * @param Asiento $entry
     * @return bool
     */
    abstract protected function addLinesAgrupped(Asiento $entry): bool;

    /**
     * Add the list of receipts to the accounting entry without grouping them.
     *
     * @param Asiento $entry
     * @return bool
     */
    abstract protected function addLinesWithoutGrouping(Asiento $entry): bool;

    /**
     * Returns the concept for the accounting entry
     *
     * @return string
     */
    abstract protected function getConcept(): string;

    /**
     * Method to launch the accounting process.
     *
     * @param CustomerReceiptGroup $model
     */
    public function generate($model) {
        parent::generate($model);

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
            if (false === $this->addLines($accountEntry, $this->document->groupreceipts)) {
                return false;
            }

            // block new accounting entry
            $accountEntry->editable = false;
            $accountEntry->save();

            // link account with document
            $this->document->identry = $accountEntry->idasiento;
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
     * @param bool $groupreceipts
     * @return bool
     */
    protected function addLines(Asiento $entry, bool $groupreceipts): bool
    {
        if (false === $this->addHeaderLine($entry)) {
            return false;
        }

        if (true === $groupreceipts && false === $this->addLinesAgrupped($entry)) {
            return false;
        }

        if (false === $groupreceipts && false === $this->addLinesWithoutGrouping($entry)) {
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
    protected function getBankAccount(string $idbank): Subcuenta
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
     * Obtains an accounting entry with the default data.
     *
     * @return Asiento
     */
    protected function getNewEntry()
    {
        $entry = new Asiento();
        $entry->codejercicio = $this->exercise->codejercicio;
        $entry->idempresa = $this->exercise->idempresa;
        $entry->fecha = $this->document->groupdate;
        $entry->importe = $this->document->total;

        $entry->concepto = empty($entry->concepto)
            ? $this->getConcept()
            : $this->document->concept;

        if (false === empty($this->document->idserie)) {
            $serie = new Serie();
            $serie->loadFromCode($this->document->idserie);
            $entry->canal = $serie->canal;
        }

        return $entry;
    }

    /**
     *
     * @return bool
     */
    protected function initialCheck(): bool
    {
        if (false === $this->loadExerciseForDate($this->document->idcompany, $this->document->groupdate)) {
            return false;
        }

        $this->bankAccount = $this->getBankAccount($this->document->idbank);
        if (false === $this->bankAccount->exists()) {
            Tools::log()->warning(
                'subaccount-not-informed',
                ['%group%' => Tools::lang()->trans('bank-account')]
            );
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
