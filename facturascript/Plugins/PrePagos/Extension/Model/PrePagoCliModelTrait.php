<?php
/**
 * Copyright (C) 2022-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PrePagos\Extension\Model;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Accounting\PrePagoCliToAccounting;
use FacturaScripts\Dinamic\Model\PrePagoCli;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait PrePagoCliModelTrait
{
    public function delete(): Closure
    {
        return function () {
            $deleted = 0;

            // eliminamos los prepagos
            foreach ($this->getPayments() as $payment) {
                if ($payment->delete()) {
                    $deleted++;
                }
            }

            // si hubo prepagos eliminados, obtenemos el documento padre y marcamos sus prepagos como no copiados
            if ($deleted > 0) {
                // recorremos los documentos padres
                foreach ($this->parentDocuments() as $parent) {
                    // buscamos los pagos relacionados
                    foreach ($parent->getPayments() as $payment) {
                        $payment->copied = false;
                        $payment->save();
                    }
                }
            }
        };
    }

    public function getPayments(): Closure
    {
        return function () {
            $where = [
                new DataBaseWhere('modelid', $this->primaryColumnValue()),
                new DataBaseWhere('modelname', $this->modelClassName())
            ];
            return PrePagoCli::all($where, [], 0, 0);
        };
    }

    public function onChange(): Closure
    {
        return function ($field) {
            // si cambió el cliente, actualizamos el cliente de los prepagos
            if ($field === 'codcliente') {
                foreach ($this->getPayments() as $payment) {
                    $payment->codcliente = $this->codcliente;
                    $payment->save();
                }
            }

            // si cambio el almacén, revisamos la empresa de la forma de pago de los prepagos sea igual a la empresa del documento
            // si algún prepago es de una empresa diferente, no permitimos el cambio
            if ($field === 'codalmacen') {
                foreach ($this->getPayments() as $prePayment) {
                    $payment = $prePayment->getPaymentMethod();
                    if ($payment->idempresa !== $this->idempresa) {
                        Tools::log()->warning('company-different-from-payment-method');
                        return false;
                    }
                }
            }
        };
    }

    public function saveUpdate(): Closure
    {
        return function () {
            // actualizamos los prepagos con la misma información del campo editable
            foreach ($this->getPayments() as $payment) {
                if ($payment->editable !== $this->editable) {
                    $payment->editable = $this->editable;
                    $payment->save();
                }
            }

            // obtenemos los estados disponibles del documento
            foreach ($this->getAvailableStatus() as $status) {
                // si el estado es diferente al del documento, continuamos
                if ($status->idestado !== $this->idestado) {
                    continue;
                }

                // si el estado no es editable y antes si era editable
                // y tampoco genera otro documento
                // eliminamos el asiento de los prepagos
                if ($this->previousData['editable'] && false === $this->editable && empty($status->generadoc)) {
                    foreach ($this->getPayments() as $payment) {
                        $entry = $payment->getAccountingEntry();
                        if ($entry->primaryColumnValue()) {
                            $entry->delete();
                        }
                    }
                    break;
                }

                // si el estado es editable y antes no lo era
                // y tampoco genera otro documento
                // generamos los asientos de los prepagos
                if (false === $this->previousData['editable'] && $this->editable && empty($status->generadoc)) {
                    PrePagoCliToAccounting::regenerate([$this]);
                    break;
                }

                break;
            }
        };
    }
}
