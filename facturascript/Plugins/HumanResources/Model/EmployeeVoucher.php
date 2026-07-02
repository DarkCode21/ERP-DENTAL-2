<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\CuentaEspecial;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\HumanResources\Lib\Accounting\VoucherToAccounting;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelExtendedOnChange;

/**
 * List of Employee Voucher
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */

class EmployeeVoucher extends ModelExtendedOnChange
{

    use ModelTrait;

    /**
     * Total amount
     *
     * @var double
     */
    public $amount;

    /**
     *
     * @var int
     */
    public $channel;

    /**
     * Paid Date
     *
     * @var string
     */
    public $enddate;

    /**
     * Link to Company model
     *
     * @var int
     */
    public $idcompany;

    /**
     * Link to Employee model
     *
     * @var int
     */
    public $idemployee;

    /**
     * Link to Asiento model
     *
     * @var int
     */
    public $identry;

    /**
     * Human description for voucher
     *
     * @var string
     */
    public $name;

    /**
     * Indicates if the voucher is paid
     *
     * @var bool
     */
    public $paid;

    /**
     * Creation Date
     *
     * @var string
     */
    public $startdate;

    /**
     * Pending amount
     *
     * @var double
     */
    public $pending;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->amount = 0.00;
        $this->pending = 0.00;
        $this->startdate = date('d-m-Y');
        $this->paid = false;
    }

    /**
     * Remove the model data from the database.
     *
     * @return bool
     */
    public function delete(): bool
    {
        $payments = $this->getPayments();
        $newTransation = false === self::$dataBase->inTransaction() && self::$dataBase->beginTransaction();
        try {
            if (!$this->deleteAccountingEntry() || !parent::delete()) {
                return false;
            }

            foreach ($payments as $row) {
                $row->setVoucherUpdate(false);
                if (!$row->delete()) {
                    return false;
                }
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
     * Get Complany model for employee voucher.
     * 
     * @return Empresa
     */
    public function getCompany(): Empresa
    {
        $company = new Empresa();
        $company->loadFromCode($this->idcompany);
        return $company;
    }

    /**
     * Get Employee model for employee voucher
     *
     * @return Employee
     */
    public function getEmployee(): Employee
    {
        $employee = new Employee();
        $employee->loadFromCode($this->idemployee);
        return $employee;
    }

    /**
     * Get Payments for an Employee Voucher.
     *
     * @return EmployeeVoucherPaid[]
     */
    public function getPayments(): array
    {
        $where = [ new DataBaseWhere('idvoucher', $this->id) ];
        $paidModel = new EmployeeVoucherPaid();
        return $paidModel->all($where, ['id' => 'ASC'], 0, 0);
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
        $this->addSpecialAccount(VoucherToAccounting::SPECIAL_PREPAYMENT_ACCOUNT);
        return parent::install();
    }

    /**
     * Returns the name of the column that describes the model, such as name, description...
     *
     * @return string
     */
    public function primaryDescriptionColumn(): string
    {
        return static::primaryColumn();
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'rrhh_employeesvouchers';
    }

    /**
     * Returns the url where to see / modify the data.
     *
     * @param string $type
     * @param string $list
     * @return string
     */
    public function url(string $type = 'auto', string $list = 'List'): string
    {
        return parent::url($type, 'ListEmployee?activetab=' . $list);
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        if (empty($this->channel)) {
            $this->channel = null;
        }

        if (parent::test()) {
            $this->updatePending();
            $this->setPaidStatus();
            return true;
        }
        return false;
    }

    /**
     * This methos is called before save (update) when some field has changed.
     *
     * @param string $field
     * @return bool
     */
    protected function onChange($field)
    {
        $this->updateAccountingEntry();
        $this->setPreviousData();
        return parent::onChange($field);
    }

    /**
     * Insert the model data in the database.
     *
     * @param array $values
     * @return bool
     */
    protected function saveInsert(array $values = array()): bool
    {
        $this->updateAccountingEntry();
        if (parent::saveInsert($values)) {
            return true;
        }
        return false;
    }


    /**
     * Updates the data of this record in the database.
     *
     * @param array $values
     * @return bool
     */
    protected function saveUpdate(array $values = array()): bool
    {
        if (empty($this->identry)) {
            $this->updateAccountingEntry();
        }
        return parent::saveUpdate($values);
    }

    /**
     * Saves previous values.
     *
     * @param array $fields
     */
    protected function setPreviousData(array $fields = array())
    {
        $more = ['amount', 'channel', 'idcompany', 'idemployee'];
        parent::setPreviousData(array_merge($more, $fields));
    }

    /**
     *
     * @param string $code
     */
    private function addSpecialAccount(string $code)
    {
        $special = new CuentaEspecial();
        if (!$special->loadFromCode($code)) {
            $special->codcuentaesp = $code;
            $special->descripcion = Tools::lang()->trans($code);
            $special->save();
            Tools::log()->info('add-special-account-anpago');
        }
    }

    /**
     * Delete accounting entry for voucher
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
            if (!$entry->delete()) {
                Tools::log()->warning('accounting-entry-delete-error', ['%number%' => $entry->numero]);
                return false;
            }
        }
        return false;
    }

    /**
     * Create accounting entry for voucher
     */
    private function updateAccountingEntry()
    {
        $this->deleteAccountingEntry();
        $tool = new VoucherToAccounting();
        $tool->generate($this);
    }

    /**
     * Set the liquidation status
     */
    private function setPaidStatus()
    {
        if ($this->pending < 0.00) {
            $this->pending = 0.00;
        }

        if ($this->pending > $this->amount) {
            $this->pending = $this->amount;
        }

        $this->paid = ($this->pending == 0.00);
        if ($this->paid === false) {
            $this->enddate = null;
            return;
        }

        if (empty($this->enddate)) {
            $this->enddate = date('d-m-Y');
        }
    }

    /**
     * Update pending field from payment list
     */
    private function updatePending()
    {
        $payment = new EmployeeVoucherPaid();
        $where = [ new DataBaseWhere('idvoucher', $this->id) ];
        $totalPaid = 0.00;

        foreach ($payment->all($where, [], 0, 0) as $row) {
            $totalPaid += $row->amount;
        }

        $this->pending = $this->amount - $totalPaid;
    }
}
