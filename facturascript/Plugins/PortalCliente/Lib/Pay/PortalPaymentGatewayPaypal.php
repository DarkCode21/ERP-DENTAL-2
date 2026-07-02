<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Lib\Pay;

use Exception;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Plugins\PortalCliente\Contract\PortalPaymentGatewayInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalPaymentGatewayPaypal implements PortalPaymentGatewayInterface
{
    public function getHtml(SalesDocument $model, Contacto $contact): string
    {
        // obtenemos la empresa del documento
        $company = $model->getCompany();

        // si no tiene configurado Paypal, terminamos
        if (empty($company->pc_paypal_pk)
            || empty($company->pc_paypal_sk)
            || empty($company->pc_paypal_codpago)
            || empty($company->cifnif)) {
            return '';
        }

        // comprobamos que la empresa de la forma de pago sea la misma que la del documento
        $paymentMethod = new FormaPago();
        if (false === $paymentMethod->loadFromCode($company->pc_paypal_codpago)
            || $paymentMethod->idempresa !== $company->idempresa) {
            return '';
        }

        $paypalLink = 'https://www.paypal.com/sdk/js?client-id='
            . $company->pc_paypal_pk
            . '&currency=' . $model->coddivisa . '&intent=capture';

        // url documento
        $urlDocument = Tools::siteUrl() . '/' . ($contact->exists() ? $model->url('public') : $model->url('public-share'));

        $legend = Tools::lang()->trans('pay-with-paypal-desc');
        if (false === empty($company->pc_paypal_legend)) {
            $legend = Tools::lang()->trans($company->pc_paypal_legend);
        }

        return '<button id="paypal-custom-button" class="btn btn-primary btn-block mb-3 btn-spin-action"">'
            . '<i class="fa-brands fa-cc-paypal mr-2"></i>'
            . Tools::lang()->trans('pay-with-paypal')
            . '<div class="small">' . $legend . '</div>'
            . '</button>'
            . '<script src="' . $paypalLink . '"></script>'
            . '<script>'
            . 'let formData = new FormData();'
            . 'formData.append("action", "pay-paypal-link");'
            . 'formData.append("pc_uuid", "' . $model->pc_uuid . '");'
            . 'document.getElementById("paypal-custom-button").addEventListener("click", function () {
    animateSpinner("add");
    fetch("' . $urlDocument . '", {
    method: "POST",
            body: formData
        }).then(function (response) {
        if (response.ok) {
                return response.json();
            }
            animateSpinner("remove", false);
            return Promise.reject(response);
        }).then(function (data) {
            if (data.redirect) {
                window.location.href = data.redirect;
            }
            
            animateSpinner("remove", true);
        }).catch(function (error) {
            animateSpinner("remove", false);
            alert("error payPaypalLink");
            console.warn(error);
        });
});</script>';
    }

    public function name(): string
    {
        return 'paypal';
    }

    public function payAction(SalesDocument &$model, Request $request): bool
    {
        try {
            // obtenemos la empresa del documento
            $company = $model->getCompany();

            // inicializamos la API de Paypal
            $paypal = new PaypalApi($company->pc_paypal_pk, $company->pc_paypal_sk, $company->pc_paypal_sandbox);

            // comprobamos que el pago se ha realizado
            $orderID = $request->get('token', '');
            $response = $paypal->getOrder($orderID);
            if (isset($response['status']) && $response['status'] != 'COMPLETED') {
                return false;
            }

            // obtenemos la forma de pago de Paypal
            $paymentMethod = new FormaPago();
            if ($paymentMethod->loadFromCode($company->pc_paypal_codpago)
                && $paymentMethod->idempresa === $company->idempresa) {
                $model->codpago = $company->pc_paypal_codpago;
            }

            // añadimos el ID de Paypal
            $model->pc_payment_paypal = $orderID;

            // actualizamos el documento
            return $model->save();
        } catch (Exception $e) {
            Tools::log('paypal')->critical('error-pay-paypal', $e->getMessage());
            return false;
        }
    }
}
