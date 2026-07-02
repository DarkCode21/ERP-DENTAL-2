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
use FacturaScripts\Core\Lib\Accounting\AccountingClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Amortizacion;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\LineaAmortizacion;

/**
 * Class base for the generation of accounting of the amortizations.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AmortizationPlanToAccounting extends AccountingClass
{

    /** @var LineaAmortizacion */
    protected $document;

    /** @var Amortizacion */
    protected $amortization;

    /**
     * Contabilize the amortization lines indicated by id list.
     *
     * @param array $lines
     */
    public static function exec(array $lines)
    {
        foreach ($lines as $idline) {
            $line = new LineaAmortizacion();
            if ($line->loadFromCode($idline) && empty($line->idasiento)) {
                $accounting = new self();
                $accounting->generate($line);
            }
        }
    }

    /**
     * Method to launch the accounting process
     *
     * @param LineaAmortizacion $model
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
            $accountEntry = $this->getNewEntry();
            if (false === $accountEntry->save()
                || false === $this->addLines($accountEntry))
            {
                return false;
            }

            // block new accounting entry
            $accountEntry->editable = false;
            $accountEntry->save();

            // link account with a document
            $this->document->idasiento = $accountEntry->idasiento;
            $this->document->amortizado = $this->document->cantidad;
            if (false === $this->document->save()) {
                return false;
            }
            if ($newTransation) {
                $dataBase->commit();
                Tools::log()->notice('record-updated-correctly');
            }
            return true;
        } finally {
            if ($newTransation && $dataBase->inTransaction()) {
                $dataBase->rollback();
            }
        }
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
            Tools::log()->warning('subaccount-not-found', ['%subAccountCode%' => $subaccount]);
            return false;
        }

        $amount = round($amount, 2);
        $line = $this->getBasicLine($entry, $account, $isDebit, $amount);
        $line->concepto = $entry->concepto;
        return $line->save();
    }

    /**
     * Obtains an accounting entry with the default data.
     *
     * @return Asiento
     */
    protected function getNewEntry(): Asiento
    {
        $entry = new Asiento();
        $entry->canal = $this->amortization->canal;
        $entry->codejercicio = $this->exercise->codejercicio;
        $entry->concepto = $this->getConcept();
        $entry->fecha = $this->document->fecha;
        $entry->idempresa = $this->exercise->idempresa;
        $entry->importe = $this->document->cantidad;
        return $entry;
    }

    /**
     * Load and check required data.
     *   - load amortization.
     *   - check if the amortization has the required accounts.
     *   - load exercise for company and date of the amortization line.
     * @return bool
     */
    protected function initialCheck(): bool
    {
        $this->amortization = new Amortizacion();
        if (false === $this->amortization->loadFromCode($this->document->idamortizacion)) {
            return false;
        }

        if (empty($this->amortization->codsubcuentadebe) || empty($this->amortization->codsubcuentahaber)) {
            Tools::log()->warning('amortization-account-not-found');
            return false;
        }

        if (false === $this->loadExerciseForDate($this->amortization->idempresa, $this->document->fecha)) {
            Tools::log()->warning('accounting-exercise-not-found');
            return false;
        }

        return true;
    }

    /**
     * Add the accounting entry lines.
     *
     * @param Asiento $entry
     * @return bool
     */
    private function addLines(Asiento $entry): bool
    {
        switch ($this->amortization->tipo) {
            case Amortizacion::TYPE_BANKING:
                return $this->addLineToEntry($entry, $this->amortization->codsubcuentadebe, true, $this->document->capital)
                    && $this->addLineToEntry($entry, $this->amortization->codsubcuentainteres, true, $this->document->interes)
                    && $this->addLineToEntry($entry, $this->amortization->codsubcuentahaber, false, $this->document->cantidad);

            default:
                return $this->addLineToEntry($entry, $this->amortization->codsubcuentadebe, true, $this->document->cantidad)
                    && $this->addLineToEntry($entry, $this->amortization->codsubcuentahaber, false, $this->document->cantidad);
        }
    }

    /**
     * Return the concept for the accounting entry.
     *
     * @return string
     */
    private function getConcept(): string
    {
        return Tools::lang()->trans('amortization') . ': '
            . $this->amortization->descripcion . ' '
            . '(' . $this->document->ano . '/' . $this->document->periodo . ')';
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
