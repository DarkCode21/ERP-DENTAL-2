<?php
/**
 * This file is part of Amortizaciones plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Amortizaciones  Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\Amortizaciones\Lib\Amortizaciones;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Amortizacion;
use FacturaScripts\Dinamic\Model\CodeModel;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\LineaAmortizacion;

/**
 * Class for agrupe all actions for edit amortization.
 */
class EditAmortizacionAction
{
    public static function exec(String $action, array $data): array
    {
        $manager = new self();
        switch ($action) {
            case 'autocomplete-entry':
                return $manager->autocompleteEntryAction($data);

            case 'editline':
                return $manager->editLineAction($data);

            case 'insertline':
                return $manager->insertLineAction($data);

            case 'invoice-data':
                return $manager->invoiceDataAction($data);

            case 'line-data':
                return $manager->lineDataAction($data);
        }
        return [];
    }

    /**
     * Return a list of entries for autocomplete.
     * Only entries with the same date as the amortization line are returned.
     *
     * @param array $data
     * @return array
     */
    protected function autocompleteEntryAction(array $data): array
    {
        $results = [];
        $line = new LineaAmortizacion();
        if ($line->loadFromCode($data['idlinea'])) {
            $where = [new DataBaseWhere('fecha', $line->fecha, '=')];
            foreach (CodeModel::search('asientos', 'idasiento', 'concepto', $data['term'], $where) as $value) {
                $results[] = ['key' => Tools::fixHtml($value->code), 'value' => Tools::fixHtml($value->description)];
            }
        }

        if (empty($results)) {
            $results[] = ['key' => null, 'value' => Tools::lang()->trans('no-data')];
        }
        return $results;
    }

    /**
     * @param array $data
     * @return array
     */
    protected function editLineAction(array $data): array
    {
        $idline = $data['idlinea'] ?? 0;
        $line = new LineaAmortizacion();
        if (empty($line) || false === $line->loadFromCode($idline)) {
            Tools::log()->error('record-not-found');
            return ['error' => true];
        }

        $line->amortizado = $data['amortizado'];
        $line->idasiento = empty($data['idasiento']) ? null : (int)$data['idasiento'];
        if (false === $line->save()) {
            Tools::log()->error('record-save-error');
            return ['error' => true];
        }

        $entry = $line->getAsiento();
        if (false === empty($entry->idasiento)) {
            if ((float)$entry->importe !== (float)$line->amortizado) {
                Tools::log()->warning('line-amortized-entry-error');
            }
        }

        Tools::log()->notice('record-updated-correctly');
        return ['error' => false];
    }

    protected function insertLineAction(array $data): array
    {
        $idamortization = $data['idamortizacion'] ?? 0;
        $amortization = new Amortizacion();
        if (empty($idamortization) || false === $amortization->loadFromCode($idamortization)) {
            Tools::log()->error('record-not-found');
            return ['error' => true];
        }

        $year = (int)$data['ano'] ?? 0;
        $period = (int)$data['periodo'] ?? 0;
        $amount = (float)$data['cantidad'] ?? 0.00;
        if (false === $this->checkNewLine($amortization, $year, $period, $amount)) {
            return ['error' => true];
        }

        $line = new LineaAmortizacion();
        $line->idamortizacion = $idamortization;
        $line->cantidad = $amount;
        $line->periodo = $period;
        $line->ano = $year;
        if (false === $line->save()) {
            Tools::log()->error('record-save-error');
            return ['error' => true];
        }

        Tools::log()->notice('record-updated-correctly');
        return ['error' => false];
    }

    /**
     * @param array $data
     * @return array
     */
    protected function invoiceDataAction(array $data): array
    {
        $where = [ new DataBaseWhere('codigo', $data['invoice']) ];
        $invoice = new FacturaProveedor();
        $invoice->loadFromCode('', $where);
        return [
            'base' => $invoice->neto,
            'total' => $invoice->total,
            'divisa' => $invoice->coddivisa,
        ];
    }

    /**
     * @param array $data
     * @return array
     */
    protected function lineDataAction(array $data): array
    {
        $line = new LineaAmortizacion();
        if (false === $line->loadFromCode($data['code'])) {
            return [
                'error' => true,
                'message' => Tools::lang()->trans('line-not-found'),
            ];
        }

        $line->concepto = empty($line->idasiento) ? '' : $line->getAsiento()->concepto;
        return [
            'error' => false,
            'line' => $line,
        ];
    }

    /**
     * Check if the new line can be added to the amortization plan.
     *
     * @param Amortizacion $amortization
     * @param int $year
     * @param int $period
     * @param float $amount
     * @return bool
     */
    private function checkNewLine(Amortizacion $amortization, int $year, int $period, float $amount): bool
    {
        if (empty($year) || empty($period) || empty($amount)) {
            Tools::log()->error('mandatory-line-values-missing');
            return false;
        }

        foreach ($amortization->getLines() as $line) {
            if ($line->periodo === $period && $line->ano === $year) {
                Tools::log()->error('line-already-exists');
                return false;
            }
        }
        return true;
    }
}
