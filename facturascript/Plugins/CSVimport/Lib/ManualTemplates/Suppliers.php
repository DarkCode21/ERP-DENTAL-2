<?php
/**
 * Copyright (C) 2020-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\ManualTemplates;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Paises;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Dinamic\Model\CuentaBancoProveedor;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\Pais;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Dinamic\Model\Serie;
use FacturaScripts\Plugins\CSVimport\Contract\ManualTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class Suppliers extends ManualTemplateClass implements ManualTemplateInterface
{
    public function getDataFields(): array
    {
        return [
            'proveedores.codproveedor' => ['title' => 'supplier-code'],
            'proveedores.nombre' => ['title' => 'name'],
            'proveedores.razonsocial' => ['title' => 'business-name'],
            'proveedores.cifnif' => ['title' => 'cifnif'],
            'proveedores.telefono1' => ['title' => 'phone'],
            'proveedores.telefono2' => ['title' => 'phone2'],
            'proveedores.fax' => ['title' => 'fax'],
            'proveedores.email' => ['title' => 'email'],
            'proveedores.web' => ['title' => 'web'],
            'proveedores.codsubcuenta' => ['title' => 'subaccount'],
            'proveedores.codserie' => ['title' => 'serie'],
            'proveedores.codpago' => ['title' => 'payment-method'],
            'proveedores.personafisica' => ['title' => 'is-person'],
            'proveedores.tipoidfiscal' => ['title' => 'fiscal-id'],
            'proveedores.observaciones' => ['title' => 'observations'],
            'contactos.direccion' => ['title' => 'address'],
            'contactos.apartado' => ['title' => 'post-office-box'],
            'contactos.codpostal' => ['title' => 'zip-code'],
            'contactos.ciudad' => ['title' => 'city'],
            'contactos.provincia' => ['title' => 'province'],
            'contactos.codpais' => ['title' => 'country'],
            'cuentasbcopro.descripcion' => ['title' => 'bank'],
            'cuentasbcopro.iban' => ['title' => 'iban'],
            'cuentasbcopro.swift' => ['title' => 'swift']
        ];
    }

    public function getFieldsToColumn(): array
    {
        return [];
    }

    public static function getProfile(): string
    {
        return 'suppliers';
    }

    public function getRequiredFieldsAnd(): array
    {
        return [];
    }

    public function getRequiredFieldsOr(): array
    {
        return ['proveedores.codproveedor', 'proveedores.nombre', 'proveedores.cifnif'];
    }

    public function importItem(array $item): bool
    {
        $where = [];
        if (isset($item['proveedores.codproveedor']) && !empty($item['proveedores.codproveedor'])) {
            $where[] = new DataBaseWhere('codproveedor', $item['proveedores.codproveedor']);
        } elseif (isset($item['proveedores.nombre']) && !empty($item['proveedores.nombre'])) {
            $where[] = new DataBaseWhere('nombre', $item['proveedores.nombre']);
        } elseif (isset($item['proveedores.cifnif']) && !empty($item['proveedores.cifnif'])) {
            $where[] = new DataBaseWhere('cifnif', $item['proveedores.cifnif']);
        }

        if (empty($where)) {
            return false;
        }

        $supplier = new Proveedor();
        if ($supplier->loadFromCode('', $where) && $this->model->mode === CsvFileTools::INSERT_MODE
            || false === $supplier->loadFromCode('', $where) && $this->model->mode === CsvFileTools::UPDATE_MODE) {
            return false;
        }

        if (false === $this->setModelValues($supplier, $item, 'proveedores.')) {
            return false;
        }
        if ($supplier->cifnif === null) {
            $supplier->cifnif = '';
        }
        if ($supplier->save()) {
            $this->importAddress($supplier, $item);
            $this->importBankAccount($supplier, $item);
            return true;
        }

        return false;
    }

    /**
     * @param Proveedor $supplier
     * @param array $item
     *
     * @return bool
     */
    private function importAddress($supplier, $item): bool
    {
        foreach ($supplier->getAddresses() as $address) {
            if (false === $this->setModelValues($address, $item, 'contactos.')) {
                return false;
            }
            return $address->save();
        }

        return false;
    }

    /**
     * @param Proveedor $supplier
     * @param array $item
     *
     * @return bool
     */
    private function importBankAccount($supplier, $item): bool
    {
        $description = $item['cuentasbcopro.descripcion'] ?? '';
        $iban = $item['cuentasbcopro.iban'] ?? '';
        $swift = CsvFileTools::formatString($item['cuentasbcopro.swift'] ?? '', 11);
        if (empty($iban) && empty($swift)) {
            return true;
        }

        $bankAccountModel = new CuentaBancoProveedor();
        $where = [new DataBaseWhere('codproveedor', $supplier->codproveedor)];
        foreach ($bankAccountModel->all($where) as $bank) {
            $bank->descripcion = $description;
            $bank->iban = $iban;
            $bank->swift = $swift;
            return $bank->save();
        }

        // new bank account
        $newBankAccount = new CuentaBancoProveedor();
        $newBankAccount->codproveedor = $supplier->codproveedor;
        $newBankAccount->descripcion = $description;
        $newBankAccount->iban = $iban;
        $newBankAccount->swift = $swift;
        return $newBankAccount->save();
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

            switch ($field['name']) {
                case 'codpais':
                    foreach (Paises::all() as $country) {
                        if (strtolower($country->codpais) === strtolower($values[$prefix . $key])
                            || strtolower($country->nombre) === strtolower($values[$prefix . $key])
                            || strtolower($country->codiso) === strtolower($values[$prefix . $key])) {
                            $model->{$key} = $country->codpais;
                            break 2;
                        }
                    }

                    // creamos el país
                    $country = new Pais();
                    $country->codpais = CsvFileTools::formatString($values[$prefix . $key], 3);
                    $country->nombre = $values[$prefix . $key];
                    if (false === $country->save()) {
                        return false;
                    }

                    $model->{$key} = $country->codpais;
                    break;

                case 'codpago':
                    $payment = new FormaPago();
                    if (false === $payment->loadFromCode($values[$prefix . $key])) {
                        $model->{$key} = null;
                    }
                    break;

                case 'codpostal':
                    $model->{$key} = CsvFileTools::formatString($values[$prefix . $key], 10);
                    break;

                case 'codserie':
                    $serie = new Serie();
                    if (false === $serie->loadFromCode($values[$prefix . $key])) {
                        $model->{$key} = null;
                    }
                    break;

                case 'personafisica':
                    // si personafisica esta vacío lo ponemos a false
                    if (empty($values[$prefix . 'personafisica'])) {
                        $model->personafisica = false;
                    }
                    break;
            }
        }

        // Si no se ha asignado ninguna columna para personafisica y el cifnif empieza por A o B, asignar personafisica = false.
        if (false === isset($values[$prefix . 'personafisica']) && isset($values[$prefix . 'cifnif']) && false !== preg_match('/^(A|B)/', strtoupper($values[$prefix . 'cifnif']))) {
            $model->personafisica = false;
        }

        // Si no se ha asignado ninguna columna para tipoidfiscal y el cifnif empieza por A o B, asignar tipoidfiscal = 'CIF'.
        if (false === isset($values[$prefix . 'tipoidfiscal']) && isset($values[$prefix . 'cifnif']) && false !== preg_match('/^(A|B)/', strtoupper($values[$prefix . 'cifnif']))) {
            $model->tipoidfiscal = 'CIF';
        }

        return true;
    }
}
