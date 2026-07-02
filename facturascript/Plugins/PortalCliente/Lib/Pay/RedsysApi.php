<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Lib\Pay;

use FacturaScripts\Core\DataSrc\Divisas;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\Empresa;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class RedsysApi
{
    const VERSION = 'HMAC_SHA256_V1';

    /** @var string */
    private $publicKey;

    /** @var string */
    private $privateKey;

    /** @var bool */
    private $sandbox;

    /** @var string */
    private $terminal;

    /** @var array */
    private $params = [];

    public function __construct(string $publicKey, string $privateKey, string $terminal, bool $sandbox)
    {
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
        $this->sandbox = $sandbox;
        $this->terminal = $terminal;
    }

    public function createMerchantParameters(): string
    {
        // Se transforma el array de datos en un objeto Json
        $json = $this->arrayToJson();

        // Se codifican los datos Base64
        return $this->encodeBase64($json);
    }

    public function createMerchantSignature(): string
    {
        // Se decodifica la clave Base64
        $key = $this->decodeBase64($this->privateKey);

        // Se genera el parámetro Ds_MerchantParameters
        $ent = $this->createMerchantParameters();

        // Se diversifica la clave con el Número de Pedido
        $key = $this->encrypt_3DES($this->getOrder(), $key);

        // MAC256 del parámetro Ds_MerchantParameters
        $res = $this->mac256($ent, $key);

        // Se codifican los datos Base64
        return $this->encodeBase64($res);
    }

    public function createMerchantSignatureNotif($data)
    {
        // Se decodifica la clave Base64
        $key = $this->decodeBase64($this->privateKey);

        // Se decodifican los datos Base64
        $decodec = $this->base64_url_decode($data);

        // Los datos decodificados se pasan al array de datos
        $this->stringToArray($decodec);

        // Se diversifica la clave con el Número de Pedido
        $key = $this->encrypt_3DES($this->getOrderNotif(), $key);

        // MAC256 del parámetro Ds_Parameters que envía Redsys
        $res = $this->mac256($data, $key);
        // Se codifican los datos Base64

        return $this->base64_url_encode($res);
    }

    public function decodeMerchantParameters($data): bool|string
    {
        // Se decodifican los datos Base64
        $decodec = $this->base64_url_decode($data);

        // Los datos decodificados se pasan al array de datos
        $this->stringToArray($decodec);

        return $decodec;
    }

    public function getParam(string $key): string
    {
        return $this->params[$key] ?? '';
    }

    public function getParamsEncoded(array $params): string
    {
        return base64_encode(json_encode($params));
    }

    public function getSignature(array $params): string
    {
        $key = base64_decode($this->privateKey);
        $paramsEncoded = $this->getParamsEncoded($params);

        $message = $params['DS_MERCHANT_ORDER'];
        $l = ceil(strlen($message) / 8) * 8;
        $encrypt = substr(openssl_encrypt($message . str_repeat("\0", $l - strlen($message)), 'des-ede3-cbc', $key, OPENSSL_RAW_DATA, "\0\0\0\0\0\0\0\0"), 0, $l);

        $res = hash_hmac('sha256', $paramsEncoded, $encrypt, true);
        return base64_encode($res);
    }

    public function getSignatureNotify(string $params): string
    {
        $key = base64_decode($this->privateKey);
        $decodec = base64_decode(strtr($params, '-_', '+/'));
        $vars = json_decode($decodec, true);

        $message = $vars['Ds_Order'];
        $l = ceil(strlen($message) / 8) * 8;
        $encrypt = substr(openssl_encrypt($message . str_repeat("\0", $l - strlen($message)), 'des-ede3-cbc', $key, OPENSSL_RAW_DATA, "\0\0\0\0\0\0\0\0"), 0, $l);

        $res = hash_hmac('sha256', $params, $encrypt, true);
        return strtr(base64_encode($res), '+/', '-_');
    }

    public function getUrl(): string
    {
        return $this->sandbox
            ? 'https://sis-t.redsys.es:25443/sis/realizarPago'
            : 'https://sis.redsys.es/sis/realizarPago';
    }

    public function setParams(string $key, string $value): void
    {
        $this->params[$key] = $value;
    }

    public static function urlDashboard(string $code): string
    {
        return '#';
    }

    protected function arrayToJson(): bool|string
    {
        return json_encode($this->params);
    }

    protected function base64_url_decode($input): bool|string
    {
        return base64_decode(strtr($input, '-_', '+/'));
    }

    protected function base64_url_encode($input): string
    {
        return strtr(base64_encode($input), '+/', '-_');
    }

    protected function decodeBase64($data): bool|string
    {
        return base64_decode($data);
    }

    protected function encodeBase64($data): string
    {
        return base64_encode($data);
    }

    protected function encrypt_3DES($message, $key): string
    {
        // Se cifra
        $l = ceil(strlen($message) / 8) * 8;
        return substr(openssl_encrypt($message . str_repeat("\0", $l - strlen($message)), 'des-ede3-cbc', $key, OPENSSL_RAW_DATA, "\0\0\0\0\0\0\0\0"), 0, $l);
    }

    protected function getOrder(): string
    {
        if(false === empty($this->params['Ds_Merchant_Order'])){
            return $this->params['Ds_Merchant_Order'];
        } elseif (false === empty($this->params['DS_MERCHANT_ORDER'])) {
            return $this->params['DS_MERCHANT_ORDER'];
        }

        return '';
    }

    protected function getOrderNotif(): string
    {
        if(false === empty($this->params['DS_ORDER'])) {
            return $this->params['DS_ORDER'];
        } elseif (false === empty($this->params['Ds_Order'])) {
            return $this->params['Ds_Order'];
        }

        return '';
    }

    protected function mac256($ent,$key): string
    {
        return hash_hmac('sha256', $ent, $key, true);
    }

    protected function stringToArray($datosDecod): void
    {
        $this->params = json_decode($datosDecod, true);
    }
}