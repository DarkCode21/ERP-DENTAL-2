<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\AutoTemplates;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\GruposClientes;
use FacturaScripts\Core\Validator;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\CuentaBancoCliente;
use FacturaScripts\Plugins\CSVimport\Contract\AutoTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez  <carlos@facturascripts.com>
 */
class FS17Customers implements AutoTemplateInterface
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

            if ($csv['titles'][0] === 'codcliente' && $csv['titles'][1] === 'nombre') {
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
            $customer = new Cliente();
            if (empty($row['codcliente'])
                || $customer->loadFromCode($row['codcliente']) && $mode === CsvFileTools::INSERT_MODE
                || false === $customer->loadFromCode($row['codcliente']) && $mode === CsvFileTools::UPDATE_MODE) {
                continue;
            }

            // guardamos el cliente
            $customer->loadFromData($row, ['codproveedor', 'codpago', 'codserie', 'idcontacto']);

            // excluimos el email si no es válido
            $customer->email = trim($customer->email);
            if (false === Validator::email($customer->email)) {
                $customer->email = '';
            }

            // excluimos la web si no es válida
            $customer->web = trim($customer->web);
            if (false === Validator::url($customer->web)) {
                $customer->web = '';
            }

            // comprobamos el grupo
            if (empty($customer->codgrupo)) {
                $customer->codgrupo = null;
            } else {
                // comprobamos que el grupo existe
                $grupo = GruposClientes::get($customer->codgrupo);
                if (empty($grupo->codgrupo)) {
                    $customer->codgrupo = null;
                }
            }

            if (false === $customer->save()) {
                continue;
            }

            $this->continue = true;
            $offset++;
            $saveLines++;

            foreach ($customer->getAddresses() as $address) {
                $address->direccion = $row['direccion'];
                $address->codpostal = $row['codpostal'];
                $address->ciudad = $row['ciudad'];
                $address->provincia = $row['provincia'];
                $address->codpais = $row['pais'];
                $address->save();
                break;
            }

            static::saveBankAccount($customer, 'Bank', $row['iban'], $row['swift']);
        }

        return true;
    }

    protected static function saveBankAccount($customer, $bankName, $iban, $swift): void
    {
        if (empty($iban) && empty($swift)) {
            return;
        }

        // Find supplier bank accounts
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
        $newBank->iban = $iban;
        $newBank->swift = $swift;
        $newBank->save();
    }
}
