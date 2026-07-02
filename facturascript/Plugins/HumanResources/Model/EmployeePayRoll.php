<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Model;

use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelExtended;
use FacturaScripts\Plugins\HumanResources\Model\Report\AttendanceSummaryReport;

/**
 * List of payroll that are paid to the employee
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EmployeePayRoll extends ModelExtended
{

    use ModelTrait;

    /**
     * Amount to pay.
     *
     * @var float
     */
    public $amount;

    /**
     * Total number of vacation days enjoyed.
     *
     * @var int
     */
    public $holiday;

    /**
     * Total number of hours to work according to work shift.
     *
     * @var float
     */
    public $hours;

    /**
     * Employee relation field.
     *
     * @var integer
     */
    public $idemployee;

    /**
     * Pay Roll relation field.
     *
     * @var integer
     */
    public $idpayroll;

    /**
     * Difference between total hours and total hours worked.
     *
     * @var float
     */
    public $difference;

    /**
     * Total number of hours worked.
     *
     * @var float
     */
    public $worked;

    /**
     * Add a list of employees to a payroll
     *
     * @param int $idpayroll
     * @param DataBaseWhere[]|array $employeeWhere
     * @return bool
     */
    public function addEmployee($idpayroll, $employeeWhere): bool
    {
        /// Search for indicated payroll
        $payroll = new PayRoll();
        if (!$payroll->loadFromCode($idpayroll)) {
            return false;
        }

        /// Search employees of company selected
        $employeeWhere[] = new DataBaseWhere('idcompany', $payroll->idcompany);
        $employees = new Employee();
        $employeeList = $employees->all($employeeWhere, ['id' => 'ASC'], 0, 0);
        if (empty($employeeList)) {
            return false;
        }

        /// Calculate employees work data
        $report = new AttendanceSummaryReport();
        $workData = $report->attendanceSummary($payroll->startdate, $payroll->enddate, $employeeWhere);

        /// Add employees and salaries
        if (!$this->addEmployeesToDB($idpayroll, $employeeList, $workData)) {
            return false;
        }

        return true;
    }

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->amount = 0.00;
        $this->difference = 0.00;
        $this->holiday = 0;
        $this->hours = 0.00;
        $this->worked = 0.00;
    }

    /**
     * This function is called when creating the model table. Returns the SQL
     * that will be executed after the creation of the table. Useful to insert values
     * default.
     *
     * @return string
     */
    public function install(): string
    {
        new Employee();
        new PayRoll();
        return parent::install();
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'rrhh_employeespayroll';
    }

    /**
     * Returns the url where to see / modify the data.
     *
     * @param string $type
     * @param string $list
     *
     * @return string
     */
    public function url(string $type = 'auto', string $list = 'List'): string
    {
        $list = 'EditPayRoll?code=' . $this->idpayroll . '&active=List';
        return parent::url($type, $list);
    }

    /**
     *
     * @param int $idpayroll
     * @param Employee $employee
     * @param AttendanceSummaryData[] $workData
     */
    private function addEmployeeSetData($idpayroll, $employee, $workData)
    {
        /// Add employee to payroll
        $this->idpayroll = $idpayroll;
        $this->idemployee = $employee->id;

        /// Add work data for employee
        foreach ($workData as $summaryData) {
            if ($summaryData->idemployee == $this->idemployee) {
                $this->holiday = $summaryData->totalHoliday;
                $this->hours = $summaryData->totalHours;
                $this->worked = $summaryData->totalWorked;
                $this->difference = $summaryData->totalDifference;
                break;
            }
        }
    }

    /**
     *
     * @param int $idpayroll
     * @param Employee[] $employeeList
     * @param AttendanceSummaryData[] $workData
     * @return bool
     */
    private function addEmployeesToDB($idpayroll, $employeeList, $workData): bool
    {
        $closeTransaction = !self::$dataBase->inTransaction();

        self::$dataBase->beginTransaction();
        try {
            foreach ($employeeList as $employee) {
                /// Check that the employee does not have a salary for the payroll
                $where = [
                    new DataBaseWhere('idpayroll', $idpayroll),
                    new DataBaseWhere('idemployee', $employee->id),
                ];

                if (!$this->loadFromCode('', $where)) {
                    /// Set data to employee's payroll
                    $this->addEmployeeSetData($idpayroll, $employee, $workData);

                    /// Insert employee to payroll
                    if (!$this->save()) {
                        return false;
                    }

                    /// Add salary for employee
                    if (!EmployeePayRollSalary::cloneSalaryFromEmployee($this->idemployee, $this->id)) {
                        return false;
                    }
                }
            }

            /// Confirm data if only one transaction
            if ($closeTransaction) {
                self::$dataBase->commit();
            }
            return true;
        } catch (Exception $exception) {
            Tools::log()->warning($exception->getMessage());
            return false;
        } finally {
            /// Cancel data if only one transaction and not finish correctly
            if ($closeTransaction && self::$dataBase->inTransaction()) {
                self::$dataBase->rollback();
            }
        }
    }
}