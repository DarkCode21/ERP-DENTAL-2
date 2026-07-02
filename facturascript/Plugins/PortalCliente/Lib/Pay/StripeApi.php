<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Lib\Pay;

use Exception;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\Empresa;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\Stripe;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class StripeApi
{
    private string $descriptor;

    public function __construct(string $stripe_sk, string $descriptor)
    {
        $this->descriptor = $descriptor;
        Stripe::setApiKey($stripe_sk);
        Stripe::setApiVersion('2023-10-16');
    }

    public function addSession(array $params): ?Session
    {
        try {
            if (false === isset($params['payment_intent_data']['metadata']['descriptor'])) {
                $params['payment_intent_data']['metadata']['descriptor'] = $this->descriptor;
            }

            return Session::create($params);
        } catch (Exception $e) {
            Tools::log()->warning($e->getMessage());
            return null;
        }
    }

    public function checkCustomer(Contacto $contact)
    {
        try {
            // buscamos el contacto en stripe
            $search = Customer::search([
                'query' => 'email:"' . $contact->email . '"',
                'limit' => 1,
            ]);

            // si existe, lo devolvemos
            if (false === empty($search->data)) {
                return $search->data[0];
            }

            // si no existe, lo creamos
            return Customer::create([
                'name' => $contact->fullName(),
                'email' => $contact->email,
            ]);
        } catch (Exception $e) {
            Tools::log()->warning($e->getMessage());
            return null;
        }
    }

    public function getPaymentIntent(string $id): ?PaymentIntent
    {
        try {
            return PaymentIntent::retrieve($id);
        } catch (Exception $e) {
            Tools::log()->warning($e->getMessage());
            return null;
        }
    }

    public function getSession(string $id): ?Session
    {
        try {
            return Session::retrieve($id);
        } catch (Exception $e) {
            Tools::log()->warning($e->getMessage());
            return null;
        }
    }

    public static function urlDashboard(Empresa $empresa, string $url = ''): string
    {
        // creamos la url del dashboard
        $urlDashboard = strpos($empresa->pc_stripe_pk, 'test') ?
            'https://dashboard.stripe.com/test/' :
            'https://dashboard.stripe.com/';

        // si la url empieza por /, la quitamos
        if (str_starts_with($url, '/')) {
            $url = substr($url, 1);
        }

        return $urlDashboard . $url;
    }
}