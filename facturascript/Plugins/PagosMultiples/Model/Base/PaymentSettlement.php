<?php
/**
 * This file is part of PagosMultiples plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * PagosMultiples  Copyright (C) 2020-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\PagosMultiples\Model\Base;

use FacturaScripts\Core\Model\Base\ModelClass;

/**
 * Model class base for data of the settlement.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
abstract class PaymentSettlement extends ModelClass
{

    /**
     * Indicates if the breakdown of the total is entered
     * and should be calculated automatically.
     *
     * @var boolean
     */
    public $automatic;

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
        return ($this->bills500 * 500)
            + ($this->bills200 * 200)
            + ($this->bills100 * 100)
            + ($this->bills50 * 50)
            + ($this->bills20 * 20)
            + ($this->bills10 * 10)
            + ($this->bills5 * 5)
            + ($this->coins2 * 2)
            + $this->coins1
            + ($this->coins050 * 0.50)
            + ($this->coins020 * 0.20)
            + ($this->coins010 * 0.10)
            + ($this->coins005 * 0.05)
            + ($this->coins002 * 0.02)
            + ($this->coins001 * 0.01);
    }

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->automatic = true;
        $this->total = 0.00;
        $this->total_diets = 0.00;
        $this->difference = 0.00;
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        if ($this->automatic) {
            $this->total = $this->calculateSettlement();
        }
        return parent::test();
    }
}
