<?php
/**
 * Copyright (C) 2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\AutoTemplates;

use DateTime;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Dinamic\Model\Subcuenta;
use FacturaScripts\Plugins\CSVimport\Contract\AutoTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class SageAccountingDiary implements AutoTemplateInterface
{
    const LIMIT_IMPORT = 250;

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
        if ($profile !== 'accounting-entries') {
            return false;
        }

        // probamos empezando desde las líneas 0 a la 6
        foreach (range(0, 6) as $start) {
            $this->start = $start;
            $csv = CsvFileTools::read($filePath, $start, 0, 1);
            $this->total_lines = CsvFileTools::getTotalLines();

            if ($csv['titles'][0] === 'N° trans' &&
                $csv['titles'][1] === 'Fecha de asiento' &&
                $csv['titles'][8] === 'Categoría') {
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
            // obtenemos la fecha y año
            $date = DateTime::createFromFormat('d-m-Y', CsvFileTools::formatDate($row['Fecha de asiento']));
            if (empty($date)) {
                Tools::log()->error('invalid-date', ['%date%' => CsvFileTools::formatDate($row['Fecha de asiento'])]);
                continue;
            }
            $fecha = $date->format('Y-m-d');
            $year = $date->format('Y');

            // buscamos si existe el ejercicio, si no existe lo creamos
            $exercise = new Ejercicio();
            $whereExercise = [
                new DataBaseWhere('fechainicio', $year . '-01-01'),
                new DataBaseWhere('fechafin', $year . '-12-31')
            ];
            if (false === $exercise->loadFromCode('', $whereExercise)) {
                $exercise->codejercicio = $year;
                $exercise->nombre = $year;
                $exercise->fechainicio = $year . '-01-01';
                $exercise->fechafin = $year . '-12-31';
                if (false === $exercise->save()) {
                    continue;
                }
            }

            // buscamos el código de la cuenta
            $accountCode = $this->getAccountCode($row['Categoría']);

            // buscamos si existe la subcuenta, si no existe continuamos
            $account = new Subcuenta();
            $whereAccount = [
                new DataBaseWhere('codejercicio', $exercise->codejercicio),
                new DataBaseWhere('codsubcuenta', $accountCode)
            ];
            if (false === $account->loadFromCode('', $whereAccount)) {
                Tools::log()->error('subaccount-not-found', ['%subAccountCode%' => $accountCode]);
                continue;
            }

            $concepto = Tools::noHtml($row['Tipo'] . ' ' . $row['N° trans'] . ' ' . $row['Ref']);
            if (strlen($concepto) > 255) {
                $concepto = Tools::textBreak($concepto, 250);
            }

            // buscamos si existe el asiento
            $entry = new Asiento();
            $whereEntry = [
                new DataBaseWhere('fecha', $fecha),
                new DataBaseWhere('concepto', $concepto)
            ];
            if (false === $entry->loadFromCode('', $whereEntry)) {
                // no existe, lo creamos
                $entry->codejercicio = $exercise->codejercicio;
                $entry->concepto = $concepto;
                $entry->fecha = $fecha;
                $entry->importe = max([CsvFileTools::formatFloat($row['Debe']), CsvFileTools::formatFloat($row['Haber'])]);
                if (false === $entry->save()) {
                    Tools::log()->error('Error al crear el asiento ' . $row['Ref']);
                    continue;
                }
            }

            // añadimos la línea al asiento
            $line = new Partida();
            $line->idasiento = $entry->idasiento;
            $line->concepto = $entry->concepto;
            $line->codsubcuenta = $account->codsubcuenta;
            $line->idsubcuenta = $account->idsubcuenta;
            $line->debe = CsvFileTools::formatFloat($row['Debe']);
            $line->haber = CsvFileTools::formatFloat($row['Haber']);
            if (false === $line->save()) {
                Tools::log()->error('Error al crear la línea del asiento ' . $row['Ref']);
                $entry->delete();
                continue;
            }

            $this->continue = true;
            $offset++;
            $saveLines++;
        }

        return true;
    }

    private function getAccountCode(string $text): string
    {
        // devolvemos el código de la cuenta, que esté en el último paréntesis
        $matches = [];
        preg_match('/\(([^)]+)\)$/', $text, $matches);
        return $matches[1] ?? '';
    }
}
