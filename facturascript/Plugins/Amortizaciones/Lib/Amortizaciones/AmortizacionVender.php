<?php
/**
 * This file is part of Amortizaciones plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Amortizaciones  Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\Amortizaciones\Lib\Amortizaciones;

use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Amortizacion;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\LineaAmortizacion;
use FacturaScripts\Plugins\Amortizaciones\Lib\Accounting\AmortizationPlanToAccounting;
use FacturaScripts\Plugins\Amortizaciones\Lib\Accounting\AmortizationSellToAccounting;

/**
 * Class to sell the product and contabilize rest of amortization.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AmortizacionVender
{
    /** @var Amortizacion */
    protected $amortization;

    /** @var FacturaCliente */
    protected $document;

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
            if (false === $controller->createDocument($params)
                || false === $controller->amortizePeriod()
                || false === $controller->createAccounting($params))
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
     * Amortize period pending to sell date.
     * Delete all plan after sell date.
     *
     * @return bool
     */
    protected function amortizePeriod(): bool
    {
        $accounting = new AmortizationPlanToAccounting();
        $order = ['fecha' => 'ASC'];
        foreach ($this->amortization->getPendingLines($order) as $line) {
            $included = (strtotime($line->fecha) <= strtotime($this->amortization->fechafinvidautil));
            if ($included) {
                $line->fecha = (false === $included) ? $this->amortization->fechafinvidautil : $line->fecha;
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
     * Create acounting entry for the sell of the product.
     * Add a special line to the amortization plan with finalize data.
     *
     * @param array $params
     * @return bool
     */
    protected function createAccounting(array $params): bool
    {
        // create the accounting entry for the sell of the product.
        if (false === AmortizationSellToAccounting::exec($this->amortization, $params['sell_subaccount'])) {
            Tools::log()->notice('amortization-accounting-error');
            return false;
        }

        // create a line in the amortization plan with finalize data.
        $line = new LineaAmortizacion();
        $line->ano = date('Y', strtotime($this->amortization->fechafinvidautil));
        $line->cantidad = $this->amortization->valor - $this->amortization->getTotalAmortized();
        $line->amortizado = $this->document->neto;
        $line->fecha = $this->amortization->fechafinvidautil;
        $line->idamortizacion = $this->amortization->idamortizacion;
        $line->idasiento = $this->amortization->idasientofinvida;
        $line->periodo = 99;
        return $line->save();
    }

    /**
     * If not exists, create the document to sell the product.
     *
     * @return bool
     */
    protected function createDocument(array $params): bool
    {
        if (false === empty($this->document->idfactura)) {
            $this->amortization->idfacturaventa = $this->document->idfactura;
            $this->amortization->fechafinvidautil = $this->document->fecha;
            return $this->amortization->save();
        }

        // set data to new document, and save it
        $this->document->idempresa = $params['idempresa'] ?? $this->idempresa;
        $this->document->setDefaultValues();

        $this->document->codserie = $params['sell_serie'] ?? $this->document->codserie;
        $this->document->codcliente = $params['sell_customer'];
        if (false === $this->document->updateSubject()
            || false === $this->document->setDate($params['sell_date'], date(FacturaCliente::HOUR_STYLE))
            || false === $this->document->save())
        {
            return false;
        }

        // add line to document
        $line = $this->document->getNewLine([
            'cantidad' => 1,
            'descripcion' => $this->amortization->descripcion,
            'pvpunitario' => $params['sell_amount'],
            'dtopor' => 0.00,
        ]);

        if (false === $line->save()) {
            return false;
        }

        // recalculate totals
        $lines = [ $line ];
        if (false === Calculator::calculate($this->document, $lines, true)) {
            return false;
        }

        // set documento to amortization
        $this->amortization->idfacturaventa = $this->document->idfactura;
        $this->amortization->fechafinvidautil = $this->document->fecha;
        return $this->amortization->save();
    }

    /**
     * Initialize the data necessary for the process.
     *
     * @param array $params
     */
    protected function initData(array $params): void
    {
        $id = (int)$params['idamortizacion'] ?? 0;
        $this->amortization = new Amortizacion();
        $this->amortization->loadFromCode($id);

        $iddocument = (int)$params['sell_invoice'] ?? 0;
        $this->document = new FacturaCliente();
        $this->document->loadFromCode($iddocument);
    }
}
