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
class SageAccountingEntries implements AutoTemplateInterface
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

            if ($csv['titles'][0] === 'Asiento' && $csv['titles'][1] === 'Fecha' && $csv['titles'][2] === 'Cuenta') {
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
            $date = DateTime::createFromFormat('d-m-Y', CsvFileTools::formatDate($row['Fecha']));
            if (empty($date)) {
                Tools::log()->error('invalid-date', ['%date%' => CsvFileTools::formatDate($row['Fecha'])]);
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

            // buscamos si existe la subcuenta, si no existe continuamos
            $account = new Subcuenta();
            $whereAccount = [
                new DataBaseWhere('codejercicio', $exercise->codejercicio),
                new DataBaseWhere('codsubcuenta', $row['Cuenta'])
            ];
            if (false === $account->loadFromCode('', $whereAccount)) {
                Tools::log()->error('subaccount-not-found', ['%subAccountCode%' => $row['Cuenta']]);
                continue;
            }

            // buscamos si existe el asiento
            $entry = new Asiento();
            $whereEntry = [
                new DataBaseWhere('fecha', $fecha),
                new DataBaseWhere('documento', $row['Asiento'])
            ];
            if (false === $entry->loadFromCode('', $whereEntry)) {
                // no existe, lo creamos
                $entry->codejercicio = $exercise->codejercicio;
                $entry->concepto = Tools::noHtml($row['Concepto']);
                if ($row['Concepto'] === 'ASIENTO DE APERTURA') {
                    $entry->operacion = $entry::OPERATION_OPENING;
                }
                $entry->documento = $row['Asiento'];
                $entry->fecha = $fecha;
                $entry->importe = max([CsvFileTools::formatFloat($row['Debe']), CsvFileTools::formatFloat($row['Haber'])]);
                if (false === $entry->save()) {
                    Tools::log()->error('Error al crear el asiento ' . $row['Asiento']);
                    continue;
                }
            }

            // buscamos si existe la partida dentro del asiento
            $line = new Partida();
            $whereLine = [
                new DataBaseWhere('idasiento', $entry->idasiento),
                new DataBaseWhere('documento', $row['Linea'])
            ];
            if ($line->loadFromCode('', $whereLine) && $mode === CsvFileTools::INSERT_MODE
                || false === $line->loadFromCode('', $whereLine) && $mode === CsvFileTools::UPDATE_MODE) {
                continue;
            }

            $line->idasiento = $entry->idasiento;
            $line->concepto = Tools::noHtml($row['Concepto']);
            $line->codsubcuenta = $account->codsubcuenta;
            $line->idsubcuenta = $account->idsubcuenta;
            $line->debe = CsvFileTools::formatFloat($row['Debe']);
            $line->haber = CsvFileTools::formatFloat($row['Haber']);
            $line->tasaconv = CsvFileTools::formatFloat($row['Cambio']);
            $line->documento = $row['Linea'];
            if (false === $line->save()) {
                Tools::log()->error('Error al crear la línea del asiento ' . $row['Asiento']);
                $entry->delete();
                continue;
            }

            $this->continue = true;
            $offset++;
            $saveLines++;
        }

        return true;
    }
}
