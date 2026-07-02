<?php
/**
 * This file is part of Amortizaciones plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Amortizaciones  Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\Amortizaciones\Lib\Amortizaciones;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Amortizacion;
use FacturaScripts\Dinamic\Model\LineaAmortizacion;

/**
 * Class to create the amortization plan for a bank loan.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AmortizationBank
{

    /** @var Amortizacion */
    protected $amortization;

    /**
     * Class constructor. Initialize the amortization object.
     *
     * @param Amortizacion $amortization
     */
    public function __construct(Amortizacion $amortization)
    {
        $this->amortization = $amortization;
    }

    /**
     * Check and initialize the data necessary for the process.
     * The amortization must be loaded, previously.
     *
     * @param array $params
     * @return bool
     */
    public function checkData(array $params): bool
    {
        if (false === $this->amortization->exists()) {
            return false;
        }

        if (empty($this->amortization->periodos)
            || empty($this->amortization->tasaanual)
            || empty($this->amortization->valor)
        ) {
            Tools::log()->warning('amortization-bank-data-error');
            return false;
        }

        return true;
    }

    /**
     * Returns the monthly fee and the amortization table for a loan.
     *
     * @return bool
     */
    public function createAmortizationPlan(): bool
    {
        $year = date('Y', strtotime($this->amortization->fechainicio));
        $period = date('m', strtotime($this->amortization->fechainicio));
        $monthlyRate = $this->getMonthlyRate();
        $totalPeriod = $this->amortization->amortizationsByPeriod();
        $totalAmortization = $this->amortization->valor;
        $monthAmortization = $this->calculateMonthlyFee();

        // Main process
        for ($count = 0; $count < $this->amortization->periodos; ++$count) {
            $interest = $totalAmortization * $monthlyRate;
            $amortization = $monthAmortization - $interest;
            if (false === $this->addLineAmortization($year, $period, $amortization, $interest)) {
                return false;
            }

            $totalAmortization -= $amortization;
            $period++;
            if ($period > $totalPeriod) {
                $period = 1;
                ++$year;
            }
        }
        return true;
    }

    /**
     * Add a new line to the amortization plan.
     *
     * @param int $year
     * @param int $period
     * @param float $capital
     * @param float $interest
     * @return bool
     */
    private function addLineAmortization(
        int $year,
        int $period,
        float $capital,
        float $interest
    ): bool {
        $line = new LineaAmortizacion();
        $line->idamortizacion = $this->amortization->idamortizacion;
        $line->cantidad = round($capital + $interest, 2);
        $line->capital = $capital;
        $line->interes = $interest;
        $line->periodo = $period;
        $line->ano = $year;
        return $line->save();
    }

    /**
     * Calculate the monthly fee for a loan.
     *
     * @return float
     */
    private function calculateMonthlyFee(): float
    {
        $monthlyRate = $this->getMonthlyRate();
        return $this->amortization->valor
            * ($monthlyRate * pow(1 + $monthlyRate, $this->amortization->periodos))
            / (pow(1 + $monthlyRate, $this->amortization->periodos) - 1);
    }

    /**
     * Get the monthly interest rate.
     *
     * @return float
     */
    private function getMonthlyRate(): float
    {
        $annualRate = $this->amortization->tasaanual / 100;
        return $annualRate / 12;
    }
}
