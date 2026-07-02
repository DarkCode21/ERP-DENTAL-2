<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\HumanResources;

/**
 * Base structure for the preparation of salaries
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
abstract class SalaryBase extends ModelExtended
{

    const CALCULATION_UNITARY = 1;
    const CALCULATION_QUANTITY = 2;
    const CALCULATION_PERCENTAGE = 80;
    const CALCULATION_BALANCE = 99;

    const POSITION_DEBIT = 1;
    const POSITION_CREDIT = 2;

    /**
     * Amount to pay.
     *
     * @var float|int
     */
    public $amount;

    /**
     * Type of claculation.
     * 1 -> unitary
     * 2 -> by quantity
     * 80-> by percentage
     * 99-> final balance
     *
     * @var integer
     */
    public $calculation;

    /**
     * Establishes the statistical channel
     *
     * @var int
     */
    public $channel;

    /**
     * Sub-account code.
     *
     * @var string
     */
    public $codsubaccount;

    /**
     * Salary concept relation field
     *
     * @var integer
     */
    public $idsalaryconcept;

    /**
     * Position of column into accounting entry.
     * 1 -> debit
     * 2 -> credit'
     *
     * @var integer
     */
    public $position;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->amount = 0.00;
        $this->calculation = self::CALCULATION_UNITARY;
        $this->position = self::POSITION_DEBIT;
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

        return parent::test();
    }

    /**
     * Copy source fields values
     *
     * @param SalaryBase $source
     */
    protected function copyFrom($source)
    {
        $this->amount = $source->amount;
        $this->calculation = $source->calculation;
        $this->channel = $source->channel;
        $this->codsubaccount = $source->codsubaccount;
        $this->idsalaryconcept = $source->idsalaryconcept;
        $this->position = $source->position;
    }
}
