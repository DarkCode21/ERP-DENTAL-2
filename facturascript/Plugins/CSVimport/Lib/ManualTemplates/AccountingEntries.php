<?php
/**
 * Copyright (C) 2020-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\ManualTemplates;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Dinamic\Model\Subcuenta;
use FacturaScripts\Plugins\CSVimport\Contract\ManualTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class AccountingEntries extends ManualTemplateClass implements ManualTemplateInterface
{
    public function getDataFields(): array
    {
        return [
            'asiento' => ['title' => 'accounting-entry'],
            'linea' => ['title' => 'line'],
            'fecha' => ['title' => 'date'],
            'concepto' => ['title' => 'concept'],
            'cuenta' => ['title' => 'account'],
            'debe' => ['title' => 'debit'],
            'haber' => ['title' => 'credit'],
            'idempresa' => ['title' => 'company-id'],
        ];
    }

    public function getFieldsToColumn(): array
    {
        return [];
    }

    public static function getProfile(): string
    {
        return 'accounting-entries';
    }

    public function getRequiredFieldsAnd(): array
    {
        return ['cuenta', 'debe', 'haber', 'fecha', 'concepto', 'asiento'];
    }

    public function getRequiredFieldsOr(): array
    {
        return [];
    }

    public function importItem(array $item): bool
    {
        // obtener año de la fecha de la partida
        $fecha = CsvFileTools::formatDate($item['fecha']);
        $year = date('Y', strtotime($fecha));

        // obtenemos la empresa
        $idempresa = false === empty($item['idempresa'])
            ? $item['idempresa']
            : Tools::settings('default', 'idempresa');

        // si la empresa no existe, paramos la ejecución
        if (empty(Empresas::get($idempresa)->primaryColumnValue())) {
            Tools::log()->error('company-not-found', ['%companyId%' => $idempresa]);
            return false;
        }

        // busca si existe el ejercicio, si no existe lo crea
        $exercise = new Ejercicio();
        $whereExercise = [
            new DataBaseWhere('fechainicio', $year . '-01-01'),
            new DataBaseWhere('fechafin', $year . '-12-31'),
            new DataBaseWhere('idempresa', $idempresa)
        ];

        if (false === $exercise->loadFromCode('', $whereExercise)) {
            $exercise->codejercicio = $year;
            $exercise->nombre = $year;
            $exercise->fechainicio = $year . '-01-01';
            $exercise->fechafin = $year . '-12-31';
            $exercise->idempresa = $idempresa;
            if (false === $exercise->save()) {
                Tools::log()->error('error-when-creating-the-fiscal-year-for-the-year', ['%year%' => $year]);
                return false;
            }
        }

        // busca si existe la subcuenta, si no existe paramos la ejecución
        $subaccount = new Subcuenta();
        $whereAccount = [
            new DataBaseWhere('codsubcuenta', $item['cuenta']),
            new DataBaseWhere('codejercicio', $exercise->codejercicio)
        ];
        if (false === $subaccount->loadFromCode('', $whereAccount)) {
            Tools::log()->error('subaccount-not-found', ['%subAccountCode%' => $item['cuenta']]);
            return false;
        }

        // busca si existe el asiento
        $entry = new Asiento();
        $where = [
            new DataBaseWhere('codejercicio', $exercise->codejercicio),
            new DataBaseWhere('fecha', $fecha),
            new DataBaseWhere('documento', $item['asiento']),
            new DataBaseWhere('idempresa', $exercise->idempresa),
        ];

        // no existe, lo creamos
        if (false === $entry->loadFromCode('', $where)) {
            $entry->idempresa = $exercise->idempresa;
            $entry->codejercicio = $exercise->codejercicio;
            $entry->concepto = Tools::noHtml($item['concepto']);
            if ($item['concepto'] === 'ASIENTO DE APERTURA') {
                $entry->operacion = $entry::OPERATION_OPENING;
            }
            $entry->documento = $item['asiento'];
            $entry->fecha = $fecha;
            $entry->importe = max([CsvFileTools::formatFloat($item['debe']), CsvFileTools::formatFloat($item['haber'])]);
            if (false === $entry->save()) {
                Tools::log()->error('error-creating-entry', ['%entry%' => $item['asiento']]);
                return false;
            }
        }

        // busca si existe la partida dentro del asiento
        $line = new Partida();
        $where = [new DataBaseWhere('idasiento', $entry->idasiento),];

        if (isset($item['linea']) && false === empty($item['linea'])) {
            $where[] = new DataBaseWhere('documento', $item['linea']);
        } else {
            $where[] = new DataBaseWhere('codsubcuenta', $item['cuenta']);
            $where[] = new DataBaseWhere('debe', CsvFileTools::formatFloat($item['debe']));
            $where[] = new DataBaseWhere('haber', CsvFileTools::formatFloat($item['haber']));
        }

        if ($line->loadFromCode('', $where) && $this->model->mode === CsvFileTools::INSERT_MODE
            || false === $line->loadFromCode('', $where) && $this->model->mode === CsvFileTools::UPDATE_MODE) {
            return false;
        }

        $line->idasiento = $entry->idasiento;
        $line->concepto = Tools::noHtml($item['concepto']);
        $line->codsubcuenta = Tools::noHtml($item['cuenta']);
        $line->idsubcuenta = $subaccount->idsubcuenta;
        $line->debe = CsvFileTools::formatFloat($item['debe']);
        $line->haber = CsvFileTools::formatFloat($item['haber']);
        $line->documento = $item['linea'] ?? '';
        if (false === $line->save()) {
            Tools::log()->error('accounting-lines-error', ['%entry%' => $item['asiento']]);
            return false;
        }

        return true;
    }
}
