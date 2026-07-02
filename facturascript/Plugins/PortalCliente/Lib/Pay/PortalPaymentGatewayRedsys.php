<?php
/**
 * Copyright (C) 2024-2025 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Lib\Pay;

use Exception;
use FacturaScripts\Core\DataSrc\Divisas;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Plugins\PortalCliente\Contract\PortalPaymentGatewayInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalPaymentGatewayRedsys implements PortalPaymentGatewayInterface
{
    public function getHtml(SalesDocument $model, Contacto $contact): string
    {
        // obtenemos la empresa del documento
        $company = $model->getCompany();

        // si no tiene configurado Stripe, terminamos
        if (empty($model->pc_uuid)
            || empty($company->pc_redsys_pk)
            || empty($company->pc_redsys_sk)
            || empty($company->pc_redsys_terminal)
            || empty($company->pc_redsys_codpago)
            || empty($company->cifnif)) {
            return '';
        }

        // comprobamos que la empresa de la forma de pago sea la misma que la del documento
        $paymentMethod = new FormaPago();
        if (false === $paymentMethod->loadFromCode($company->pc_redsys_codpago)
            || $paymentMethod->idempresa !== $company->idempresa) {
            return '';
        }

        // obtenemos la descripción del documento
        $description = $this->getDescription($model);
        if (empty($description)) {
            Tools::log()->warning('redsys-payment-not-supported', ['%model%' => $model->modelClassName()]);
            return '';
        }

        // url documento
        $urlDocument = Tools::siteUrl() . '/' . ($contact->exists() ? $model->url('public') : $model->url('public-share'));

        // urls de redirección
        $urlCancel = $urlDocument;
        $urlSuccess = $urlDocument;

        // si $successUrl lleva ? añadimos & en lugar de ?
        $urlSuccess .= str_contains($urlSuccess, '?') ? '&' : '?';

        // añadimos los parámetros necesarios
        $urlSuccess .= 'platform=redsys&action=pay';

        // creamos la instancia de Redsys
        $redsys = new RedsysApi($company->pc_redsys_pk, $company->pc_redsys_sk, $company->pc_redsys_terminal, $company->pc_redsys_sandbox);
        $redsys->setParams('DS_MERCHANT_AMOUNT', $model->total * 100);
        $redsys->setParams('DS_MERCHANT_ORDER', $this->getOrderNumber($model));
        $redsys->setParams('DS_MERCHANT_MERCHANTCODE', $company->pc_redsys_pk);
        $redsys->setParams('DS_MERCHANT_CURRENCY', Divisas::get($model->coddivisa)->codiso);
        $redsys->setParams('DS_MERCHANT_TRANSACTIONTYPE', 0);
        $redsys->setParams('DS_MERCHANT_TERMINAL', $company->pc_redsys_terminal);
        $redsys->setParams('DS_MERCHANT_MERCHANTURL', $urlDocument);
        $redsys->setParams('DS_MERCHANT_URLOK', $urlSuccess);
        $redsys->setParams('DS_MERCHANT_URLKO', $urlCancel);

        $legend = Tools::lang()->trans('pay-with-redsys-desc');
        if (false === empty($company->pc_redsys_legend)) {
            $legend = Tools::lang()->trans($company->pc_redsys_legend);
        }

        $params = $redsys->createMerchantParameters();
        $signature = $redsys->createMerchantSignature();
        return '<form action="' . $redsys->getUrl() . '" method="post" onsubmit="animateSpinner(\'add\')">'
            . '<input type="hidden" name="Ds_MerchantParameters" value="' . $params . '">'
            . '<input type="hidden" name="Ds_SignatureVersion" value="' . RedsysApi::VERSION . '">'
            . '<input type="hidden" name="Ds_Signature" value="' . $signature . '">'
            . '<button type="submit" class="btn btn-block btn-primary mb-3 btn-spin-action">'
            . '<i class="fa-solid fa-credit-card mr-2"></i>'
            . Tools::lang()->trans('pay-with-redsys')
            . '<div class="small">' . $legend . '</div>'
            . '</button>'
            . '</form>';
    }

    public function name(): string
    {
        return 'redsys';
    }

    public function payAction(SalesDocument &$model, Request $request): bool
    {
        try {
            // obtenemos la empresa del documento
            $company = $model->getCompany();

            // obtenemos los datos
            $requestVersion = $request->get('Ds_SignatureVersion', '');
            $requestParams = $request->get('Ds_MerchantParameters', '');
            $requestSignature = $request->get('Ds_Signature', '');

            // creamos la instancia de Redsys
            $redsys = new RedsysApi($company->pc_redsys_pk, $company->pc_redsys_sk, $company->pc_redsys_terminal, $company->pc_redsys_sandbox);

            $decodec = $redsys->decodeMerchantParameters($requestParams);
            $signature = $redsys->createMerchantSignatureNotif($requestParams);

            // comprobamos la firma
            if ($signature !== $requestSignature) {
                Tools::log('redsys')->critical('signature-not-match');
                return false;
            }

            if ($redsys->getParam('Ds_Response') !== '0000') {
                Tools::log('redsys')->critical('response-not-0000', ['%response%' => $redsys->getParam('Ds_Response')]);
                return false;
            }

            // obtenemos la forma de pago de Redsys
            $paymentMethod = new FormaPago();
            if ($paymentMethod->loadFromCode($company->pc_redsys_codpago)
                && $paymentMethod->idempresa === $company->idempresa) {
                $model->codpago = $company->pc_redsys_codpago;
            }

            // añadimos el ID de la intención de pago
            $model->pc_payment_redsys = $redsys->getParam('Ds_AuthorisationCode', '');

            // actualizamos el documento
            return $model->save();
        } catch (Exception $e) {
            Tools::log('stripe')->critical('error-pay-stripe', $e->getMessage());
            return false;
        }
    }

    protected function getDescription(SalesDocument $model): string
    {
        return match ($model->modelClassName()) {
            'AlbaranCliente' => Tools::lang()->trans('delivery-note') . ' #' . $model->codigo,
            'FacturaCliente' => Tools::lang()->trans('invoice') . ' #' . $model->codigo,
            'PedidoCliente' => Tools::lang()->trans('order') . ' #' . $model->codigo,
            'PresupuestoCliente' => Tools::lang()->trans('estimation') . ' #' . $model->codigo,
            default => '',
        };
    }

    protected function getOrderNumber(SalesDocument $model): string
    {
        if (!empty($model->pc_payment_redsys_order)) {
            return $model->pc_payment_redsys_order;
        }

        /*
         * Requisitos de Redsys para DS_MERCHANT_ORDER:
         * Debe tener entre 4 y 12 caracteres
         * Solo puede contener números y letras (alfanumérico)
         * No puede contener guiones, espacios u otros caracteres especiales
         * Los primeros 4 dígitos deben ser numéricos
         * */
        $model->pc_payment_redsys_order = substr(date('ymd') . $model->pc_uuid, 0, 12);
        return $model->pc_payment_redsys_order;
    }
}