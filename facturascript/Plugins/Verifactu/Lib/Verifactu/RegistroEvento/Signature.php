<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroEvento;

use DOMException;
use Exception;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\Certificate;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRegistroEvento;
use DOMDocument;
use DOMElement;

/**
 * Clase para generar la firma en el JSON de alta, subsanación y anulación en Verifactu.
 * Validaciones realizadas a fecha del 07-08-2025 en la versión 6 del registro de firmas de Verifactu.
 * https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/Espec-Tecnicas/EspecTecGenerFirmaElectRfact.pdf
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class Signature
{
    public static function generate(VerifactuRegistroEvento $regEvent): bool
    {
        // Comprueba si el archivo existe
        if (!file_exists($regEvent->file_json)) {
            return false;
        }

        // Leemos el contenido del archivo
        $fileContent = file_get_contents($regEvent->file_json);
        if (false === $fileContent) {
            return false;
        }

        // Decodificamos el JSON
        $eventJson = json_decode($fileContent, true);
        if (null === $eventJson) {
            return false;
        }

        // si el JSON ya tiene firma, no hacemos nada
        if (isset($eventJson['RegistroEvento']['Evento']['Signature'])) {
            return true;
        }

        // obtenemos la empresa
        $company = $regEvent->getCompany();

        // Obtenemos la ruta del certificado
        $certPath = Certificate::getCertificateP12($company);
        if (false === file_exists($certPath)) {
            Tools::log()->error('verifactu-certificate-not-found', [
                '%certPath%' => $certPath,
                '%company%' => $company->nombrecorto
            ]);
            return false;
        }

        // Cargamos el certificado
        $certData = file_get_contents($certPath);
        if (false === $certData) {
            Tools::log()->error('verifactu-certificate-not-loaded', [
                '%certPath%' => $certPath,
                '%company%' => $company->nombrecorto
            ]);
            return false;
        }

        // Leemos el certificado PKCS#12
        $certs = [];
        if (false === openssl_pkcs12_read($certData, $certs, $company->vf_password)) {
            Tools::log()->error('verifactu-certificate-read-error', [
                '%error%' => openssl_error_string(),
                '%company%' => $company->nombrecorto
            ]);
            return false;
        }

        try {
            // Creamos un documento XML temporal para generar la firma
            $doc = new DOMDocument('1.0', 'UTF-8');
            $doc->formatOutput = false;
            $doc->preserveWhiteSpace = false;

            // Creamos el nodo raíz para poder añadir la firma
            $rootNode = $doc->createElement('Root');
            $doc->appendChild($rootNode);

            // Generamos el XML con la firma
            if (!self::xmlSignature($doc, $rootNode, $certs)) {
                return false;
            }

            // Extraemos el nodo de firma del documento
            $signatureNodes = $rootNode->getElementsByTagName('Signature');
            if ($signatureNodes->length === 0) {
                Tools::log()->error('verifactu-signature-node-not-found');
                return false;
            }

            // Obtenemos el XML de la firma
            $signatureNode = $signatureNodes->item(0);
            $signatureXml = $doc->saveXML($signatureNode);

            // Añadir la firma al JSON original
            $eventJson['RegistroEvento']['Evento']['Signature'] = $signatureXml;

            // Guardar el JSON con la firma
            file_put_contents($regEvent->file_json, json_encode($eventJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return true;
        } catch (Exception $e) {
            Tools::log()->error('verifactu-signature-exception', ['%error%' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Genera la firma XML siguiendo las especificaciones de Verifactu
     *
     * @param DOMDocument $doc Documento DOM
     * @param DOMElement $node Nodo al que se añadirá la firma
     * @param array $certs Certificados
     * @return bool
     * @throws DOMException
     */
    private static function xmlSignature(DOMDocument $doc, DOMElement $node, array $certs): bool
    {
        // Creamos el nodo Signature
        $signatureNode = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Signature');
        $node->appendChild($signatureNode);

        // Creamos el nodo SignedInfo
        $signedInfoNode = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:SignedInfo');
        $signatureNode->appendChild($signedInfoNode);

        // Añadimos la función de canonicalización
        $canonMethodNode = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:CanonicalizationMethod');
        $canonMethodNode->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfoNode->appendChild($canonMethodNode);

        // Añadimos la función de firma
        $signMethodNode = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:SignatureMethod');
        $signMethodNode->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256');
        $signedInfoNode->appendChild($signMethodNode);

        // Añadimos la referencia
        $referenceNode = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Reference');
        $referenceNode->setAttribute('URI', '');
        $signedInfoNode->appendChild($referenceNode);

        // Añadimos las transformaciones
        $transformsNode = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Transforms');
        $referenceNode->appendChild($transformsNode);

        $transform1Node = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Transform');
        $transform1Node->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transformsNode->appendChild($transform1Node);

        $transform2Node = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Transform');
        $transform2Node->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $transformsNode->appendChild($transform2Node);

        // Añadimos la función de digest
        $digestMethodNode = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:DigestMethod');
        $digestMethodNode->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $referenceNode->appendChild($digestMethodNode);

        // Calculamos el digest value
        $canonicalXml = $doc->C14N(true, false);
        $digestValue = base64_encode(hash('sha256', $canonicalXml, true));

        // Añadimos el digest value
        $digestValueNode = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:DigestValue', $digestValue);
        $referenceNode->appendChild($digestValueNode);

        // Canonicalizamos el SignedInfo para firmarlo
        $signedInfoCanon = $signedInfoNode->C14N(true, false);

        // Firmamos el SignedInfo
        $signature = '';
        if (false === openssl_sign($signedInfoCanon, $signature, $certs['pkey'], OPENSSL_ALGO_SHA256)) {
            Tools::log()->error('verifactu-signature-error', ['error' => openssl_error_string()]);
            return false;
        }

        // Añadimos el valor de la firma
        $signatureValueNode = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:SignatureValue', base64_encode($signature));
        $signatureNode->appendChild($signatureValueNode);

        // Añadimos la información de la clave
        $keyInfoNode = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:KeyInfo');
        $signatureNode->appendChild($keyInfoNode);

        // Añadimos los datos del certificado X509
        $x509DataNode = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:X509Data');
        $keyInfoNode->appendChild($x509DataNode);

        // Preparamos el certificado para incluirlo
        $certPem = $certs['cert'];
        $certPem = str_replace('-----BEGIN CERTIFICATE-----', '', $certPem);
        $certPem = str_replace('-----END CERTIFICATE-----', '', $certPem);
        $certPem = str_replace("\n", '', trim($certPem));

        // Añadimos el certificado
        $x509CertNode = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:X509Certificate', $certPem);
        $x509DataNode->appendChild($x509CertNode);

        return true;
    }
}