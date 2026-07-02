<?php
/**
 * Copyright (C) 2020-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\ManualTemplates;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Paises;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\CuentaBancoCliente;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\GrupoClientes;
use FacturaScripts\Dinamic\Model\Pais;
use FacturaScripts\Dinamic\Model\Serie;
use FacturaScripts\Dinamic\Model\Tarifa;
use FacturaScripts\Plugins\CSVimport\Contract\ManualTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class Customers extends ManualTemplateClass implements ManualTemplateInterface
{
    public function getDataFields(): array
    {
        return [
            'clientes.codcliente' => ['title' => 'customer-code'],
            'clientes.nombre' => ['title' => 'name'],
            'clientes.razonsocial' => ['title' => 'business-name'],
            'clientes.cifnif' => ['title' => 'cifnif'],
            'clientes.telefono1' => ['title' => 'phone'],
            'clientes.telefono2' => ['title' => 'phone2'],
            'clientes.fax' => ['title' => 'fax'],
            'clientes.email' => ['title' => 'email'],
            'clientes.web' => ['title' => 'web'],
            'clientes.codsubcuenta' => ['title' => 'subaccount'],
            'clientes.codserie' => ['title' => 'serie'],
            'clientes.codpago' => ['title' => 'method-payment'],
            'clientes.personafisica' => ['title' => 'is-person'],
            'clientes.tipoidfiscal' => ['title' => 'fiscal-id'],
            'clientes.codtarifa' => ['title' => 'rate'],
            'clientes.codgrupo' => ['title' => 'group'],
            'clientes.observaciones' => ['title' => 'observations'],
            'clientes.codagente' => ['title' => 'agent'],
            'clientes.fechaalta' => ['title' => 'discharge-date'],
            'clientes.fechabaja' => ['title' => 'date-of-suspension'],
            'clientes.idcontactofact' => ['title' => 'billing-address-id'],
            'clientes.idcontactoenv' => ['title' => 'shipping-address-id'],
            'contactos.direccion' => ['title' => 'address'],
            'contactos.apartado' => ['title' => 'post-office-box'],
            'contactos.codpostal' => ['title' => 'zip-code'],
            'contactos.ciudad' => ['title' => 'city'],
            'contactos.provincia' => ['title' => 'province'],
            'contactos.codpais' => ['title' => 'country'],
            'cuentasbcocli.descripcion' => ['title' => 'bank'],
            'cuentasbcocli.iban' => ['title' => 'iban'],
            'cuentasbcocli.swift' => ['title' => 'swift']
        ];
    }

    public function getFieldsToColumn(): array
    {
        return [];
    }

    public static function getProfile(): string
    {
        return 'customers';
    }

    public function getRequiredFieldsAnd(): array
    {
        return [];
    }

    public function getRequiredFieldsOr(): array
    {
        return ['clientes.codcliente', 'clientes.nombre', 'clientes.cifnif'];
    }

    public function importItem(array $item): bool
    {
        $where = [];
        if (isset($item['clientes.codcliente']) && !empty($item['clientes.codcliente'])) {
            $where[] = new DataBaseWhere('codcliente', $item['clientes.codcliente']);
        } elseif (isset($item['clientes.nombre']) && !empty($item['clientes.nombre'])) {
            $where[] = new DataBaseWhere('nombre', $item['clientes.nombre']);
        } elseif (isset($item['clientes.cifnif']) && !empty($item['clientes.cifnif'])) {
            $where[] = new DataBaseWhere('cifnif', $item['clientes.cifnif']);
        }
        if (empty($where)) {
            return false;
        }

        $customer = new Cliente();
        if ($customer->loadFromCode('', $where) && $this->model->mode === CsvFileTools::INSERT_MODE ||
            false === $customer->loadFromCode('', $where) && $this->model->mode === CsvFileTools::UPDATE_MODE) {
            return false;
        }

        if (false === $this->setModelValues($customer, $item, 'clientes.')) {
            return false;
        }
        if ($customer->cifnif === null) {
            $customer->cifnif = '';
        }
        return $customer->save()
            && $this->importAddress($customer, $item)
            && $this->importBankAccount($customer, $item);
    }

    /**
     * @param Cliente $customer
     * @param array $item
     *
     * @return bool
     */
    private function importAddress($customer, $item): bool
    {
        foreach ($customer->getAddresses() as $address) {
            if (false === $this->setModelValues($address, $item, 'contactos.')) {
                return false;
            }
            return $address->save();
        }

        return false;
    }

    /**
     * @param Cliente $customer
     * @param array $item
     *
     * @return bool
     */
    private function importBankAccount($customer, $item): bool
    {
        $description = $item['cuentasbcocli.descripcion'] ?? '';
        $iban = $item['cuentasbcocli.iban'] ?? '';
        $swift = CsvFileTools::formatString($item['cuentasbcocli.swift'] ?? '', 11);
        if (empty($iban) && empty($swift)) {
            return true;
        }

        $bankAccountModel = new CuentaBancoCliente();
        $where = [new DataBaseWhere('codcliente', $customer->codcliente)];
        foreach ($bankAccountModel->all($where) as $bank) {
            $bank->descripcion = $description;
            $bank->iban = $iban;
            $bank->swift = $swift;
            return $bank->save();
        }

        // new bank account
        $newBankAccount = new CuentaBancoCliente();
        $newBankAccount->codcliente = $customer->codcliente;
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
                case 'codagente':
                    $agent = new Agente();
                    if (false === $agent->loadFromCode($values[$prefix . $key])) {
                        $model->{$key} = null;
                    }
                    break;

                case 'codgrupo':
                    $group = new GrupoClientes();
                    if (false === $group->loadFromCode($values[$prefix . $key])) {
                        $model->{$key} = null;
                    }
                    break;

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

                case 'codpostal':
                    $model->{$key} = CsvFileTools::formatString($values[$prefix . $key], 10);
                    break;

                case 'codserie':
                    $serie = new Serie();
                    if (false === $serie->loadFromCode($values[$prefix . $key])) {
                        $model->{$key} = null;
                    }
                    break;

                case 'codpago':
                    $payment = new FormaPago();
                    if (false === $payment->loadFromCode($values[$prefix . $key])) {
                        $model->{$key} = null;
                    }
                    break;

                case 'codtarifa':
                    $rate = new Tarifa();
                    if (false === $rate->loadFromCode($values[$prefix . $key])) {
                        $model->{$key} = null;
                    }
                    break;

                case 'fechaalta':
                case 'fechabaja':
                    if (false === empty($values[$prefix . $key])) {
                        $model->{$key} = date('d-m-Y', strtotime($values[$prefix . $key]));
                    }
                    break;

                case 'idcontactofact':
                case 'idcontactoenv':
                    if (empty($values[$prefix . $key]) || false === is_numeric($values[$prefix . $key])) {
                        Tools::log()->error('invalid-contact-id', ['%id%' => $values[$prefix . $key]]);
                        return false;
                    }
                    $address = new Contacto();
                    if ($address->loadFromCode($values[$prefix . $key])) {
                        $model->{$key} = $address->idcontacto;
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
