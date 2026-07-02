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
use FacturaScripts\Core\Tools;

class AmortizationLinea
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

    public function checkData(array $params): bool
    {
        if (false === $this->amortization->exists()) {
            return false;
        }

        if (
            empty($this->amortization->periodos)
            || empty($this->amortization->porcamort)
            || empty($this->amortization->valor)
        ) {
            Tools::log()->warning('amortization-bank-data-error');
            return false;
        }

        return true;
    }

    public function createAmortizationPlan(): bool
{
    // Valores iniciales
    $year = date('Y', strtotime($this->amortization->fechainicio));
    $period = date('m', strtotime($this->amortization->fechainicio));
    $totalAmortization = $this->amortization->valor;

    // Calcular el número de períodos según el tipo de amortización (anual o mensual)
    if ($this->amortization->tipo === 'anual') {
        // Calcular el número de períodos anuales basado en el porcentaje de amortización
        $maxPeriods = ceil(100 / $this->amortization->porcamort);  // Número de años
    } else {
        // Si es mensual, calculamos con 12 meses por año
        $maxPeriods = ceil(100 / $this->amortization->porcamort) * 12;  // Número de meses
    }

    // Calcular la amortización por período, según el tipo (anual o mensual)
    $periodAmortization = round($totalAmortization * ($this->amortization->porcamort / 100), 2);

    // Calcular amortización proporcional para el primer período
    $firstYearAmortization = round($totalAmortization * ($this->amortization->porcamort / 100) * (date('m', strtotime($this->amortization->fechainicio)) / 12), 2);

    $currentPeriod = 0;

    // Proceso principal: Generar la amortización por períodos
    while ($totalAmortization > 0 && $currentPeriod < $maxPeriods) {
        $currentPeriod++;

        // Ajuste para el primer período
        if ($currentPeriod == 1 && $firstYearAmortization > 0) {
            $periodAmortization = $firstYearAmortization;
            $totalAmortization -= $periodAmortization;
        }

        // Ajuste en el último período para evitar residuos
        if ($currentPeriod == $maxPeriods || round($totalAmortization, 2) <= round($periodAmortization, 2)) {
            $periodAmortization = $totalAmortization;
        }

        // Agregar la línea de amortización al sistema
        if (false === $this->addLineAmortization($year, $period, $periodAmortization)) {
            return false;
        }

        $totalAmortization -= $periodAmortization;
        $period++;

        // Cambiar de año si es necesario
        if ($period > 12) {  // Cambiar mes cuando se supera diciembre
            $period = 1;
            ++$year;
        }
    }

    return true;
}


    private function addLineAmortization(int $year, int $period, float $amount): bool
    {
        $line = new LineaAmortizacion();
        $line->idamortizacion = $this->amortization->idamortizacion;
        $line->cantidad = round($amount, 2); // La cantidad es la amortización calculada
        $line->capital = $amount; // En amortización lineal, capital y cantidad son iguales
        $line->interes = 0.00; // No hay interés
        $line->periodo = $period;
        $line->ano = $year;
        return $line->save();
    }
}
