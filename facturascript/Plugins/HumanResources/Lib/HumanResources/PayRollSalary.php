<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\HumanResources;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\HumanResources\Model\EmployeePayRoll;
use FacturaScripts\Plugins\HumanResources\Model\EmployeePayRollSalary;

/**
 * Calculation of an employee's salary based on salary concepts
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class PayRollSalary
{

    /**
     * Extra Hours salary concept ID
     *
     * @var int
     */
    private $extraHours;

    /**
     *
     * @var EmployeePayRollSalary
     */
    private $salary;

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->salary = new EmployeePayRollSalary();
        $this->extraHours = Tools::settings('rrhh', 'extra-hours');
    }

    /**
     * Calculate the amounts of the salary concepts from a list of employees
     *
     * @param int   $idpayroll
     * @param array $employeeWhere
     */
    public function calculate(int $idpayroll, array $employeeWhere = [])
    {
        /// Init master model data
        $employeeOrder = ['idemployee' => 'ASC'];
        $employeeWhere[] = new DataBaseWhere('idpayroll', $idpayroll);
        $payroll = new EmployeePayRoll();

        /// For each employee
        foreach ($payroll->all($employeeWhere, $employeeOrder, 0, 0) as $employeePayRoll) {
            /// Calculate his/her concepts and save employee payroll amount
            $this->calculateConcepts($employeePayRoll);
            $employeePayRoll->save();
        }
    }

    /**
     * Accumulate the amount from employee salary concept
     *
     * @param EmployeePayRollSalary $salaryConcept
     * @param float $debit
     * @param float $credit
     * @param float $balance
     */
    protected function accumulate(EmployeePayRollSalary $salaryConcept, float &$debit, float &$credit, float &$balance)
    {
        $amount = $salaryConcept->amount * $salaryConcept->quantity;
        switch ($salaryConcept->position) {
            case SalaryBase::POSITION_DEBIT:
                $debit += $amount;
                $balance += $amount;
                break;

            case SalaryBase::POSITION_CREDIT:
                $credit += $amount;
                $balance -= $amount;
                break;
        }
    }

    /**
     * Calculate the total amount according to the accumulated salary concepts
     * and establish the accounting position.
     *
     * @param EmployeePayRollSalary $salaryConcept
     * @param float $balance
     */
    protected function applyBalance(EmployeePayRollSalary $salaryConcept, float $balance)
    {
        $salaryConcept->quantity = 1.00;
        $salaryConcept->amount = $balance;
    }

    /**
     * Calculate the amount based on the percentage of the salary concept
     * and your accounting position.
     *
     * <strong>Note:</strong>
     * Change the calculation from percentage to unit to avoid later calculations.
     * Set the quantity as the percentage of the concept.
     *
     * @param EmployeePayRollSalary $salaryConcept
     * @param float $debit
     * @param float $credit
     * @param float $balance
     */
    protected function applyPercentage(EmployeePayRollSalary $salaryConcept, float $debit, float $credit, float &$balance)
    {
        $salaryConcept->calculation = SalaryBase::CALCULATION_UNITARY;
        $salaryConcept->quantity = $salaryConcept->amount / 100;
        switch ($salaryConcept->position) {
            case SalaryBase::POSITION_DEBIT:
                $salaryConcept->amount = $credit;
                $balance += $salaryConcept->amount * $salaryConcept->quantity;
                break;

            case SalaryBase::POSITION_CREDIT:
                $salaryConcept->amount = $debit;
                $balance -= $salaryConcept->amount * $salaryConcept->quantity;
                break;
        }
    }

    /**
     * Calculate salary concepts and total for a employee payroll
     *
     * @param EmployeePayRoll $employeePayRoll
     */
    protected function calculateConcepts(EmployeePayRoll $employeePayRoll)
    {
        /// Init working variables
        $channel = 0;
        $credit = 0;
        $debit = 0;
        $balance = 0;
        $employeePayRoll->amount = 0;

        /// Search for employee concept list
        $salaryOrder = ['channel' => 'ASC', 'calculation' => 'ASC', 'id' => 'ASC'];
        $where = [ new DataBaseWhere('idemployeepayroll', $employeePayRoll->id) ];

        /// Main process
        foreach ($this->salary->all($where, $salaryOrder, 0, 0) as $salaryConcept) {
            /// Init channel values
            if ($channel !== $salaryConcept->channel) {
                $channel = $salaryConcept->channel;
                $credit = 0;
                $debit = 0;
                $balance = 0;
            }

            /// According to the type of calculation
            switch ($salaryConcept->calculation) {
                /// Acumulate Amount
                case SalaryBase::CALCULATION_UNITARY:
                    $this->accumulate($salaryConcept, $debit, $credit, $balance);
                    continue 2;

                /// Acumulate Quantity x Amount
                case SalaryBase::CALCULATION_QUANTITY:
                    /// Check if concept is configured as overtime
                    if ($salaryConcept->idsalaryconcept != $this->extraHours) {
                        $this->accumulate($salaryConcept, $debit, $credit, $balance);
                        continue 2;
                    }

                    /// Assign difference between theoretical and real hours
                    $salaryConcept->quantity = $employeePayRoll->difference;
                    $this->accumulate($salaryConcept, $debit, $credit, $balance);
                    break;

                /// Percentage of the accumulated amount of the opposite column
                case SalaryBase::CALCULATION_PERCENTAGE:
                    $this->applyPercentage($salaryConcept, $debit, $credit, $balance);
                    break;

                /// Total salary concept
                case SalaryBase::CALCULATION_BALANCE:
                    $this->applyBalance($salaryConcept, $balance);
                    $employeePayRoll->amount += $balance;
                    break;
            }
            $salaryConcept->save();
        }
    }
}
