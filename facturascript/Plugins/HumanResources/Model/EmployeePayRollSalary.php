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
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\SalaryBase;

/**
 * Salary detail of the payroll paid to the employee.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EmployeePayRollSalary extends SalaryBase
{

    use ModelTrait;

    /**
     * Employee payroll relation field
     *
     * @var integer
     */
    public $idemployeepayroll;

    /**
     * Unit amount paid from the concept
     *
     * @var double
     */
    public $quantity;

    /**
     * Total amount of the concept
     *
     * <strong>Note:</strong>
     * Calculate field from (quantity * amount)
     *
     * @var double
     */
    public $total;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->quantity = 1.00;
        $this->total = 0.00;
    }

    /**
     * Clone salary concepts from employee to other employee
     *
     * @param int $employee
     * @param int $employeePayRoll
     * @return bool
     */
    public static function cloneSalaryFromEmployee(int $employee, int $employeePayRoll): bool
    {
        if (empty($employee) || empty($employeePayRoll)) {
            return false;
        }

        /// Select data from employee salary template
        $where = [new DataBaseWhere('idemployee', $employee)];
        $employeeSalary = new EmployeeSalary();
        $rows = $employeeSalary->all($where, ['channel' => 'ASC', 'calculation' => 'ASC', 'id' => 'ASC'], 0, 0);
        if (empty($rows)) {
            return true;
        }

        /// Initialize work variables and values
        $model = new EmployeePayRollSalary();
        $model->idemployeepayroll = $employeePayRoll;

        /// Main Process
        $closeTransaction = !self::$dataBase->inTransaction();
        self::$dataBase->beginTransaction();
        try {
            foreach ($rows as $row) {
                /// Set data for new record
                $model->id = null;
                $model->copyFrom($row);

                /// Save new data
                if (!$model->save()) {
                    Tools::log()->warning('save-error', ['model' => 'employee-payroll-salary']);
                    return false;
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

    /**
     * This function is called when creating the model table. Returns the SQL
     * that will be executed after the creation of the table. Useful to insert values
     * default.
     *
     * @return string
     */
    public function install(): string
    {
        new EmployeeSalary();
        new EmployeePayRoll();
        parent::install();

        return '';
    }

    /**
     * Assign the values of the $data array to the model properties.
     *
     * @param array $data
     * @param array $exclude
     */
    public function loadFromData(array $data = array(), array $exclude = array())
    {
        parent::loadFromData($data, $exclude);
        $this->total = $this->quantity * $this->amount;
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'rrhh_employeespayrollsalary';
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
        $list = 'EditEmployeePayRoll?code=' . $this->idemployeepayroll . '&active=List';
        return parent::url($type, $list);
    }

    /**
     * Copy source fields values
     *
     * @param SalaryBase $source
     */
    protected function copyFrom($source)
    {
        parent::copyFrom($source);

        $quantity = $source->calculation == self::CALCULATION_QUANTITY ? 0.00 : 1.00;
        if (isset($source->quantity)) {
            $quantity = $source->quantity;
        }

        $this->quantity = $quantity;
    }
}
