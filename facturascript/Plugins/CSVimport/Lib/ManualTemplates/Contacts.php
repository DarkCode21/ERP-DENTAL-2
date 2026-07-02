<?php
/**
 * Copyright (C) 2020-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\ManualTemplates;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Agentes;
use FacturaScripts\Core\DataSrc\Paises;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\Pais;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\CSVimport\Contract\ManualTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Contacts extends ManualTemplateClass implements ManualTemplateInterface
{
    public function getDataFields(): array
    {
        return [
            'contactos.idcontacto' => ['title' => 'code'],
            'contactos.nombre' => ['title' => 'name'],
            'contactos.apellidos' => ['title' => 'surname'],
            'contactos.tipoidfiscal' => ['title' => 'fiscal-id'],
            'contactos.cifnif' => ['title' => 'cifnif'],
            'contactos.empresa' => ['title' => 'company'],
            'contactos.cargo' => ['title' => 'position'],
            'contactos.telefono1' => ['title' => 'phone'],
            'contactos.telefono2' => ['title' => 'phone2'],
            'contactos.email' => ['title' => 'email'],
            'contactos.direccion' => ['title' => 'address'],
            'contactos.apartado' => ['title' => 'post-office-box'],
            'contactos.codpostal' => ['title' => 'zip-code'],
            'contactos.ciudad' => ['title' => 'city'],
            'contactos.provincia' => ['title' => 'province'],
            'contactos.codpais' => ['title' => 'country'],
            'contactos.personafisica' => ['title' => 'is-person'],
            'contactos.idfuente' => ['title' => 'source'],
            'contactos.admitemarketing' => ['title' => 'allow-marketing'],
            'contactos.observaciones' => ['title' => 'observations'],
            'contactos.codagente' => ['title' => 'agent'],
            'contactos.codcliente' => ['title' => 'customer-code'],
            'contactos.codproveedor' => ['title' => 'supplier-code'],
            'clientes.idcontactofact' => ['title' => 'customer-billing-contact'],
            'clientes.idcontactoenv' => ['title' => 'customer-shipping-contact'],
            'proveedores.idcontacto' => ['title' => 'supplier-billing-contact'],
        ];
    }

    public function getFieldsToColumn(): array
    {
        return [];
    }

    public static function getProfile(): string
    {
        return 'contacts';
    }

    public function getRequiredFieldsAnd(): array
    {
        return [];
    }

    public function getRequiredFieldsOr(): array
    {
        return ['contactos.idcontacto', 'contactos.nombre', 'contactos.cifnif'];
    }

    public function importItem(array $item): bool
    {
        $where = [];
        if (isset($item['contactos.idcontacto']) && !empty($item['contactos.idcontacto'])) {
            $where[] = new DataBaseWhere('idcontacto', $item['contactos.idcontacto']);
        } elseif (isset($item['contactos.nombre']) && !empty($item['contactos.nombre'])) {
            $where[] = new DataBaseWhere('nombre', $item['contactos.nombre']);
        } elseif (isset($item['contactos.cifnif']) && !empty($item['contactos.cifnif'])) {
            $where[] = new DataBaseWhere('cifnif', $item['contactos.cifnif']);
        }

        if (empty($where)) {
            return false;
        }

        $contact = new Contacto();
        if ($contact->loadFromCode('', $where) && $this->model->mode === CsvFileTools::INSERT_MODE
            || false === $contact->loadFromCode('', $where) && $this->model->mode === CsvFileTools::UPDATE_MODE) {
            return false;
        }

        if (false === $this->setModelValues($contact, $item, 'contactos.')) {
            return false;
        }

        if (false === $contact->save()) {
            return false;
        }

        if (isset($item['clientes.idcontactofact']) && !empty($item['clientes.idcontactofact']) && !empty($contact->primaryColumnValue())) {
            $customer = $contact->getCustomer(false);
            if (!empty($customer->primaryColumnValue())) {
                $customer->idcontactofact = $contact->primaryColumnValue();
                $customer->save();
            }
        }

        if (isset($item['clientes.idcontactoenv']) && !empty($item['clientes.idcontactoenv']) && !empty($contact->primaryColumnValue())) {
            $customer = $contact->getCustomer(false);
            if (!empty($customer->primaryColumnValue())) {
                $customer->idcontactoenv = $contact->primaryColumnValue();
                $customer->save();
            }
        }

        if (isset($item['proveedores.idcontacto']) && !empty($item['proveedores.idcontacto']) && !empty($contact->primaryColumnValue())) {
            $supplier = $contact->getSupplier(false);
            if (!empty($supplier->primaryColumnValue())) {
                $supplier->idcontacto = $contact->primaryColumnValue();
                $supplier->save();
            }
        }

        return true;
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
                case 'admitemarketing':
                    $model->admitemarketing = false;
                    $am = strtolower($values[$prefix . 'admitemarketing']);
                    $array = ['s', 'si', 'yes', 'y', 'true', '1'];
                    if (in_array($am, $array)) {
                        $model->admitemarketing = true;
                    }
                    break;

                case 'codagente':
                    foreach (Agentes::all() as $agent) {
                        if (strtolower($agent->codagente) === strtolower($values[$prefix . $key])
                            || strtolower($agent->nombre) === strtolower($values[$prefix . $key])) {
                            $model->{$key} = $agent->codagente;
                            break 2;
                        }
                    }
                    $model->{$key} = null;
                    break;

                case 'codcliente':
                    $client = new Cliente();
                    $model->{$key} = $client->loadFromCode($values[$prefix . $key]) ? $client->codcliente : null;
                    break;

                case 'codpais':
                    // si el nombre del país está vacío, saltamos
                    if (empty($values[$prefix . $key])) {
                        break;
                    }

                    // si el país ya existe, lo asignamos
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

                case 'codproveedor':
                    $provider = new Proveedor();
                    $model->{$key} = $provider->loadFromCode($values[$prefix . $key]) ? $provider->codproveedor : null;
                    break;

                case 'idfuente':
                    $modelClass = '\\FacturaScripts\\Dinamic\\Model\\CrmFuente';
                    if (false === class_exists($modelClass)) {
                        break;
                    }
                    $source = new $modelClass();
                    $where = [new DataBaseWhere('nombre', strtolower($values[$prefix . 'idfuente']))];
                    if (false === $source->loadFromCode('', $where)) {
                        // creamos la fuente
                        $source->nombre = $values[$prefix . 'idfuente'];
                        if (false === $source->save()) {
                            return false;
                        }
                    }
                    $model->idfuente = $source->id;
                    break;

                case 'personafisica':
                    // si personafisica esta vacío lo ponemos a false
                    if (empty($values[$prefix . 'personafisica'])) {
                        $model->personafisica = false;
                    }
                    break;
            }
        }

        // si no hay codpais asignado, asignar el país por defecto
        if (empty($model->codpais)) {
            $model->codpais = Tools::settings('default', 'codpais');
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