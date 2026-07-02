<?php
/**
 * This file is part of Amortizaciones plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Amortizaciones  Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\Amortizaciones\Lib\Accounting;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Lib\Accounting\AccountingClass;
use FacturaScripts\Dinamic\Model\Amortizacion;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\FacturaCliente;

/**
 * Class for the generation of accounting entry to sell product with amortization.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AmortizationSellToAccounting extends AccountingClass
{

    /** @var string */
    protected $codSubAccount;

    /** @var Amortizacion */
    protected $document;

    /** @var FacturaCliente */
    protected $invoice;

    /**
     * Contabilize the sell of the product with amortization.
     *
     * @param Amortizacion $model
     * @param string $codSubAccount
     * @return bool
     */
    public static function exec(Amortizacion $model, string $codSubAccount): bool
    {
        $accounting = new self();
        $accounting->codSubAccount = $codSubAccount;
        $accounting->invoice = $model->getSellInvoice();
        return $accounting->generate($model);
    }

    /**
     * Method to launch the accounting process
     *
     * @param Amortizacion $model
     * @return bool
     */
    public function generate($model): bool
    {
        parent::generate($model);

        if (false === $this->initialCheck()) {
            return false;
        }

        $dataBase = new DataBase();
        $newTransation = false === $dataBase->inTransaction() && $dataBase->beginTransaction();
        try {
            // main process. Create accounting entry and lines.
            $accountEntry = $this->getNewEntry();
            if (false === $accountEntry->save()
                || false === $this->addLines($accountEntry))
            {
                return false;
            }

            // block new accounting entry
            $accountEntry->editable = false;
            $accountEntry->save();

            // link account entry with a document
            $this->document->idasientofinvida = $accountEntry->idasiento;
            $this->document->fechafinvidautil = $accountEntry->fecha;
            if (false === $this->document->save()) {
                return false;
            }
            if ($newTransation) {
                $dataBase->commit();
            }
            return true;
        } finally {
            if ($newTransation && $dataBase->inTransaction()) {
                $dataBase->rollback();
            }
        }
    }

    /**
     * Add the accounting entry lines.
     *   - lines for amortization liquidation:
     *      - buy
     *      - total amortization
     *      - sell.
     *   - Line for benefit or lost.
     *
     * @param Asiento $entry
     * @return bool
     */
    protected function addLines(Asiento &$entry): bool
    {
        // add lines for amortization liquidation
        $totalAmortized = round($this->document->getTotalAmortized(), FS_NF0);
        if (false === $this->addLineToEntry($entry, $this->document->codsubcuentacierre, false, $this->document->valor)
            || false === $this->addLineToEntry($entry, $this->document->codsubcuentahaber, true, $totalAmortized)
            || false === $this->addLineToEntry($entry, $this->codSubAccount, true, $this->invoice->neto))
        {
            return false;
        }

        // add line for benefit or lost
        $benefit = $totalAmortized + $this->invoice->neto - $this->document->valor;
        $subacount = $this->document->codsubcuentabeneficios;
        $isDebit = false;
        if ($benefit < 0.00) {
            $benefit = $benefit * -1.00;
            $subacount = $this->document->codsubcuentaperdidas;
            $isDebit = true;
        }
        return $this->addLineToEntry($entry, $subacount, $isDebit, $benefit);
    }

    /**
     * Add a new line to accounting entry.
     *
     * @param Asiento $entry
     * @param string $subaccount
     * @param bool $isDebit
     * @param float $amount
     * @return bool
     */
    protected function addLineToEntry(Asiento $entry, string $subaccount, bool $isDebit, float $amount): bool
    {
        $account = $this->getSubAccount($subaccount);
        if (empty($account->codsubcuenta)) {
            Tools::lang()->warning('subaccount-not-found', ['%subAccountCode%' => $subaccount]);
            return false;
        }

        $line = $this->getBasicLine($entry, $account, $isDebit, $amount);
        $line->concepto = $entry->concepto;
        return $line->save();
    }

    /**
     * Obtains an accounting entry with the default data.
     *
     * @return Asiento
     */
    protected function getNewEntry()
    {
        $entry = new Asiento();
        $entry->canal = $this->document->canal;
        $entry->codejercicio = $this->exercise->codejercicio;
        $entry->concepto = $this->getConcept();
        $entry->fecha = $this->document->fechafinvidautil;
        $entry->idempresa = $this->exercise->idempresa;
        $entry->importe = 0.00;
        return $entry;
    }

    /**
     * Load and check required data.
     *   - check exits the amortization.
     *   - check the amortization is not closed or sold.
     *   - check if the amortization has the required accounts.
     *   - check the end date is valid.
     *   - load exercise for company and date of the amortization line.
     *
     * @return bool
     */
    protected function initialCheck(): bool
    {
        if (empty($this->document->idamortizacion)) {
            Tools::log()->info('amortization-not-found');
            return false;
        }

        if (false === empty($this->document->idasientofinvida)) {
            Tools::log()->info('amortization-finalized');
            return false;
        }

        if (empty($this->document->idfacturaventa)) {
            Tools::log()->info('amortization-not-sold');
            return false;
        }

        if (empty($this->document->codsubcuentacierre)
            || empty($this->document->codsubcuentahaber)
            || empty($this->document->codsubcuentabeneficios)
            || empty($this->document->codsubcuentaperdidas))
        {
            Tools::log()->info('amortization-account-not-found');
            return false;
        }

        if (empty($this->codSubAccount)) {
            Tools::log()->info('sales-subaccount-not-found');
            return false;
        }

        if (false === $this->loadExerciseForDate($this->document->idempresa, $this->document->fechafinvidautil)) {
            Tools::log()->warning('accounting-exercise-not-found');
            return false;
        }
        return true;
    }

    /**
     * Return the concept for the accounting entry.
     *
     * @return string
     */
    protected function getConcept(): string
    {
        return Tools::lang()->trans('sell-immobilized') . ': '
            . $this->document->descripcion;
    }

    /**
     * Calculate and verify the accounting year.
     *
     * @param int $idcompany
     * @param string $date
     * @return bool
     */
    private function loadExerciseForDate(int $idcompany, string $date): bool
    {
        $this->exercise->idempresa = $idcompany;
        if (false === $this->exercise->loadFromDate($date, false)) {
            Tools::log()->warning('exercise-not-found');
            return false;
        }

        if (!$this->exercise->isOpened()) {
            Tools::log()->warning(
                'closed-exercise',
                ['%exerciseName%' => $this->exercise->codejercicio]
            );
            return false;
        }
        return true;
    }
}