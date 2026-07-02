<?php
/**
 * This file is part of Amortizaciones plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Amortizaciones  Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\Amortizaciones\Lib\Amortizaciones;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Amortizacion;
use FacturaScripts\Dinamic\Model\LineaAmortizacion;
use FacturaScripts\Plugins\Amortizaciones\Lib\Accounting\AmortizationFinalizeToAccounting;
use FacturaScripts\Plugins\Amortizaciones\Lib\Accounting\AmortizationPlanToAccounting;

/**
 * Class for finalization the amortization of one product.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AmortizacionFinalizar
{
    /** @var Amortizacion */
    protected $amortization;

    /** @var string */
    protected $endAmortizationDate;

    /**
     * Create the depreciation plan for the fixed assets.
     *
     * @param array $params
     * @return bool
     */
    public static function exec(array $params)
    {
        $controller = new self();
        $controller->initData($params);
        $database = new DataBase();
        $database->beginTransaction();
        try {
            if (false === $controller->amortizePeriod()
                || false === $controller->endAmortization())
            {
                return false;
            }
            $database->commit();
            Tools::log()->notice('record-updated-correctly');
            return true;
        } finally {
            if ($database->inTransaction()) {
                $database->rollback();
            }
        }
    }

    /**
     * @return bool
     */
    protected function amortizePeriod(): bool
    {
        $accounting = new AmortizationPlanToAccounting();
        $order = ['fecha' => 'ASC'];
        foreach ($this->amortization->getPendingLines($order) as $line) {
            $included = (strtotime($line->fecha) <= strtotime($this->endAmortizationDate));
            if ($included) {
                $line->fecha = (false === $included) ? $this->endAmortizationDate : $line->fecha;
                // FIXME: when not included, its partial included then contabilize the amount to the endAmortizarionDate.
                if (false === $accounting->generate($line)) {
                    Tools::log()->notice('amortization-accounting-error');
                    return false;
                }
                continue;
            }

            // delete not include lines.
            if (false === $line->delete()) {
                Tools::log()->notice('amortization-plan-delete-error');
                return false;
            }
        }
        return true;
    }

    /**
     * @return bool
     */
    protected function endAmortization(): bool
    {
        // create the accounting entry for the end of the amortization.
        if (false === AmortizationFinalizeToAccounting::exec($this->amortization, $this->endAmortizationDate)) {
            Tools::log()->notice('amortization-accounting-error');
            return false;
        }

        // create a line in the amortization plan with finalize data.
        $line = new LineaAmortizacion();
        $line->ano = date('Y', strtotime($this->endAmortizationDate));
        $line->cantidad = 0;
        $line->fecha = $this->endAmortizationDate;
        $line->idamortizacion = $this->amortization->idamortizacion;
        $line->idasiento = $this->amortization->idasientofinvida;
        $line->periodo = 99;
        return $line->save();
    }

    /**
     * Initialize the data necessary for the process.
     *
     * @param array $params
     */
    protected function initData(array $params): void
    {
        $id = $params['idamortizacion'] ?? 0;
        $this->amortization = new Amortizacion();
        $this->amortization->loadFromCode($id);
        $this->endAmortizationDate = $params['finalize_date'] ?? '';
    }
}
