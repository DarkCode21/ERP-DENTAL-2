<?php
/**
 * Copyright (C) 2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\AutoTemplates;

use FacturaScripts\Dinamic\Model\Familia;
use FacturaScripts\Plugins\CSVimport\Contract\AutoTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class FactusolFamilies implements AutoTemplateInterface
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
        if ($profile !== 'families') {
            return false;
        }

        // probamos empezando desde las líneas 0 a la 6
        foreach (range(0, 6) as $start) {
            $this->start = $start;
            $csv = CsvFileTools::read($filePath, $start, 0, 1);
            $this->total_lines = CsvFileTools::getTotalLines();

            if ($csv['titles'][0] === 'Código' && $csv['titles'][1] === 'Descripción' && $csv['titles'][2] === 'Sección') {
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
            // buscamos la familia
            $family = new Familia();
            $code = $row['Código'];
            if (empty($code) ||
                ($family->loadFromCode($code) && $mode === CsvFileTools::INSERT_MODE) ||
                (false === $family->loadFromCode($code) && $mode === CsvFileTools::UPDATE_MODE)) {
                continue;
            }

            // guardar nueva familia
            $family->codfamilia = $code;
            $family->descripcion = $row['Descripción'];
            if (false === $family->save()) {
                continue;
            }

            $this->continue = true;
            $offset++;
            $saveLines++;
        }

        return true;
    }
}
