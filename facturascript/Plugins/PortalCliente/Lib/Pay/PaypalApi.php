<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Lib\Pay;

use FacturaScripts\Core\Tools;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PaypalApi
{
    /** @var string */
    private $publicKey;

    /** @var string */
    private $privateKey;

    /** @var bool */
    private $sandbox;

    /** @var string */
    private $token;

    public function __construct(string $publicKey, string $privateKey, bool $sandbox)
    {
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
        $this->sandbox = $sandbox;
    }

    public function createOrder(float $total, string $currency, string $description,  $returnUrl, $cancellUrl)
    {
        $this->login();

        $url = $this->sandbox ?
            'https://api-m.sandbox.paypal.com/v2/checkout/orders' :
            'https://api-m.paypal.com/v2/checkout/orders';

        $resource = curl_init();
        curl_setopt($resource, CURLOPT_URL, $url);
        curl_setopt($resource, CURLOPT_POST, true);
        curl_setopt($resource, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ]);
        curl_setopt($resource, CURLOPT_POSTFIELDS, json_encode([
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => $total
                    ],
                    'description' => $description
                ]
            ],
            'application_context' => [
                'cancel_url' => $cancellUrl,
                'return_url' => $returnUrl
            ]
        ]));
        curl_setopt($resource, CURLOPT_TIMEOUT, 10);
        curl_setopt($resource, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($resource);
        $error = curl_error($resource);
        if (!empty($error)) {
            Tools::log()->error('PAYPAL error: ' . $error);
        }

        $httCode = curl_getinfo($resource, CURLINFO_HTTP_CODE);
        if (201 != $httCode) {
            Tools::log()->error('CURL HTTP code: ' . $httCode);
        }

        curl_close($resource);
        return json_decode($result, true);
    }

    public function getOrder(string $id): array
    {
        $this->login();

        $url = $this->sandbox ?
            'https://api-m.sandbox.paypal.com/v2/checkout/orders/' . $id . '/capture' :
            'https://api-m.paypal.com/v2/checkout/orders/' . $id . '/capture';

        $resource = curl_init();
        curl_setopt($resource, CURLOPT_URL, $url);
        curl_setopt($resource, CURLOPT_POST, true);
        curl_setopt($resource, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ]);
        curl_setopt($resource, CURLOPT_TIMEOUT, 10);
        curl_setopt($resource, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($resource);
        $error = curl_error($resource);
        if (!empty($error)) {
            Tools::log()->error('PAYPAL error: ' . $error);
        }

        $httCode = curl_getinfo($resource, CURLINFO_HTTP_CODE);
        if (201 != $httCode) {
            Tools::log()->error('CURL HTTP code: ' . $httCode);
        }

        curl_close($resource);
        return json_decode($result, true);
    }

    public static function urlDashboard(string $oderID): string
    {
        return 'https://www.paypal.com/unifiedtransactions/details/payment/' . $oderID;
    }

    private function login(): void
    {
        if ($this->token) {
            return;
        }

        $url = $this->sandbox ?
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' :
            'https://api-m.paypal.com/v1/oauth2/token';

        $resource = curl_init();
        curl_setopt($resource, CURLOPT_URL, $url);
        curl_setopt($resource, CURLOPT_POST, true);
        curl_setopt($resource, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'client_credentials']));
        curl_setopt($resource, CURLOPT_TIMEOUT, 10);
        curl_setopt($resource, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($resource, CURLOPT_USERPWD,  $this->publicKey . ':' . $this->privateKey);

        $result = curl_exec($resource);
        $error = curl_error($resource);
        if (!empty($error)) {
            Tools::log()->error('PAYPAL error: ' . $error);
        }

        $httCode = curl_getinfo($resource, CURLINFO_HTTP_CODE);
        if (200 != $httCode) {
            Tools::log()->error('CURL HTTP code: ' . $httCode);
        }

        curl_close($resource);
        $data = json_decode($result, true);
        if (isset($data['access_token'])) {
            $this->token = $data['access_token'];
            return;
        }

        Tools::log()->error('paypal-api-auth-error');
    }
}
