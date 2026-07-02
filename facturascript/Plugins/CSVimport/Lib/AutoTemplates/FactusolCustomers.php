<?php
/**
 * Copyright (C) 2020-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\AutoTemplates;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\CuentaBancoCliente;
use FacturaScripts\Plugins\CSVimport\Contract\AutoTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class FactusolCustomers implements AutoTemplateInterface
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
        if ($profile !== 'customers') {
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
            // find customer
            $customer = new Cliente();
            $code = $row['Código'] ?? $row['Cód'] ?? $row['Cód.'];
            if (empty($code) ||
                ($customer->loadFromCode($code) && $mode === CsvFileTools::INSERT_MODE) ||
                (false === $customer->loadFromCode($code) && $mode === CsvFileTools::UPDATE_MODE)) {
                continue;
            }

            // save new customer
            $customer->codcliente = $code;
            $customer->cifnif = $row['N.I.F.'];
            $customer->nombre = substr($row['Nombre comercial'] ?? $row['Nombre'], 0, 100);
            $customer->telefono1 = $row['Teléfono'];

            // optional fields
            if (isset($row['E-mail']) && filter_var(trim($row['E-mail']), FILTER_VALIDATE_EMAIL)) {
                $customer->email = trim($row['E-mail']);
            }

            if (isset($row['Fax'])) {
                $customer->fax = $row['Fax'];
            }

            if (isset($row['Móvil'])) {
                $customer->telefono2 = $row['Móvil'];
            }

            if (isset($row['Nombre fiscal'])) {
                $customer->razonsocial = substr($row['Nombre fiscal'], 0, 100);
                if (empty($customer->nombre)) {
                    $customer->nombre = $customer->razonsocial;
                }
            }

            if (empty($customer->nombre)) {
                $customer->nombre = 'Cliente ' . $customer->codcliente;
            }

            if (false === $customer->save()) {
                continue;
            }

            $this->continue = true;
            $offset++;
            $saveLines++;

            foreach ($customer->getAddresses() as $address) {
                $address->direccion = $row['Domicilio'] ?? $row['Dirección'];
                $address->codpostal = $row['Cód. Postal'] ?? $row['C.P.'];
                $address->ciudad = $row['Población'];
                $address->provincia = $row['Provincia'];
                $address->save();
                break;
            }

            if (isset($row['IBAN del banco']) && isset($row['SWIFT del banco'])) {
                static::saveBankAccount($customer, $row['Banco'], $row['IBAN del banco'], $row['SWIFT del banco']);
            }
        }

        return true;
    }

    protected static function saveBankAccount(Cliente $customer, ?string $bankName, ?string $iban, ?string $swift): void
    {
        if (empty($iban) && empty($swift)) {
            return;
        }

        // Find customer bank accounts
        $bankAccountModel = new CuentaBancoCliente();
        $where = [new DataBaseWhere('codcliente', $customer->codcliente)];
        foreach ($bankAccountModel->all($where) as $bank) {
            $bank->descripcion = $bankName;
            $bank->iban = $iban;
            $bank->swift = $swift;
            $bank->save();
            return;
        }

        // No previous bank accounts? Then create a new one
        $newBank = new CuentaBancoCliente();
        $newBank->codcliente = $customer->codcliente;
        $newBank->descripcion = $bankName;
        $newBank->iban = $iban;
        $newBank->swift = $swift;
        $newBank->save();
    }
}
