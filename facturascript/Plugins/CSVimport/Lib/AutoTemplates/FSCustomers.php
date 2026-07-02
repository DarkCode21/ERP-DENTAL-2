<?php
/**
 * Copyright (C) 2024-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\AutoTemplates;

use FacturaScripts\Core\DataSrc\Agentes;
use FacturaScripts\Core\DataSrc\GruposClientes;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Tarifa;
use FacturaScripts\Plugins\CSVimport\Contract\AutoTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class FSCustomers implements AutoTemplateInterface
{
    const LIMIT_IMPORT = 500;

    /** @var bool */
    private $continue = false;

    /** @var int */
    private $start = 0;

    /** @var array */
    private $tarifas = [];

    /** @var int */
    private $total_lines = 0;

    public function continue(): bool
    {
        return $this->continue;
    }

    public function getTotalLines(): int
    {
        return $this->total_lines;
    }

    public function isValid(string $filePath, string $profile): bool
    {
        if ($profile !== 'customers') {
            return false;
        }

        // probamos empezando desde las líneas 0 a la 6
        foreach (range(0, 6) as $start) {
            $this->start = $start;
            $csv = CsvFileTools::read($filePath, $start, 0, 1);
            $this->total_lines = CsvFileTools::getTotalLines();

            if ($csv['titles'][0] === 'cifnif' && $csv['titles'][1] === 'codagente' && $csv['titles'][2] === 'codcliente') {
                return true;
            }
        }

        return false;
    }

    public function run(string $filePath, string $profile, string $mode, int &$offset, int &$saveLines): bool
    {
        $csv = CsvFileTools::read($filePath, $this->start, $offset, static::LIMIT_IMPORT);
        $this->total_lines = CsvFileTools::getTotalLines();
        $this->continue = false;

        foreach ($csv['data'] as $row) {
            // buscamos el cliente
            $customer = new Cliente();
            if (empty($row['codcliente'])
                || $customer->loadFromCode($row['codcliente']) && $mode === CsvFileTools::INSERT_MODE
                || false === $customer->loadFromCode($row['codcliente']) && $mode === CsvFileTools::UPDATE_MODE) {
                continue;
            }

            // creamos el cliente
            $customer->loadFromData($row, ['codproveedor', 'idcontacto']);

            if (empty($customer->codagente)) {
                $customer->codagente = null;
            } else {
                $agent = Agentes::get($customer->codagente);
                if (empty($agent->codagente)) {
                    $customer->codagente = null;
                }
            }

            if (empty($customer->codgrupo)) {
                $customer->codgrupo = null;
            } else {
                $group = GruposClientes::get($customer->codgrupo);
                if (empty($group->codgrupo)) {
                    $customer->codgrupo = null;
                }
            }

            if (empty($customer->codpago)) {
                $customer->codpago = null;
            }

            if (empty($customer->codretencion)) {
                $customer->codretencion = null;
            }

            if (empty($customer->codserie)) {
                $customer->codserie = null;
            }

            if (empty($customer->codtarifa)) {
                $customer->codtarifa = null;
            } else {
                $tarifa = $this->getTarifa($customer->codtarifa);
                if (empty($tarifa->codtarifa)) {
                    $customer->codtarifa = null;
                }
            }

            if (false === $customer->save()) {
                continue;
            }

            $this->continue = true;
            $offset++;
            $saveLines++;
        }

        return true;
    }

    private function getTarifa(string $codtarifa): Tarifa
    {
        if (empty($this->tarifas[$codtarifa])) {
            $this->tarifas[$codtarifa] = new Tarifa();
            $this->tarifas[$codtarifa]->loadFromCode($codtarifa);
        }

        return $this->tarifas[$codtarifa];
    }
}
