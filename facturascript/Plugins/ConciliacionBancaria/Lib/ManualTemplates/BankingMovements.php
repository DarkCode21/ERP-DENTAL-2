<?php
/**
 * Copyright (C) 2023-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\ConciliacionBancaria\Lib\ManualTemplates;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Dinamic\Model\MovimientoBanco;
use FacturaScripts\Plugins\CSVimport\Contract\ManualTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;
use FacturaScripts\Plugins\CSVimport\Lib\ManualTemplates\ManualTemplateClass;

/**
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class BankingMovements extends ManualTemplateClass implements ManualTemplateInterface
{
    public function getDataFields(): array
    {
        return [
            'cuentasbanco_movimientos.amount' => ['title' => 'amount'],
            'cuentasbanco_movimientos.date' => ['title' => 'date'],
            'custom.expenses' => ['title' => 'expenses'],
            'custom.incomes' => ['title' => 'incomes'],
            'cuentasbanco_movimientos.observations' => ['title' => 'observations'],
        ];
    }

    public function getFieldsToColumn(): array
    {
        return [];
    }

    public static function getProfile(): string
    {
        return 'banking-movements';
    }

    public function getRequiredFieldsAnd(): array
    {
        return [];
    }

    public function getRequiredFieldsOr(): array
    {
        return [];
    }

    public function importItem(array $item): bool
    {
        $this->checkAmount($item);

        // comprobamos si existe un movimiento bancario con los mismos campos
        $whereMovement = [new DataBaseWhere('codcuenta', $this->model->codcuenta)];
        foreach (static::getDataFields() as $field => $data) {
            // si el field contiene "cuentasbanco_movimientos." lo añadimos
            if (str_contains($field, 'cuentasbanco_movimientos.')
                && isset($item[$field]) && false === empty($item[$field])) {
                $whereMovement[] = new DataBaseWhere($field, $item[$field]);
            }
        }

        $bankingMovement = new MovimientoBanco();
        if ($bankingMovement->loadFromCode('', $whereMovement) && $this->model->mode === CsvFileTools::INSERT_MODE
            || false === $bankingMovement->loadFromCode('', $whereMovement) && $this->model->mode === CsvFileTools::UPDATE_MODE) {
            return false;
        }

        if (false === $this->setModelValues($bankingMovement, $item, 'cuentasbanco_movimientos.')) {
            return false;
        }

        if (empty($bankingMovement->codcuenta)) {
            $bankingMovement->codcuenta = $this->model->codcuenta;
        }

        if (empty($bankingMovement->reconciled) || false === is_bool($bankingMovement->reconciled)) {
            $bankingMovement->reconciled = false;
        }

        return $bankingMovement->save();
    }

    protected function checkAmount(array &$item): void
    {
        // si ya existe el campo amount, no hacemos nada
        if (isset($item['cuentasbanco_movimientos.amount']) &&
            false === empty($item['cuentasbanco_movimientos.amount'])) {
            return;
        }

        // si hay gastos e ingresos, hacemos la diferencia
        if (isset($item['custom.expenses']) &&
            false === empty($item['custom.expenses']) &&
            isset($item['custom.incomes']) &&
            false === empty($item['custom.incomes'])) {
            $incomes = abs(CsvFileTools::formatFloat($item['custom.incomes']));
            $expenses = -abs(CsvFileTools::formatFloat($item['custom.expenses']));
            $item['cuentasbanco_movimientos.amount'] = $incomes + $expenses;
            unset($item['custom.expenses']);
            unset($item['custom.incomes']);
            return;
        }

        // comprobamos si hay gastos, para sustituirlos por el campo amount
        if (isset($item['custom.expenses']) && false === empty($item['custom.expenses'])) {
            $item['cuentasbanco_movimientos.amount'] = -abs(CsvFileTools::formatFloat($item['custom.expenses']));
            unset($item['custom.expenses']);
            return;
        }

        // comprobamos si hay ingresos, para sustituirlos por el campo amount
        if (isset($item['custom.incomes']) && false === empty($item['custom.incomes'])) {
            $item['cuentasbanco_movimientos.amount'] = abs(CsvFileTools::formatFloat($item['custom.incomes']));
            unset($item['custom.incomes']);
        }
    }

    protected function setModelValues(ModelClass &$model, array $values, string $prefix): bool
    {
        if (false === parent::setModelValues($model, $values, $prefix)) {
            return false;
        }

        foreach ($model->getModelFields() as $key => $field) {
            if (!isset($values[$prefix . $key])) {
                continue;
            }

            if ($field['name'] == 'date') {
                $model->date = CsvFileTools::formatDate($values[$prefix . $key]);
            }
        }

        return true;
    }
}