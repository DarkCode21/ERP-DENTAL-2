<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Empresa;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Clase para manejar el certificado de VeriFactu guardado en la empresa.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class Certificate
{
    public static function createCertificatePem(string $filePath, string $fileName, Empresa $company): bool
    {
        // cargamos la ruta de destino
        $destiny = self::getCertificateRoute($company);

        // creamos la ruta del archivo pem
        $filePathPem = $destiny . '/' . str_replace('.p12', '.pem', $fileName);

        // extraemos la información del certificado p12
        if (false === openssl_pkcs12_read(file_get_contents($filePath), $certs, $company->vf_password)) {
            Tools::log()->warning('error-read-cert', [
                '%file%' => $filePath,
            ]);
            return false;
        }

        // creamos el archivo pem con el certificado y la clave privada en una sola operación
        $pemContent = $certs['cert'] . $certs['pkey'];
        if (false === file_put_contents($filePathPem, $pemContent)) {
            Tools::log()->warning('error-write-cert', [
                '%file%' => $filePathPem,
            ]);
            return false;
        }

        return true;
    }

    public static function getCertificateP12(Empresa $company): string
    {
        if (empty($company->vf_certificate)) {
            return '';
        }

        // cargamos la ruta de destino
        $destiny = self::getCertificateRoute($company);

        // creamos la ruta del archivo
        $filePath = $destiny . '/' . $company->vf_certificate;

        // devolvemos la ruta completa del archivo
        return file_exists($filePath)
            ? $filePath
            : '';
    }

    public static function getCertificatePem(Empresa $company): string
    {
        if (empty($company->vf_certificate)) {
            return '';
        }

        // cargamos la ruta de destino
        $destiny = self::getCertificateRoute($company);

        // creamos la ruta del archivo
        $filePath = $destiny . '/' . str_replace('.p12', '.pem', $company->vf_certificate);

        // devolvemos la ruta completa del archivo
        return file_exists($filePath)
            ? $filePath
            : '';
    }

    /**
     * Comprueba si un certificado es de tipo sello electrónico.
     *
     * @param Empresa $company La empresa propietaria del certificado
     * @return bool True si el certificado es de sello, false en caso contrario
     */
    public static function isSealCertificate(Empresa $company): bool
    {
        // Obtenemos la ruta del certificado P12
        $filePath = self::getCertificateP12($company);
        if (empty($filePath)) {
            return false;
        }

        // Extraer la información del certificado
        $certData = [];
        if (false === openssl_pkcs12_read(file_get_contents($filePath), $certData, $company->vf_password)) {
            // Si no podemos leer el P12, intentamos con el PEM
            $filePath = self::getCertificatePem($company);
            if (empty($filePath)) {
                return false;
            }

            // Leemos el contenido del archivo PEM
            $pemContent = file_get_contents($filePath);
            if (false === $pemContent) {
                return false;
            }

            // Extraemos la información del certificado PEM
            $certData['cert'] = $pemContent;
        }

        // Verificar el formato del certificado
        if (empty($certData['cert'])) {
            return false;
        }

        // Obtener los detalles del certificado
        $certInfo = openssl_x509_parse($certData['cert']);
        if (false === $certInfo) {
            return false;
        }

        // OIDs específicos para certificados de sello electrónico
        $sealOIDs = [
            '0.4.0.1862.1.4',   // QCP-l: Policy for EU qualified certificate for electronic seals
            '0.4.0.1862.1.5',   // QCP-l-qscd: Policy for EU qualified certificate for electronic seals on QSCD
            '1.3.6.1.4.1.5734.3.5', // FNMT Certificado de Sello Electrónico
        ];

        // Verificar si el certificado tiene algún OID de sello electrónico
        if (isset($certInfo['extensions']['certificatePolicies'])) {
            $policies = $certInfo['extensions']['certificatePolicies'];
            foreach ($sealOIDs as $oid) {
                if (strpos($policies, $oid) !== false) {
                    return true;
                }
            }
        }

        // Verificar en el subject o subject alternative name si contiene "SELLO ELECTRONICO" o similar
        $subjectFields = ['CN', 'OU', 'O', 'description'];
        foreach ($subjectFields as $field) {
            if (isset($certInfo['subject'][$field])) {
                $value = strtoupper($certInfo['subject'][$field]);
                if (strpos($value, 'SELLO ELECTRONICO') !== false ||
                    strpos($value, 'SELLO ELECTRÓNICO') !== false ||
                    strpos($value, 'ELECTRONIC SEAL') !== false) {
                    return true;
                }
            }
        }

        // Comprobar en extensiones específicas
        if (isset($certInfo['extensions']['subjectAltName'])) {
            $altName = strtoupper($certInfo['extensions']['subjectAltName']);
            if (strpos($altName, 'SELLO ELECTRONICO') !== false ||
                strpos($altName, 'SELLO ELECTRÓNICO') !== false ||
                strpos($altName, 'ELECTRONIC SEAL') !== false) {
                return true;
            }
        }

        return false;
    }

    public static function setCertificateModal(Empresa $company, UploadedFile $uploadFile): bool
    {
        // copiamos el archivo en MyFiles
        if (!$uploadFile->move(Tools::folder('MyFiles'), $uploadFile->getClientOriginalName())) {
            Tools::log()->warning('error-moving-file', [
                '%file%' => $uploadFile->getClientOriginalName(),
                '%folder%' => Tools::folder('MyFiles'),
            ]);
            return false;
        }

        // guardamos el nombre del archivo en la empresa
        $company->vf_certificate = $uploadFile->getClientOriginalName();
        return self::setCertificateMyFiles($company);
    }

    public static function setCertificateMyFiles(Empresa $company): bool
    {
        // creamos la ruta de destino
        $destiny = self::getCertificateRoute($company);

        // si la carpeta no existe o no podemos crearla, terminamos
        if (false === Tools::folderCheckOrCreate($destiny)) {
            return false;
        }

        if (empty($company->vf_certificate)) {
            return true;
        }

        // comprobamos si el archivo está en MyFiles, si no está, terminamos
        $filePath = Tools::folder('MyFiles', $company->vf_certificate);
        if (false === file_exists($filePath)) {
            return false;
        }

        // formateamos el nombre del archivo manteniendo la extensión
        $newFileName = self::getFormatName($company->vf_certificate);
        $newFilePath = $destiny . '/' . $newFileName;

        // lo movemos a la carpeta de Verifactu, si no podemos renombrarlo, devolvemos vacío
        if (false === rename($filePath, $newFilePath)) {
            Tools::log()->warning('verifactu-company-has-events', [
                '%file%' => $company->vf_certificate,
                '%folder%' => $destiny,
            ]);
            return false;
        }

        if (false === self::validateCertificate($newFilePath, $company->vf_password)) {
            unlink($newFilePath);
            $company->vf_certificate = null;
            $company->vf_password = null;
            $company->save();
            return false;
        }

        // creamos el archivo .pem con el certificado y la clave privada
        if (false === self::createCertificatePem($newFilePath, $newFileName, $company)) {
            unlink($newFilePath);
            $company->vf_certificate = null;
            $company->vf_password = null;
            $company->save();
            return false;
        }

        // actualizamos el nombre del archivo en la empresa
        $company->vf_certificate = $newFileName;
        if (false === $company->save()) {
            unlink($newFilePath);
            Tools::log()->warning('error-saving-certificate', [
                '%file%' => $newFileName,
                '%folder%' => $destiny,
            ]);
        }

        return true;
    }

    private static function getCertificateRoute(Empresa $company): string
    {
        // obtener la ruta de la carpeta Verifactu
        $folder = JsonTrait::getFolderVerifactu();

        // eliminar la barra final si la tiene
        $folder = rtrim($folder, '/');

        return Tools::folder($folder, $company->primaryColumnValue());
    }

    private static function getFormatName(string $fileName): string
    {
        return preg_replace('/[^a-zA-Z0-9\.\_\-]/', '', $fileName);
    }

    /**
     * Comprueba si el emisor del certificado está en la lista de proveedores cualificados.
     *
     * @param array $certInfo Información del certificado
     * @return bool True si el emisor es un proveedor cualificado
     */
    private static function isQualifiedProvider(array $certInfo): bool
    {
        // Lista de OIDs de políticas de certificados cualificados según el reglamento eIDAS
        $qualifiedOIDs = [
            // OIDs para certificados cualificados según eIDAS
            '0.4.0.1862.1.1', // QCP: Qualified Certificate Policy
            '0.4.0.1862.1.2', // QCP+SSCD: Policy for EU qualified certificate issued on SSCD
            '0.4.0.1862.1.3', // QCP-w: Policy for EU qualified website authentication certificates
            '0.4.0.1862.1.4', // QCP-l: Policy for EU qualified certificate for electronic seals
            '0.4.0.1862.1.5', // QCP-l-qscd: Policy for EU qualified certificate for electronic seals on QSCD
            '0.4.0.1862.1.6', // QCP-n: Policy for EU qualified certificate for natural persons
            '0.4.0.1862.1.7', // QCP-n-qscd: Policy for EU qualified certificate for natural persons on QSCD

            // OIDs específicos de la FNMT-RCM (España)
            '1.3.6.1.4.1.5734.3.1', // FNMT Certificado Cualificado
            '1.3.6.1.4.1.5734.3.2', // FNMT Certificado AAPP
            '1.3.6.1.4.1.5734.3.3', // FNMT Certificado de Representante
            '1.3.6.1.4.1.5734.3.4', // FNMT Certificado de Sede Electrónica
            '1.3.6.1.4.1.5734.3.5', // FNMT Certificado de Sello Electrónico

            // OIDs específicos de la ACCV (España)
            '1.3.6.1.4.1.8149.2.1.1', // ACCV Certificado Cualificado

            // OIDs específicos de CamerFirma (España)
            '1.3.6.1.4.1.17326.10.1.1', // CamerFirma Certificado Cualificado

            // OIDs específicos de Firmaprofesional (España)
            '1.3.6.1.4.1.13177.10.1.1', // Firmaprofesional Certificado Cualificado
        ];

        // Verificar si el certificado tiene alguna política OID cualificada
        if (isset($certInfo['extensions']['certificatePolicies'])) {
            $policies = $certInfo['extensions']['certificatePolicies'];
            foreach ($qualifiedOIDs as $oid) {
                if (strpos($policies, $oid) !== false) {
                    return true;
                }
            }
        }

        // Verificar el emisor contra la lista de emisores conocidos (TSL)
        $issuer = $certInfo['issuer'];

        // Emisores conocidos de España incluidos en la TSL
        $knownIssuers = [
            'FNMT', 'Fábrica Nacional de Moneda y Timbre',
            'ACCV', 'Agencia de Tecnología y Certificación Electrónica',
            'Firmaprofesional', 'ANF', 'AC Camerfirma',
            'Agencia Notarial de Certificación', 'Izenpe',
            'Autoridad de Certificación de la Abogacía', 'Banesto CA',
            'Consejo General de la Abogacía', 'Dirección General de la Policía',
            'Servicio de Certificación del Colegio de Registradores'
        ];

        foreach ($knownIssuers as $knownIssuer) {
            // Comprobar si el emisor contiene el nombre de algún proveedor conocido
            if (isset($issuer['CN']) && strpos($issuer['CN'], $knownIssuer) !== false) {
                return true;
            }
            if (isset($issuer['O']) && strpos($issuer['O'], $knownIssuer) !== false) {
                return true;
            }
        }

        // Para una validación más exhaustiva, se podría implementar una conexión
        // a las APIs de la lista de confianza de la UE o de España para verificar
        // en tiempo real, pero eso puede requerir más recursos.
        Tools::log()->warning('error-cert-not-qualified', [
            '%issuer%' => $certInfo['issuer']['CN'] ?? 'Desconocido',
        ]);

        return false;
    }

    /**
     * Válida si el certificado cumple con los requisitos de la normativa española y europea.
     * Comprueba si el certificado ha sido emitido por un proveedor cualificado en la lista TSL.
     *
     * @param string $filePath Ruta al archivo del certificado
     * @param string $password Contraseña del certificado
     * @return bool True si el certificado es válido, false en caso contrario
     */
    private static function validateCertificate(string $filePath, string $password): bool
    {
        // Extraer la información del certificado
        $certData = [];
        if (false === openssl_pkcs12_read(file_get_contents($filePath), $certData, $password)) {
            Tools::log()->warning('error-read-cert', [
                '%file%' => $filePath,
            ]);
            return false;
        }

        // Verificar el formato del certificado
        if (empty($certData['cert'])) {
            Tools::log()->warning('error-cert-no-data', [
                '%file%' => $filePath,
            ]);
            return false;
        }

        // Obtener los detalles del certificado
        $certInfo = openssl_x509_parse($certData['cert']);
        if (false === $certInfo) {
            Tools::log()->warning('error-parse-cert', [
                '%file%' => $filePath,
            ]);
            return false;
        }

        // Validar el período de validez
        $currentTime = time();
        if ($currentTime < $certInfo['validFrom_time_t'] || $currentTime > $certInfo['validTo_time_t']) {
            Tools::log()->warning('error-cert-expired', [
                '%file%' => $filePath,
                '%valid_from%' => date('Y-m-d H:i:s', $certInfo['validFrom_time_t']),
                '%valid_to%' => date('Y-m-d H:i:s', $certInfo['validTo_time_t']),
            ]);
            return false;
        }

        // Verificar el emisor del certificado
        if (false === self::isQualifiedProvider($certInfo)) {
            return false;
        }

        return true;
    }
}
