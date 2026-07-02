<?php
/**
 * Copyright (C) 2024-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\AutoTemplates;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Paises;
use FacturaScripts\Dinamic\Model\CuentaBancoProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\CSVimport\Contract\AutoTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class FactusolSuppliers implements AutoTemplateInterface
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

            if ($csv['titles'][0] === 'Código' && in_array('N.I.F.', $csv['titles'])) {
                return true;
            } elseif ($csv['titles'][0] === 'Cód' && $csv['titles'][1] === 'Nombre') {
                return true;
            } elseif ($csv['titles'][0] === 'Cód.' && $csv['titles'][1] === 'Nombre') {
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
            // buscar proveedor
            $supplier = new Proveedor();
            $code = $row['Código'] ?? $row['Cód'] ?? $row['Cód.'];
            if (empty($code) ||
                ($supplier->loadFromCode($code) && $mode === CsvFileTools::INSERT_MODE) ||
                (false === $supplier->loadFromCode($code) && $mode === CsvFileTools::UPDATE_MODE)) {
                continue;
            }

            // guardar nuevo proveedor
            $supplier->codproveedor = $code;
            $supplier->cifnif = $row['N.I.F.'];
            $supplier->nombre = substr($row['Nombre comercial'] ?? $row['Nombre fiscal'] ?? $row['Nombre'], 0, 100);
            $supplier->telefono1 = $row['Teléfono'];

            // campos opcionales
            if (isset($row['E-mail']) && filter_var(trim($row['E-mail']), FILTER_VALIDATE_EMAIL)) {
                $supplier->email = trim($row['E-mail']);
            }

            if (isset($row['Nombre fiscal'])) {
                $supplier->razonsocial = substr($row['Nombre fiscal'], 0, 100);
                if (empty($supplier->nombre)) {
                    $supplier->nombre = $supplier->razonsocial;
                }
            }

            if (empty($supplier->nombre)) {
                $supplier->nombre = 'Proveedor ' . $supplier->codcliente;
            }

            if (false === $supplier->save()) {
                continue;
            }

            $this->continue = true;
            $offset++;
            $saveLines++;

            foreach ($supplier->getAddresses() as $address) {
                $address->direccion = $row['Domicilio'] ?? $row['Dirección'];
                $address->codpostal = $row['Cód. Postal'] ?? $row['C.P.'];
                $address->ciudad = $row['Población'];
                $address->provincia = $row['Provincia'];
                $address->codpais = static::getCountry($row['País'] ?? '');
                $address->save();
                break;
            }

            if (isset($row['IBAN del banco']) && isset($row['SWIFT del banco'])) {
                static::saveBankAccount($supplier, $row['Banco'], $row['IBAN del banco'], $row['SWIFT del banco']);
            }
        }

        return true;
    }

    protected static function getCountry(string $countryName): ?string
    {
        $countries = Paises::all();

        foreach ($countries as $country) {
            if (strtolower($country->nombre) === strtolower($countryName)) {
                return $country->codpais;
            }
        }

        return null;
    }

    protected static function saveBankAccount(Proveedor $supplier, ?string $bankName, ?string $iban, ?string $swift): void
    {
        if (empty($iban) && empty($swift)) {
            return;
        }

        // busca la cuenta del proveedor
        $bankAccountModel = new CuentaBancoProveedor();
        $where = [new DataBaseWhere('codproveedor', $supplier->codproveedor)];
        foreach ($bankAccountModel->all($where) as $bank) {
            $bank->descripcion = $bankName;
            $bank->iban = $iban;
            $bank->swift = $swift;
            $bank->save();
            return;
        }

        // si no hay banco previo, crea uno nuevo
        $newBank = new CuentaBancoProveedor();
        $newBank->codproveedor = $supplier->codproveedor;
        $newBank->descripcion = $bankName;
        $newBank->iban = $iban;
        $newBank->swift = $swift;
        $newBank->save();
    }
}
