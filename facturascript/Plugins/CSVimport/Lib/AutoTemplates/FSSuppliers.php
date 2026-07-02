<?php
/**
 * Copyright (C) 2024-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\AutoTemplates;

use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\CSVimport\Contract\AutoTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class FSSuppliers implements AutoTemplateInterface
{
    const LIMIT_IMPORT = 500;

    /** @var bool */
    private $continue = false;

    /** @var int */
    private $start = 0;

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
        if ($profile !== 'suppliers') {
            return false;
        }

        // probamos empezando desde las líneas 0 a la 6
        foreach (range(0, 6) as $start) {
            $this->start = $start;
            $csv = CsvFileTools::read($filePath, $start, 0, 1);
            $this->total_lines = CsvFileTools::getTotalLines();

            if ($csv['titles'][0] === 'acreedor' && $csv['titles'][1] === 'cifnif') {
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
            // find supplier
            $supplier = new Proveedor();
            if (empty($row['codproveedor'])
                || $supplier->loadFromCode($row['codproveedor']) && $mode === CsvFileTools::INSERT_MODE
                || false === $supplier->loadFromCode($row['codproveedor']) && $mode === CsvFileTools::UPDATE_MODE) {
                continue;
            }

            // save new supplier
            $supplier->loadFromData($row, ['codcliente', 'idcontacto']);

            if (empty($supplier->codpago)) {
                $supplier->codpago = null;
            }

            if (empty($supplier->codretencion)) {
                $supplier->codretencion = null;
            }

            if (empty($supplier->codserie)) {
                $supplier->codserie = null;
            }

            if (false === $supplier->save()) {
                continue;
            }

            $this->continue = true;
            $offset++;
            $saveLines++;
        }

        return true;
    }
}
