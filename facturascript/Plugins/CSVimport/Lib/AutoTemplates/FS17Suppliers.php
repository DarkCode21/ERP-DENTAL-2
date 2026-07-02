<?php
/**
 * Copyright (C) 2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\AutoTemplates;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\CuentaBancoProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\CSVimport\Contract\AutoTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class FS17Suppliers implements AutoTemplateInterface
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

            if ($csv['titles'][0] === 'codproveedor' && $csv['titles'][1] === 'nombre') {
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
            $supplier = new Proveedor();
            if (empty($row['codproveedor'])
                || $supplier->loadFromCode($row['codproveedor']) && $mode === CsvFileTools::INSERT_MODE
                || false === $supplier->loadFromCode($row['codproveedor']) && $mode === CsvFileTools::UPDATE_MODE) {
                continue;
            }

            // save new supplier
            $supplier->loadFromData($row, ['codcliente', 'codpago', 'codserie', 'idcontacto']);

            // exclude bad emails
            $supplier->email = trim($supplier->email);
            if (false === filter_var($supplier->email, FILTER_VALIDATE_EMAIL)) {
                $supplier->email = '';
            }

            if (false === $supplier->save()) {
                continue;
            }

            $this->continue = true;
            $offset++;
            $saveLines++;

            foreach ($supplier->getAddresses() as $address) {
                $address->direccion = $row['direccion'];
                $address->codpostal = $row['codpostal'];
                $address->ciudad = $row['ciudad'];
                $address->provincia = $row['provincia'];
                $address->codpais = $row['pais'];
                $address->save();
                break;
            }

            static::saveBankAccount($supplier, 'Bank', $row['iban'], $row['swift']);
        }

        return true;
    }

    protected static function saveBankAccount($supplier, $bankName, $iban, $swift): void
    {
        if (empty($iban) && empty($swift)) {
            return;
        }

        // Find supplier bank accounts
        $bankAccountModel = new CuentaBancoProveedor();
        $where = [new DataBaseWhere('codproveedor', $supplier->codproveedor)];
        foreach ($bankAccountModel->all($where) as $bank) {
            $bank->descripcion = $bankName;
            $bank->iban = $iban;
            $bank->swift = $swift;
            $bank->save();
            return;
        }

        // No previous bank accounts? Then create a new one
        $newBank = new CuentaBancoProveedor();
        $newBank->codproveedor = $supplier->codproveedor;
        $newBank->iban = $iban;
        $newBank->swift = $swift;
        $newBank->save();
    }
}
