<?php
/**
 * Copyright (C) 2023-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PrePagos\Extension\Lib;

use Closure;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Model\FacturaCliente;
use FacturaScripts\Core\Model\FacturaProveedor;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\PrePagoCli;
use FacturaScripts\Dinamic\Model\PrePagoProv;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Dinamic\Model\ReciboProveedor;

class BusinessDocumentGenerator
{
    public function cloneLines(): Closure
    {
        return function (BusinessDocument $prototype, BusinessDocument $newDoc, array $lines, array $quantity) {
            // si no es un documento de venta o compra, terminamos
            if (!in_array($newDoc->modelClassName(), [
                'FacturaCliente', 'AlbaranCliente', 'PedidoCliente', 'PresupuestoCliente',
                'FacturaProveedor', 'AlbaranProveedor', 'PedidoProveedor', 'PresupuestoProveedor'
            ])) {
                return;
            }

            // si el nuevo documento es una factura de venta, copiamos los recibos
            if ($newDoc->modelClassName() === 'FacturaCliente') {
                $this->copyReceiptsSales($newDoc);
                return;
            }

            // si el nuevo documento es una factura de compra, copiamos los recibos
            if ($newDoc->modelClassName() === 'FacturaProveedor') {
                $this->copyReceiptsPurchases($newDoc);
                return;
            }

            // si el nuevo documento es de venta
            if (in_array($newDoc->modelClassName(), ['AlbaranCliente', 'PedidoCliente', 'PresupuestoCliente'])) {
                $this->copyPaymentsSales($newDoc);
                return;
            }

            // si el nuevo documento es de compra
            if (in_array($newDoc->modelClassName(), ['AlbaranProveedor', 'PedidoProveedor', 'PresupuestoProveedor'])) {
                $this->copyPaymentsPurchases($newDoc);
            }
        };
    }

    protected function copyPaymentsPurchases(): Closure
    {
        return function (BusinessDocument $newDoc) {
            // recorremos los documentos padres
            foreach ($newDoc->parentDocuments() as $parent) {
                // buscamos los pagos relacionados
                foreach ($parent->getPayments() as $payment) {
                    // si el pago ya fue copiado, continuamos
                    if ($payment->copied) {
                        continue;
                    }

                    // creamos un nuevo pago
                    $copy = new PrePagoProv();
                    $copy->amount = $payment->amount;
                    $copy->codproveedor = $payment->codproveedor;
                    $copy->codpago = $payment->codpago;
                    $copy->idasiento = $payment->idasiento;
                    $copy->modelid = $newDoc->primaryColumnValue();
                    $copy->modelname = $newDoc->modelClassName();
                    $copy->nick = $payment->nick;
                    $copy->notes = empty($payment->notes) ?
                        $payment->modelname . ' ' . $payment->getDocument()->codigo :
                        $payment->notes;
                    $copy->payment_date = Tools::date($payment->payment_date ?? $payment->creationdate);

                    // guardamos el pago copiado al documento
                    if (false === $copy->save()) {
                        return;
                    }

                    // marcamos el pago como copiado
                    $payment->copied = true;
                    $payment->save();
                }
            }
        };
    }

    protected function copyPaymentsSales(): Closure
    {
        return function (BusinessDocument $newDoc) {
            // recorremos los documentos padres
            foreach ($newDoc->parentDocuments() as $parent) {
                // buscamos los pagos relacionados
                foreach ($parent->getPayments() as $payment) {
                    // si el pago ya fue copiado, continuamos
                    if ($payment->copied) {
                        continue;
                    }

                    // creamos un nuevo pago
                    $copy = new PrePagoCli();
                    $copy->amount = $payment->amount;
                    $copy->codcliente = $payment->codcliente;
                    $copy->codpago = $payment->codpago;
                    $copy->idasiento = $payment->idasiento;
                    $copy->modelid = $newDoc->primaryColumnValue();
                    $copy->modelname = $newDoc->modelClassName();
                    $copy->nick = $payment->nick;
                    $copy->notes = empty($payment->notes) ?
                        $payment->modelname . ' ' . $payment->getDocument()->codigo :
                        $payment->notes;
                    $copy->payment_date = Tools::date($payment->payment_date ?? $payment->creationdate);

                    // guardamos el pago copiado al documento
                    if (false === $copy->save()) {
                        return;
                    }

                    // marcamos el pago como copiado
                    $payment->copied = true;
                    $payment->save();
                }
            }
        };
    }

    protected function copyReceiptsPurchases(): Closure
    {
        return function (FacturaProveedor $newDoc) {
            $count = 1;

            // recorremos los documentos padres
            foreach ($newDoc->parentDocuments() as $parent) {
                // buscamos los pagos relacionados
                foreach ($parent->getPayments() as $payment) {
                    // si el pago ya fue copiado, continuamos
                    if ($payment->copied) {
                        continue;
                    }

                    // creamos el recibo
                    $recibo = new ReciboProveedor();
                    $recibo->codproveedor = $newDoc->codproveedor;
                    $recibo->codpago = $payment->codpago;
                    $recibo->fechapago = Tools::date($payment->payment_date ?? $payment->creationdate);
                    $recibo->idfactura = $newDoc->idfactura;
                    $recibo->idprepago = $payment->primaryColumnValue();
                    $recibo->importe = $payment->amount;
                    $recibo->numero = $count;
                    $recibo->pagado = true;
                    $recibo->observaciones = empty($payment->notes) ?
                        $payment->modelname . ' ' . $payment->getDocument()->codigo :
                        $payment->notes;
                    $recibo->save();

                    // aumentamos el contador
                    $count++;

                    // marcamos el pago como copiado
                    $payment->copied = true;
                    $payment->deleteAccountingEntry();
                    $payment->save();
                }
            }
        };
    }

    protected function copyReceiptsSales(): Closure
    {
        return function (FacturaCliente $newDoc) {
            $count = 1;

            // recorremos los documentos padres
            foreach ($newDoc->parentDocuments() as $parent) {
                // buscamos los pagos relacionados
                foreach ($parent->getPayments() as $payment) {
                    // si el pago ya fue copiado, continuamos
                    if ($payment->copied) {
                        continue;
                    }

                    // creamos el recibo
                    $recibo = new ReciboCliente();
                    $recibo->codcliente = $newDoc->codcliente;
                    $recibo->codpago = $payment->codpago;
                    $recibo->fechapago = Tools::date($payment->payment_date ?? $payment->creationdate);
                    $recibo->idfactura = $newDoc->idfactura;
                    $recibo->idprepago = $payment->primaryColumnValue();
                    $recibo->importe = $payment->amount;
                    $recibo->numero = $count;
                    $recibo->pagado = true;
                    $recibo->observaciones = empty($payment->notes) ?
                        $payment->modelname . ' ' . $payment->getDocument()->codigo :
                        $payment->notes;
                    $recibo->save();

                    // aumentamos el contador
                    $count++;

                    // marcamos el pago como copiado
                    $payment->copied = true;
                    $payment->deleteAccountingEntry();
                    $payment->save();
                }
            }
        };
    }
}
