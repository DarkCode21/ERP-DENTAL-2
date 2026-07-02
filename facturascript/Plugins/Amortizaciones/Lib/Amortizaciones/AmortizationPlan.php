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

/**
 * Class for creating the amortization plan.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AmortizationPlan
{

    /**
     * Create the depreciation plan for the fixed assets.
     *
     * @param array $params
     */
    public static function exec(array $params)
    {
        $amortizationPlan = new self();
        $id = $params['idamortizacion'] ?? 0;
        $amortization = new Amortizacion();
        if (false === $amortization->loadFromCode($id)) {
            return;
        }

        $controller = $amortizationPlan->getController($amortization);
        if (false === isset($controller) || false === $controller->checkData($params)) {
            return;
        }

        $database = new DataBase();
        $database->beginTransaction();
        try {
            if ($controller->createAmortizationPlan($amortization)) {
                $database->commit();
                Tools::log()->notice('record-updated-correctly');
            }
        } finally {
            if ($database->inTransaction()) {
                $database->rollback();
                Tools::log()->error('amortization-plan-error');
            }
        }
    }

    /**
     * @return AmortizationConstant|AmortizationBank|AmortizationLinea|null
     */
    protected function getController(Amortizacion $amortizacion)
    {
        switch ($amortizacion->tipo) {
            case Amortizacion::TYPE_CONSTANT:
                return new AmortizationConstant($amortizacion);

            case Amortizacion::TYPE_BANKING:
                return new AmortizationBank($amortizacion);
            case Amortizacion::TYPE_LINEAL:
                return new AmortizationLinea($amortizacion);
        }
        return null;
    }
}
