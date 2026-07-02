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
use FacturaScripts\Dinamic\Model\EmployeeVoucher;
use FacturaScripts\Dinamic\Model\EmployeeVoucherPaid;

/**
 * Class for management Vouchers and payments data of the employee panel
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class PanelVoucher
{

    /**
     *
     * @var EmployeeVoucher[]
     */
    public $data;

    /**
     *
     * @var EmployeeVoucherPaid[]
     */
    public $payments;

    /**
     *
     * @var float
     */
    public $pending;

    /**
     *
     * @var float
     */
    public $total;

    /**
     * Constructor and inicializate values
     */
    public function __construct()
    {
        $this->data = [];
        $this->payments = [];
        $this->pending = 0.00;
        $this->total = 0.00;
    }

    /**
     * Load voucher data structure for employee.
     *
     * @param int $idemployee
     */
    public function load(int $idemployee)
    {
        $period = date('Y-m-d', strtotime('-3 months'));
        $where = [
            new DataBaseWhere('idemployee', $idemployee),
            new DataBaseWhere('paid', false),
            new DataBaseWhere('startdate', $period, '>', 'OR'),
        ];
        $order = [ 'startdate' => 'ASC' ];

        $vouchers = new EmployeeVoucher();
        foreach ($vouchers->all($where, $order) as $item) {
            $this->total += $item->amount;
            $this->pending += $item->pending;
            $this->data[] = $item;

            foreach ($item->getPayments() as $payment) {
                $this->payments[] = $payment;
            }
        }
    }
}
