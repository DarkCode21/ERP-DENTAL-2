<?php
/**
 * This file is part of PagosMultiples plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * PagosMultiples  Copyright (C) 2020-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\PagosMultiples\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CustomerReceiptGroup;

/**
 * Class that manages the data model of the agent settlement.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class AgentSettlement extends ModelClass
{

    use ModelTrait;

    /**
     * Indicates if the breakdown of the total is entered
     * and should be calculated automatically.
     *
     * @var boolean
     */
    public $automatic;

    /**
     *
     * @var boolean
     */
    public $accept;

    /**
     * Acumulate import of bank checks.
     *
     * @var double
     */
    public $bankchecks;

    /**
     * Number of 500 bills delivered by the agent.
     *
     * @var integer
     */
    public $bills500;

    /**
     * Number of 200 bills delivered by the agent.
     *
     * @var integer
     */
    public $bills200;

    /**
     * Number of 100 bills delivered by the agent.
     *
     * @var integer
     */
    public $bills100;

    /**
     * Number of 50 bills delivered by the agent.
     *
     * @var integer
     */
    public $bills50;

    /**
     * Number of 20 bills delivered by the agent.
     *
     * @var integer
     */
    public $bills20;

    /**
     * Number of 10 bills delivered by the agent.
     *
     * @var integer
     */
    public $bills10;

    /**
     * Number of 5 bills delivered by the agent.
     *
     * @var integer
     */
    public $bills5;

    /**
     * Number of 2 coins delivered by the agent.
     *
     * @var integer
     */
    public $coins2;

    /**
     * Number of 1 coins delivered by the agent.
     *
     * @var integer
     */
    public $coins1;

    /**
     * Number of 0.50 coins delivered by the agent.
     *
     * @var integer
     */
    public $coins050;

    /**
     * Number of 0.20 coins delivered by the agent.
     *
     * @var integer
     */
    public $coins020;

    /**
     * Number of 0.10 coins delivered by the agent.
     *
     * @var integer
     */
    public $coins010;

    /**
     * Number of 0.05 coins delivered by the agent.
     *
     * @var integer
     */
    public $coins005;

    /**
     * Number of 0.02 coins delivered by the agent.
     *
     * @var integer
     */
    public $coins002;

    /**
     * Number of 0.01 coins delivered by the agent.
     *
     * @var integer
     */
    public $coins001;

    /**
     * Difference between receipts collected and settlement.
     *
     * @var double
     */
    public $difference;

    /**
     * Primary Key of the model.
     * Link to customer receipts group model.
     *
     * @var integer
     */
    public $id;

    /**
     * Total amount delivered by the agent.
     *
     * @var double
     */
    public $total;

    /**
     * Amount used in diets by the agent.
     *
     * @var double
     */
    public $total_diets;

    /**
     *
     * @return float
     */
    public function calculateSettlement(): float
    {
        $result = $this->bills500 * 500;
        $result += $this->bills200 * 200;
        $result += $this->bills100 * 100;
        $result += $this->bills50 * 50;
        $result += $this->bills20 * 20;
        $result += $this->bills10 * 10;
        $result += $this->bills5 * 5;
        $result += $this->coins2 * 2;
        $result += $this->coins1;
        $result += $this->coins050 * 0.50;
        $result += $this->coins020 * 0.20;
        $result += $this->coins010 * 0.10;
        $result += $this->coins005 * 0.05;
        $result += $this->coins002 * 0.01;
        $result += $this->coins001 * 0.01;
        return $result;
    }

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->automatic = true;
        $this->accept = false;
        $this->idpayment = Tools::settings('default', 'codpago');
        $this->total = 0.00;
        $this->total_diets = 0.00;
        $this->difference = 0.00;
        $this->bankchecks = 0.00;
    }

    /**
     * Gets the customer group associated with the settlement.
     *
     * @return CustomerReceiptGroup
     */
    public function getCustomerReceiptGroup()
    {
        $group = new CustomerReceiptGroup();
        $group->loadFromCode($this->id);
        return $group;
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
        new CustomerReceiptGroup();
        return parent::install();
    }

    /**
     * Returns the name of the column that is the model's primary key.
     *
     * @return string
     */
    public static function primaryColumn(): string
    {
        return 'id';
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'ppmm_agent_settlement';
    }

    /**
     * Fill the class with the registry values
     * whose primary column corresponds to the value $cod, or according to the condition
     * where indicated, if value is not reported in $cod.
     * Initializes the values of the class if there is no record that
     * meet the above conditions.
     * Returns True if the record exists and False otherwise.
     *
     * @param int $code
     * @param array $where
     * @param array $order
     *
     * @return bool
     */
    public function loadFromCode($code, array $where = array(), array $order = array()): bool
    {
        if (false === parent::loadFromCode($code, $where, $order)) {
            $this->id = $code;
            return false;
        }
        return true;
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        $this->calculate();
        return parent::test();
    }

    /**
     * Calculate total and difference, if its automatic.
     */
    private function calculate()
    {
        if (!$this->automatic) {
            return;
        }
        $group = $this->getCustomerReceiptGroup();
        $this->total = $this->calculateSettlement();
        $this->bankchecks = $this->calculateBankChecks($group);
        $this->difference = ($this->total > 0) ? $this->calculateDifference($group) : 0.00;
    }

    /**
     *
     * @param CustomerReceiptGroup $group
     * @return float
     */
    private function calculateBankChecks(CustomerReceiptGroup $group): float
    {
        $total = 0.00;
        foreach ($group->getBankChecks() as $bankcheck) {
            $total += $bankcheck->total;
        }
        return $total;
    }

    /**
     *
     * @param CustomerReceiptGroup $group
     * @return float
     */
    private function calculateDifference(CustomerReceiptGroup $group): float
    {
        return round(($this->total + $this->total_diets + $this->bankchecks) - $group->total, FS_NF0);
    }
}
