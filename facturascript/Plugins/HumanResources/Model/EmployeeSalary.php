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
 * List of extras and supplements that are paid to the employee
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EmployeeSalary extends SalaryBase
{

    use ModelTrait;

    /**
     * Employee relation field
     *
     * @var integer
     */
    public $idemployee;

    /**
     * Clone salary concepts from employee to other employee
     *
     * @param int $fromEmployee
     * @param int $toEmployee
     * @return bool
     */
    public function cloneSalaryToEmployee(int $fromEmployee, int $toEmployee): bool
    {
        if (empty($fromEmployee) || empty($toEmployee)) {
            return false;
        }

        $where = [new DataBaseWhere('idemployee', $fromEmployee)];
        $rows = $this->all($where, ['id' => 'ASC'], 0, 0);

        $closeTransaction = !self::$dataBase->inTransaction();
        self::$dataBase->beginTransaction();
        try {
            foreach ($rows as $row) {
                $row->idemployee = $toEmployee;
                $row->id = null;
                if (!$row->save()) {
                    Tools::log()->warning('save-error', ['model' => 'employee-salary']);
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
        new SalaryConcept();
        new Employee();
        parent::install();

        return '';
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'rrhh_employeessalary';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        if ($this->calculation == self::CALCULATION_BALANCE) {
            $this->amount = 0.00;
        }
        return parent::test();
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
        $list = 'EditEmployee?code=' . $this->idemployee . '&active=List';
        return parent::url($type, $list);
    }
}
