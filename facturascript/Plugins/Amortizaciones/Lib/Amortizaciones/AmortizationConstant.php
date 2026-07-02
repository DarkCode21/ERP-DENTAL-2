<?php
/**
 * This file is part of Amortizaciones plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Amortizaciones  Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\Amortizaciones\Lib\Amortizaciones;

use FacturaScripts\Dinamic\Model\Amortizacion;
use FacturaScripts\Dinamic\Model\LineaAmortizacion;

/**
 * Class to create the constant amortization plan.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AmortizationConstant
{
    private const FIRST_PERIOD_COMPLETE = 1;
    private const FIRST_PERIOD_DISTRIBUTION = 2;
    private const FIRST_PERIOD_ADITIONAL = 3;

    /** @var Amortizacion */
    protected $amortization;

    /** @var int */
    protected $firstPeriodType;

    /** @var int */
    protected $times;

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

        $this->times = $this->amortization->amortizationsByPeriod() * $this->amortization->periodos;
        if (empty($this->times)) {
            return false;
        }

        $this->firstPeriodType = (int)$params['firstPeriodType'] ?? self::FIRST_PERIOD_COMPLETE;
        return true;
    }

    /**
     * Create the depreciation plan for the fixed assets.
     * Main process.
     *
     * @return bool
     */
    public function createAmortizationPlan(): bool
    {
        // start values
        $year = date('Y', strtotime($this->amortization->fechainicio));
        $period = $this->getStartPeriod();
        $totalPeriod = $this->amortization->amortizationsByPeriod();
        $totalAmortization = $this->amortization->valor;
        $periodAmortization = round($totalAmortization / $this->times, FS_NF0);

        // Main process
        for ($count = 0; $count < $this->times; ++$count) {
            $amount = $this->getAmortizationAmount($totalAmortization, $periodAmortization, $count);
            if (false === $this->addLineAmortization($year, $period, $amount)) {
                return false;
            }

            $totalAmortization -= $amount;
            $period++;
            if ($period > $totalPeriod) {
                $period = 1;
                ++$year;
            }

            // if there are more than one amortization, distribute the rest of amortization into the next periods.
            if ($this->times > 1) {
                if ($count === 0 && $this->firstPeriodType === self::FIRST_PERIOD_DISTRIBUTION) {
                    $periodAmortization = round($totalAmortization / ($this->times - 1), FS_NF0);
                }
            }
        }

        // depreciation difference
        if ($totalAmortization > 0 && $this->firstPeriodType === self::FIRST_PERIOD_ADITIONAL) {
            if (false === $this->addLineAmortization($year, $period, $totalAmortization)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Gets the amortization ratio for the period.
     *
     * @return float
     */
    protected function getAmortizationDays(): float
    {
        $date = strtotime($this->amortization->fechainicio);
        switch ($this->amortization->contabilizacion) {
            case Amortizacion::CONTABILIZACION_ANNUAL:
                $endDate = date('Y-12-31', $date);
                $days = round((strtotime($endDate) - $date) / 86400);
                $maxDays = date("L", $date) === '1' ? 366 : 365;
                return round(($days + 1) / $maxDays, 4);

            case Amortizacion::CONTABILIZACION_MONTHLY:
                $days = date("d", $date);
                $maxDays = date("t", $date);
                return round(($days + 1) / $maxDays, 4);

            case Amortizacion::CONTABILIZACION_QUARTERLY:
                $endDate = $this->endDateInQuarterly($this->getStartPeriod(), $date);
                $days = ((strtotime($endDate) - $date) / 86400) + 1;
                $maxDays = $this->daysInQuarterly($this->getStartPeriod(), date("L", $date) === '1');
                return round(($days + 1) / $maxDays, 4);
        }
        return 1;
    }

    /**
     * Add a new line to the amortization plan.
     *
     * @param int $year
     * @param int $period
     * @param float $amount
     * @return bool
     */
    private function addLineAmortization(int $year, int $period, float $amount = 0.00): bool
    {
        $line = new LineaAmortizacion();
        $line->idamortizacion = $this->amortization->idamortizacion;
        $line->cantidad = $amount;
        $line->periodo = $period;
        $line->ano = $year;
        return $line->save();
    }

    /**
     * Returns the amount of the amortization.
     * if calculate the first period, and it is not complete then calculate the period of amortization.
     * if calculate the last period, and it is not aditional then return the rest of amortization.
     *
     * @param float $total
     * @param float $amount
     * @param int $count
     * @return float
     */
    private function getAmortizationAmount(float $total, float $amount, int $count): float
    {
        // for the first period from adquisition date
        if ($count === 0 && $this->firstPeriodType !== self::FIRST_PERIOD_COMPLETE) {
            return round($amount * $this->getAmortizationDays(), FS_NF0);
        }

        // for the last period and not aditional period
        if ($count === $this->times - 1 && $this->firstPeriodType !== self::FIRST_PERIOD_ADITIONAL) {
            return $total;
        }

        // other periods
        if ($total > $amount) {
            return $amount;
        }

        return $total;
    }

    /**
     * Number of days in a quarter.
     *
     * @param int $quarterly
     * @param bool $leap
     * @return int
     */
    private function daysInQuarterly(int $quarterly, bool $leap = false): int
    {
        switch ($quarterly) {
            case 1:
                return $leap ? 91 : 90;

            case 2:
                return 91;

            case 3:
            case 4:
                return 92;
        }
        return 0;
    }

    /**
     * Date of the last day of a quarter.
     *
     * @param int $quarterly
     * @param int $date
     * @return string
     */
    private function endDateInQuarterly(int $quarterly, int $date): string
    {
        switch ($quarterly) {
            case 1:
                return date('Y-03-31', $date);

            case 2:
                return date('Y-06-30', $date);

            case 3:
                return date('Y-09-30', $date);

            case 4:
                return date('Y-12-31', $date);
        }
        return '';
    }

    /**
     * Calculates the initial amortization period based on the type of posting.
     *
     * @return int
     */
    private function getStartPeriod(): int
    {
        switch ($this->amortization->contabilizacion) {
            case Amortizacion::CONTABILIZACION_MONTHLY:
                return date('m', strtotime($this->amortization->fechainicio));

            case Amortizacion::CONTABILIZACION_QUARTERLY:
                $month = date('m', strtotime($this->amortization->fechainicio));
                return ($month / 4) + 1;

            case Amortizacion::CONTABILIZACION_ANNUAL:
            default:
                return 1;
        }
    }
}
