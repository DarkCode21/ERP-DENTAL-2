<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Model;

use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\HumanResources\Lib\Accounting\VoucherPaidToAccounting;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelExtended;

/**
 * List of Paid of Employee Voucher
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */

class EmployeeVoucherPaid extends ModelExtended
{

    use ModelTrait;

    /**
     * Total amount
     *
     * @var double
     */
    public $amount;

    /**
     * Link to Asiento model
     *
     * @var int
     */
    public $identry;

    /**
     * Link to Employee Voucher model
     *
     * @var int
     */
    public $idvoucher;

    /**
     * Link to User model
     *
     * @var string
     */
    public $nick;

    /**
     * Creation Date
     *
     * @var string
     */
    public $startdate;

    /**
     * Creation Time
     *
     * @var string
     */
    public $starttime;

    /**
     * Voucher Pending status for update action
     *
     * @var boolean
     */
    private $voucherUpdate = true;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->amount = 0.00;
        $this->startdate = date('d-m-Y');
        $this->starttime = date('H:i:s');
    }

    /**
     * Remove the model data from the database.
     *
     * @return bool
     */
    public function delete(): bool
    {
        $newTransation = false === self::$dataBase->inTransaction() && self::$dataBase->beginTransaction();
        try {
            if (false === $this->deleteAccountingEntry() ||
                false === parent::delete() ||
                false === $this->updateEmployeeVoucherPending())
            {
                return false;
            }

            if ($newTransation) {
                self::$dataBase->commit();
            }
        } finally {
            if ($newTransation && self::$dataBase->inTransaction()) {
                self::$dataBase->rollback();
            }
        }
        return true;
    }

    /**
     * Get Voucher model for employee
     *
     * @return EmployeeVoucher
     */
    public function getVoucher(): EmployeeVoucher
    {
        $voucher = new EmployeeVoucher();
        $voucher->loadFromCode($this->idvoucher);
        return $voucher;
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
        new EmployeeVoucher();
        return parent::install();
    }

    /**
     * Set accounting action status
     *
     * @param bool $status
     */
    public function setVoucherUpdate(bool $status)
    {
        $this->voucherUpdate = $status;
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'rrhh_employeesvoucherspaid';
    }

    /**
     * Insert the model data in the database.
     * Generate accounting entry for paidment.
     *
     * @param array $values
     * @return bool
     */
    protected function saveInsert(array $values = array()): bool
    {
        $tool = new VoucherPaidToAccounting();
        $tool->generate($this);

        if (parent::saveInsert($values)) {
            $this->updateEmployeeVoucherPending();
            return true;
        }

        $this->deleteAccountingEntry();
        return false;
    }

    /**
     * Delete accounting entry for voucher payment
     *
     * @return bool
     */
    private function deleteAccountingEntry():bool
    {
        if (empty($this->identry)) {
            return true;
        }

        $entry = new Asiento();
        if ($entry->loadFromCode($this->identry)) {
            $entry->editable = true;
            if (false === $entry->delete()) {
                Tools::log()->warning('accounting-entry-delete-error', ['%number%' => $entry->numero]);
                return false;
            }
        }
        return true;
    }

    /**
     * Update voucher pending amount
     */
    private function updateEmployeeVoucherPending():bool
    {
        if (false === $this->voucherUpdate) {
            return true;
        }
        $voucher = new EmployeeVoucher();
        $voucher->loadFromCode($this->idvoucher);
        return $voucher->save();
    }
}
