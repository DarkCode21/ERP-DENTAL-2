<?php

/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Extension\Model;

use Closure;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\EstadoDocumento;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroFactura\QR;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroFactura\JsonAltaSubsanacion;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroFactura\JsonAnulacion;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRegistroFactura;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class FacturaCliente
{
    public function clear(): Closure
    {
        return function () {
            $this->vf_intents_alta = 0;
            $this->vf_intents_anulacion = 0;
            $this->vf_intents_subsanacion = 0;
            $this->vf_manual_alta = false;
            $this->vf_manual_anulacion = false;
            $this->vf_sent = false;
        };
    }

    public function deleteBefore(): Closure
    {
        return function () {
            // si la factura está dada de alta o anulada en verifactu, no se puede eliminar
            if ($this->verifactuCheckAlta() || $this->verifactuCheckAnulacion()) {
                Tools::log()->warning('verifactu-invoice-has-events', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }
        };
    }

    public function onUpdate(): Closure
    {
        return function () {
            $dataBase = new DataBase();

            $row = $dataBase->select(
                'SELECT idestado FROM facturascli WHERE idfactura = ' . $this->primaryColumnValue()
            );

            if (empty($row)) {
                return;
            }

            $oldEstado = $row[0]['idestado'] ?? null;

            // Si no cambió idestado → salimos (equivalente a isDirty('idestado'))
            if ($oldEstado == $this->idestado) {
                return;
            }

            // obtenemos el estado actual de la factura
            $status = new EstadoDocumento();
            if (!$status->loadFromCode($this->idestado)) {
                return;
            }

            // si no tiene marcado el estado de mandar a verifactu, no hacemos nada
            if (!$status->vf_send) {
                return;
            }

            // enviamos la factura a verifactu
            $this->verifactuAlta();
        };
    }

    public function saveUpdateBefore(): Closure
    {
        return function () {
            $dataBase = new DataBase();

            $row = $dataBase->select(
                'SELECT * FROM facturascli WHERE idfactura = ' . $this->primaryColumnValue()
            );

            if (empty($row)) {
                return;
            }

            $row = $row[0];

            $dirty = [];
            foreach ($row as $field => $oldValue) {
                if (property_exists($this, $field)) {
                    $newValue = $this->$field;
                    if ($newValue != $oldValue) {
                        $dirty[$field] = $newValue;
                    }
                }
            }

            // si no hay cambios, no hacemos nada
            if (empty($dirty)) {
                return;
            }

            // si la factura no está dada de alta y baja en verifactu, permitimos guardar
            if (!$this->verifactuCheckAlta() && !$this->verifactuCheckAnulacion()) {
                return;
            }

            // si la factura no está dada de alta en verifactu, permitimos guardar
            if (!$this->verifactuCheckAlta()) {
                return;
            }

            // si la factura está dada de baja en verifactu, no permitimos guardar
            if ($this->verifactuCheckAnulacion()) {
                Tools::log()->warning('verifactu-invoice-has-events', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            // campos que puede modificar cualquier usuario
            $fieldsUserPermitted = [
                'cifnif',
                'nombrecliente',
                'direccion',
                'apartado',
                'codpostal',
                'ciudad',
                'provincia',
                'codpais',
                'idestado',
                'idcontactofact',
                'idcontactoenv',
                'fechaenvio',
                'codagente',
                'codtrans',
                'codigoenv',
                'codcliente'
            ];

            // campos que solo puede modificar el sistema
            $fieldsSystemPermitted = ['editable', 'netosindto'];

            // unimos ambos arrays
            $fieldsPermitted = array_merge($fieldsUserPermitted, $fieldsSystemPermitted);

            // comprobamos que los campos modificados son los permitidos
            foreach ($dirty as $field => $value) {
                if (!in_array($field, $fieldsPermitted)) {
                    Tools::log()->warning('verifactu-invoice-edit-not-permitted', [
                        'model-code' => $this->primaryColumnValue(),
                        'model-class' => $this->modelClassName(),
                        '%field%' => $field,
                        '%permitted%' => implode(', ', $fieldsUserPermitted),
                    ]);
                    return false;
                }
            }
        };
    }

    public function verifactuGetRegistroFactura(): Closure
    {
        return function (string $mode = ''): array {
            $where = [new DataBaseWhere('idfactura', $this->idfactura)];

            if (!empty($mode)) {
                $where[] = new DataBaseWhere('mode', $mode);
            }

            return VerifactuRegistroFactura::all($where, ['id' => 'ASC']);
        };
    }

    public function verifactuCheckAlta(): Closure
    {
        return function (): bool {
            if ($this->vf_manual_alta) {
                return true;
            }

            foreach ($this->verifactuGetRegistroFactura() as $log) {
                if ($log->event === VerifactuRegistroFactura::EVENT_ALTA) {
                    return true;
                }
            }

            return false;
        };
    }

    public function verifactuCheckAnulacion(): Closure
    {
        return function (): bool {
            if ($this->vf_manual_anulacion) {
                return true;
            }

            foreach ($this->verifactuGetRegistroFactura() as $log) {
                if ($log->event === VerifactuRegistroFactura::EVENT_ANULACION) {
                    return true;
                }
            }

            return false;
        };
    }

    public function verifactuAlta(): Closure
    {
        return function (): bool {
            return JsonAltaSubsanacion::generate($this, VerifactuRegistroFactura::EVENT_ALTA);
        };
    }

    public function verifactuAnulacion(): Closure
    {
        return function (): bool {
            return JsonAnulacion::generate($this);
        };
    }

    public function verifactuSubsanacion(): Closure
    {
        return function (): bool {
            return JsonAltaSubsanacion::generate($this, VerifactuRegistroFactura::EVENT_SUBSANACION);
        };
    }

    public function verifactuGetQr(): Closure
    {
        return function (): string {
            return QR::generate($this);
        };
    }
	
}
