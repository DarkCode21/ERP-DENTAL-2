<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\Accounting;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Dinamic\Lib\Accounting\AccountingClass;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\SalaryBase;
use FacturaScripts\Plugins\HumanResources\Model\Join\PayRollAccountingLines;
use FacturaScripts\Plugins\HumanResources\Model\PayRoll;
use FacturaScripts\Plugins\HumanResources\Model\PayRollAccounting;

/**
 * Class for the generation of accounting entries of employees payroll.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class PayRollToAccounting extends AccountingClass
{

    /** @var PayRoll */
    protected $document;

    /**
     *
     * @param PayRoll $model
     * @return bool
     */
    public function generate($model):bool
    {
        parent::generate($model);

        /// Set company and fiscal exercise
        if (!$this->loadExerciseForDate($this->document->idcompany, $this->document->creationdate)) {
            return false;
        }

        $payrollSalary = $this->getPayRollSalary();
        if (empty($payrollSalary)) {
            return false;
        }

        /// Main Process
        $dataBase = new DataBase();
        $newTransation = false === $dataBase->inTransaction() && $dataBase->beginTransaction();
        try {
            $entry = null;
            foreach ($payrollSalary as $data) {
                if (false === $this->checkEntry($entry, $data) || false === $this->newLine($entry, $data)) {
                    return false;
                }
            }

            /// Save amounts from the last accounting entry
            if (isset($entry)) {
                $entry->editable = false;
                $entry->save();
            }

            if ($newTransation) {
                $dataBase->commit();
            }
        } finally {
            if ($newTransation && $dataBase->inTransaction()) {
                $dataBase->rollback();
            }
        }
        Tools::log()->notice('record-updated-correctly');
        return true;
    }

    /**
     *
     * @param Asiento|Null $entry
     * @param PayRollAccountingLines $data
     * @return bool
     */
    private function checkEntry(&$entry, $data):bool
    {
        if (!isset($entry) || $entry->canal != $data->channel) {
            /// Save amounts from the previous accounting entry
            if (isset($entry)) {
                $entry->editable = false;
                $entry->save();
            }

            /// Generate accounting entry
            $entry = $this->getNewEntry($data);
            if (false === $entry->save()) {
                Tools::log()->warning('accounting-entry-error');
                return false;
            }

            /// Payroll and accounting relation model
            $payrollAccounting = new PayRollAccounting();
            $payrollAccounting->idpayroll = $this->document->id;
            $payrollAccounting->identry = $entry->idasiento;
            if (false === $payrollAccounting->save()) {
                Tools::log()->warning('accounting-entry-error');
                return false;
            }
        }
        return true;
    }

    /**
     *
     * @param PayRollAccountingLines $accountingLines
     * @return Asiento
     */
    private function getNewEntry($accountingLines)
    {
        $entry = new Asiento();
        $entry->canal = $accountingLines->channel;
        $entry->iddiario = Tools::settings('rrhh', 'journal');
        $entry->codejercicio = $this->exercise->codejercicio;
        $entry->idempresa = $this->exercise->idempresa;
        $entry->fecha = $this->document->creationdate;
        $entry->concepto = $this->document->name;
        $entry->importe = 0;
        return $entry;
    }

    /**
     *
     * @param Asiento $entry
     * @param PayRollAccountingLines $accountingLines
     * @return Partida
     */
    private function getNewLine($entry, $accountingLines)
    {
        $code = isset($accountingLines->codsubaccount) ? $accountingLines->codsubaccount : '';
        $account = $this->getSubAccount($code);
        $isDebit = ($accountingLines->column_position == SalaryBase::POSITION_DEBIT);
        $line = $this->getBasicLine($entry, $account, $isDebit, $accountingLines->total);
        $line->concepto = Tools::lang()->trans('employee-salary') . ': ' . $accountingLines->employee;
        return $line;
    }

    /**
     *
     * @return PayRollAccountingLines[]
     */
    private function getPayRollSalary()
    {
        $where = [ new DataBaseWhere('rrhh_payroll.id', $this->document->id) ];
        $order = [
            'rrhh_employeespayroll.idemployee' => 'ASC',
            'rrhh_employeespayrollsalary.channel' => 'ASC',
            'rrhh_employeespayrollsalary.position' => 'ASC',
            'rrhh_employeespayrollsalary.id' => 'ASC'
        ];

        $accountingLines = new PayRollAccountingLines();
        return $accountingLines->all($where, $order);
    }

    /**
     *
     * @param int $idcompany
     * @param string $date
     * @return boolean
     */
    protected function loadExerciseForDate($idcompany, $date)
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

    /**
     * Generate new accounting line.
     * Acumulate line amount to entry.
     *
     * @param Asiento|Null $entry
     * @param PayRollAccountingLines $data
     * @return bool
     */
    private function newLine(&$entry, $data): bool
    {
        $line = $this->getNewLine($entry, $data);
        if (false === $line->save()) {
            Tools::log()->warning('accounting-entry-error');
            return false;
        }
        $entry->importe += $line->debe;
        return true;
    }
}
