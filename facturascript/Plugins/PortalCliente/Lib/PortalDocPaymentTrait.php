<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Lib;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\WorkQueue;
use FacturaScripts\Dinamic\Lib\Email\ButtonBlock;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Lib\Email\SpaceBlock;
use FacturaScripts\Dinamic\Lib\Email\TextBlock;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Lib\Pay\PortalPaymentGateway;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\EstadoDocumento;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\PortalCliente\Lib\Pay\PaypalApi;
use FacturaScripts\Plugins\PrePagos\Model\PrePago;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait PortalDocPaymentTrait
{
    public function getPaymentGatewayHtml(): string
    {
        // obtenemos el documento
        $model = $this->preloadModel();

        // si no es una instancia de SalesDocument, terminamos
        if (false === $model instanceof SalesDocument) {
            return '';
        }

        if (false === $this->checkPaymentButton($model)) {
            return '';
        }

        // obtenemos el html de los botones de pago
        return PortalPaymentGateway::getHtml($model, $this->contact);
    }

    protected function addPaymentPrePago(SalesDocument $model): void
    {
        $prePago = new PrePago();
        $prePago->amount = $model->total;
        $prePago->codcliente = $model->codcliente;
        $prePago->codpago = $model->codpago;
        $prePago->modelid = $model->primaryColumnValue();
        $prePago->modelname = $model->modelClassName();
        $prePago->notes = $model->observaciones;
        $prePago->save();
    }

    protected function checkPaymentButton(SalesDocument $model): bool
    {
        $modelClassName = $model->modelClassName();

        if ($model->total <= 0) {
            return false;
        }

        if (false === $model->editable) {
            return false;
        }

        if ($model->pc_paid) {
            return false;
        }

        if (false === $this->contact->pc_allow_pay) {
            return false;
        }

        if (false === empty($model->pc_payment_intent_stripe)
            || false === empty($model->pc_payment_paypal)
            || false === empty($model->pc_payment_redsys)) {
            return false;
        }

        if ($modelClassName === 'PresupuestoCliente' && empty(Tools::settings('portalcliente', 'status_payment_estimations'))) {
            return false;
        }

        if ($modelClassName === 'PedidoCliente' && empty(Tools::settings('portalcliente', 'status_payment_orders'))) {
            return false;
        }

        if ($modelClassName === 'AlbaranCliente' && empty(Tools::settings('portalcliente', 'status_payment_delivery_notes'))) {
            return false;
        }

        if ($modelClassName === 'FacturaCliente' && empty(Tools::settings('portalcliente', 'status_payment_invoices'))) {
            return false;
        }

        if ($modelClassName === 'FacturaCliente' && ($model->pagada || false === empty($model->idfacturarect))) {
            return false;
        }

        return true;
    }

    protected function payAction(): bool
    {
        // obtenemos el documento
        $model = $this->preloadModel();

        // si el documento no existe, redirigimos al portal
        if (empty($model) || false === $model->exists()) {
            $this->redirect('PortalCliente');
            return true;
        } elseif (false === $model->editable || false === $model instanceof SalesDocument) {
            // si el documento no es editable
            // o no es una instancia de SalesDocument
            // terminamos
            Tools::log()->error('non-editable-document');
            return true;
        }

        // comprobamos los datos de pago
        if (false === PortalPaymentGateway::payAction($model, $this->request)) {
            WorkQueue::send(
                'PortalDocPaymentAdmin',
                $model->primaryColumnValue(),
                [
                    'model_name' => $model->modelClassName(),
                    'payment_success' => false,
                ]
            );

            Tools::log()->error('payment-failed');
            return true;
        }

        WorkQueue::send(
            'PortalDocPaymentAdmin',
            $model->primaryColumnValue(),
            [
                'model_name' => $model->modelClassName(),
                'payment_success' => true,
            ]
        );

        return $this->payUpdateDocument($model);
    }

    protected function payPaypalLinkAction(): bool
    {
        $this->setTemplate(false);

        // obtenemos el documento
        $model = $this->preloadModel();

        // si el documento no existe, redirigimos al portal
        if (empty($model) || false === $model->exists()) {
            $this->response->setContent(json_encode(['redirect' => Tools::siteUrl() . '/PortalCliente']));
            return false;
        }

        // url documento
        $urlDocument = Tools::siteUrl() . '/' . ($this->contact->exists() ? $model->url('public') : $model->url('public-share'));

        if (false === $model->editable || false === $model instanceof SalesDocument) {
            // si el documento no es editable
            // o no es una instancia de SalesDocument
            // recargamos el documento
            $this->response->setContent(json_encode(['redirect' => $urlDocument]));
            return false;
        }

        $description = match ($model->modelClassName()) {
            'AlbaranCliente' => Tools::lang()->trans('delivery-note') . ' #' . $model->codigo,
            'FacturaCliente' => Tools::lang()->trans('invoice') . ' #' . $model->codigo,
            'PedidoCliente' => Tools::lang()->trans('order') . ' #' . $model->codigo,
            'PresupuestoCliente' => Tools::lang()->trans('estimation') . ' #' . $model->codigo,
            default => '',
        };

        if (empty($description)) {
            $this->response->setContent(json_encode(['redirect' => $urlDocument]));
            return false;
        }

        // obtenemos la empresa del documento
        $company = $model->getCompany();

        // cargamos la api de paypal
        $paypal = new PaypalApi($company->pc_paypal_pk, $company->pc_paypal_sk, $company->pc_paypal_sandbox);

        // obtenemos las urls de retorno
        $urlCancel = $urlDocument;
        $urlSuccess = $urlDocument;

        // si $urlSuccess lleva ? añadimos & en lugar de ?
        $urlSuccess .= str_contains($urlSuccess, '?') ? '&' : '?';

        // añadimos los parámetros necesarios
        $urlSuccess .= 'action=pay&platform=paypal';

        // creamos el documento en paypal
        $responseData = $paypal->createOrder($model->total, $model->coddivisa, $description, $urlSuccess, $urlCancel);

        $url = null;
        if (isset($responseData['status']) && $responseData['status'] === "CREATED") {
            foreach ($responseData['links'] as $link) {
                if ($link['rel'] === "approve") {
                    $url = $link['href'];
                    break;
                }
            }
        }

        if (empty($url)) {
            $this->response->setContent(json_encode(['redirect' => $urlDocument]));
            return false;
        }

        $this->response->setContent(json_encode(['redirect' => $url]));
        return false;
    }

    protected function payUpdateDocument(SalesDocument $model): bool
    {
        // actualizamos el documento
        $model->loadFromCode($model->primaryColumnValue());

        // marcamos el documento como pagado
        $model->pc_paid = true;
        if (false === $model->save()) {
            Tools::log()->error('document-paid-but-could-not-mark-as-paid');
            return true;
        }

        // obtenemos el estado que debe ponerse al documento
        $status = new EstadoDocumento();
        switch ($model->modelClassName()) {
            case 'AlbaranCliente':
                $status->loadFromCode(Tools::settings('portalcliente', 'status_payment_delivery_notes'));
                break;

            case 'FacturaCliente':
                $status->loadFromCode(Tools::settings('portalcliente', 'status_payment_invoices'));
                break;

            case 'PedidoCliente':
                $status->loadFromCode(Tools::settings('portalcliente', 'status_payment_orders'));
                break;

            case 'PresupuestoCliente':
                $status->loadFromCode(Tools::settings('portalcliente', 'status_payment_estimations'));
                break;
        }

        if (false === $status->exists()) {
            Tools::log()->warning('document-paid-but-new-status-not-found');
            return true;
        }

        $model->idestado = $status->idestado;
        if (false === $model->save()) {
            Tools::log()->warning('document-paid-but-status-could-not-be-updated');
            return true;
        }

        // si el nuevo estado genera una factura
        if ($status->generadoc === 'FacturaCliente') {
            // obtenemos las facturas
            $invoices = $model->childrenDocuments();

            // si hay facturas
            if (false === empty($invoices)) {
                $this->payUpdateInvoice($invoices[0]);
            }
        } elseif ($model->modelClassName() === 'FacturaCliente') {
            $this->payUpdateInvoice($model);
        }

        // si el documento no es una factura y el plugin de PrePagos está activo
        // añadimos un pago PrePago
        if (Plugins::isEnabled('PrePagos')
            && $model->modelClassName() !== 'FacturaCliente') {
            $this->addPaymentPrePago($model);
        }

        // enviamos la notificación de pago
        WorkQueue::send(
            'PortalDocPaymentCustomer',
            $model->primaryColumnValue(),
            [
                'genera_doc' => $status->generadoc,
                'model_name' => $model->modelClassName(),
            ]
        );

        Tools::log()->notice('payment-success');
        return true;
    }

    protected function payUpdateInvoice(SalesDocument $invoice): void
    {
        // marcamos los recibos como pagados
        foreach ($invoice->getReceipts() as $receipt) {
            if (false === $receipt->pagado) {
                $receipt->pagado = true;
                $receipt->fechapago = Tools::date();
            }

            if (false === $receipt->save()) {
                Tools::log()->critical('error-updating-receipt');
            }
        }

        // recargamos la factura
        $invoice->loadFromCode($invoice->primaryColumnValue());

        // ponemos la factura como emitida
        // cambiamos el estado de la factura si su estado actual es editable
        foreach ($invoice->getAvailableStatus() as $stat) {
            if (false === $stat->editable) {
                $invoice->idestado = $stat->idestado;
                if (false === $invoice->save()) {
                    Tools::log()->critical('error-updating-invoice-status');
                    return;
                }
            }
        }
    }
}