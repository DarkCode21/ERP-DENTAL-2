<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu;

use Exception;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use SimpleXMLElement;
use SoapClient;
use SoapFault;

/**
 * Clase para enviar un JSON a Verifactu y parsear la respuesta.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class ApiClient
{
    const FOLDER_TEMP = 'MyFiles/Tmp/Verifactu/';

    /**
     * Envía un JSON a la AEAT para alta, subsanación o anulación de facturas.
     *
     * @param Empresa $company
     * @param array $json Contenido JSON completo
     * @return array Estructura con errores y facturas procesadas
     */
    public static function sendInvoiceBatch(Empresa $company, array $json): array
    {
        $result = [
            'errors' => [],
            'invoices' => [],
        ];

        $currentDate = Tools::dateTime();

        // comprobamos que la empresa está configurada
        if (false === $company->verifactuIsConfigured(false)) {
            $result['errors'][] = Tools::lang()->trans('company-not-configured-verifactu');
            return $result;
        }

        if (!self::createFolderTemp($company)) {
            $result['errors'][] = Tools::lang()->trans('verifactu-error-creating-temp-folder', [
                '%folder%' => self::FOLDER_TEMP . $company->idempresa,
            ]);
            return $result;
        }

        // guardamos el JSON original en un archivo temporal para depuración
        if (Tools::config('debug', false)) {
            $jsonPath = self::FOLDER_TEMP . $company->idempresa . '/' . Tools::slug($currentDate) . '_json_send_invoice_batch.json';
            file_put_contents($jsonPath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // Creamos el cliente SOAP
        $client = self::createSoapClient($company);

        try {
            // Realizamos la llamada SOAP
            $client->__soapCall('RegFactuSistemaFacturacion', [$json]);
            self::parseResponse($result, $client->__getLastResponse());
        } catch (SoapFault $e) {
            $result['errors'][] = 'Error SoapFault: ' . self::getSoapFaultErrorMessage($e);
        } catch (Exception $e) {
            $result['errors'][] = 'Error Exception: ' . $e->getMessage();
        }

        if (Tools::config('debug', false)) {
            // Capturar información de request/response para diagnóstico
            $log = [
                'timestamp' => date('Y-m-d H:i:s'),
                'requestHeaders' => $client->__getLastRequestHeaders(),
                'request' => $client->__getLastRequest(),
                'responseHeaders' => $client->__getLastResponseHeaders(),
                'response' => $client->__getLastResponse(),
                'errors' => $result['errors'],
                'invoices' => $result['invoices'],
            ];

            // Guardar log completo
            $logPath = self::FOLDER_TEMP . $company->idempresa . '/' . Tools::slug($currentDate) . '_last_request_send_invoice_batch.json';
            file_put_contents($logPath, json_encode($log, JSON_PRETTY_PRINT));

            // si la respuesta es un xml guardamos el archivo xml
            if (str_starts_with($log['response'], '<?xml')) {
                $xmlPath = self::FOLDER_TEMP . $company->idempresa . '/' . Tools::slug($currentDate) . '_last_response_send_invoice_batch.xml';
                file_put_contents($xmlPath, $log['response']);
            }
        }

        return $result;
    }

    public static function sendRequirementBatch(Empresa $company, array $json): array
    {
        $result = [
            'errors' => [],
            'invoices' => [],
        ];

        $currentDate = Tools::dateTime();

        // comprobamos que la empresa está configurada
        if (false === $company->verifactuIsConfigured(false)) {
            $result['errors'][] = Tools::lang()->trans('company-not-configured-verifactu');
            return $result;
        }

        if (!self::createFolderTemp($company)) {
            $result['errors'][] = Tools::lang()->trans('verifactu-error-creating-temp-folder', [
                '%folder%' => self::FOLDER_TEMP . $company->idempresa,
            ]);
            return $result;
        }

        // guardamos el JSON original en un archivo temporal para depuración
        if (Tools::config('debug', false)) {
            $jsonPath = self::FOLDER_TEMP . $company->idempresa . '/' . Tools::slug($currentDate) . '_json_send_requirement_batch.json';
            file_put_contents($jsonPath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // Creamos el cliente SOAP
        $client = self::createSoapClient($company, true);

        try {
            // Realizamos la llamada SOAP
            $client->__soapCall('RegFactuSistemaFacturacion', [$json]);
            self::parseResponse($result, $client->__getLastResponse());
        } catch (SoapFault $e) {
            $result['errors'][] = 'Error SoapFault: ' . self::getSoapFaultErrorMessage($e);
        } catch (Exception $e) {
            $result['errors'][] = 'Error Exception: ' . $e->getMessage();
        }

        if (Tools::config('debug', false)) {
            // Capturar información de request/response para diagnóstico
            $log = [
                'timestamp' => date('Y-m-d H:i:s'),
                'requestHeaders' => $client->__getLastRequestHeaders(),
                'request' => $client->__getLastRequest(),
                'responseHeaders' => $client->__getLastResponseHeaders(),
                'response' => $client->__getLastResponse(),
                'errors' => $result['errors'],
                'invoices' => $result['invoices'],
            ];

            // Guardar log completo
            $logPath = self::FOLDER_TEMP . $company->idempresa . '/' . Tools::slug($currentDate) . '_last_request_send_requirement_batch.json';
            file_put_contents($logPath, json_encode($log, JSON_PRETTY_PRINT));

            // si la respuesta es un xml guardamos el archivo xml
            if (str_starts_with($log['response'], '<?xml')) {
                $xmlPath = self::FOLDER_TEMP . $company->idempresa . '/' . Tools::slug($currentDate) . '_last_response_send_requirement_batch.xml';
                file_put_contents($xmlPath, $log['response']);
            }
        }

        return $result;
    }

    /**
     * Envía un XML a la AEAT para consultar las facturas.
     *
     * @param Empresa $company
     * @param array $json
     * @return array Estructura con errores y facturas procesadas
     */
    public static function sendSearch(Empresa $company, array $json): array
    {
        $result = [
            'errors' => [],
            'invoices' => [],
        ];

        $currentDate = Tools::dateTime();

        // comprobamos que la empresa está configurada
        if (false === $company->verifactuIsConfigured()) {
            return $result;
        }

        if (!self::createFolderTemp($company)) {
            $result['errors'][] = Tools::lang()->trans('verifactu-error-creating-temp-folder', [
                '%folder%' => self::FOLDER_TEMP . $company->idempresa,
            ]);
            return $result;
        }

        // guardamos el JSON original en un archivo temporal para depuración
        if (Tools::config('debug', false)) {
            $jsonPath = self::FOLDER_TEMP . $company->idempresa . '/' . Tools::slug($currentDate) . '_json_send_search.json';
            file_put_contents($jsonPath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // Creamos el cliente SOAP
        $client = self::createSoapClient($company);

        try {
            // Realizamos la llamada SOAP
            $client->__soapCall('ConsultaFactuSistemaFacturacion', [$json]);
            self::parseResponse($result, $client->__getLastResponse());
        } catch (SoapFault $e) {
            $result['errors'][] = 'Error SoapFault: ' . self::getSoapFaultErrorMessage($e);
        } catch (Exception $e) {
            $result['errors'][] = 'Error Exception: ' . $e->getMessage();
        }

        if (Tools::config('debug', false)) {
            // Capturar información de request/response para diagnóstico
            $log = [
                'timestamp' => date('Y-m-d H:i:s'),
                'requestHeaders' => $client->__getLastRequestHeaders(),
                'request' => $client->__getLastRequest(),
                'responseHeaders' => $client->__getLastResponseHeaders(),
                'response' => $client->__getLastResponse(),
                'errors' => $result['errors'],
                'invoices' => $result['invoices'],
            ];

            // Guardar log completo
            $logPath = self::FOLDER_TEMP . $company->idempresa . '/' . Tools::slug($currentDate) . '_last_request_log.json';
            file_put_contents($logPath, json_encode($log, JSON_PRETTY_PRINT));

            // si la respuesta es un xml guardamos el archivo xml
            if (str_starts_with($log['response'], '<?xml')) {
                $xmlPath = self::FOLDER_TEMP . $company->idempresa . '/' . Tools::slug($currentDate) . '_last_response_log.xml';
                file_put_contents($xmlPath, $log['response']);
            }
        }

        return $result;
    }

    private static function calcularTiempoEsperaEnvio(array $parsed): void
    {
        if (!isset($parsed['TiempoEsperaEnvio'])) {
            return;
        }

        // obtenemos el tiempo de espera de la respuesta
        $seconds = (int)$parsed['TiempoEsperaEnvio'];

        // obtenemos la fecha anterior guardada en settings
        $lastDate = Tools::settings('verifactu', 'send_last_date', Tools::dateTime());

        // sumamos los segundos al tiempo actual
        $newDate = date(Tools::DATETIME_STYLE, strtotime($lastDate) + $seconds);

        // guardamos la nueva fecha en settings
        Tools::settingsSet('verifactu', 'send_last_date', $newDate);
        Tools::settingsSave();
    }

    private static function createFolderTemp(Empresa $company): bool
    {
        return Tools::folderCheckOrCreate(self::FOLDER_TEMP . $company->idempresa);
    }

    private static function createSoapClient(Empresa $company, bool $requirement = false): SoapClient
    {
        // comprobamos si el certificado es de sello o no
        $sealCertificate = Certificate::isSealCertificate($company);

        // Obtener el endpoint
        if ($requirement) {
            if ($sealCertificate && $company->vf_debug_mode) {
                $endpoint = 'https://prewww10.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/RequerimientoSOAP';
            } elseif ($sealCertificate && !$company->vf_debug_mode) {
                $endpoint = 'https://www10.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/RequerimientoSOAP';
            } elseif (!$sealCertificate && $company->vf_debug_mode) {
                $endpoint = 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/RequerimientoSOAP';
            } else {
                $endpoint = 'https://www1.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/RequerimientoSOAP';
            }
        } else {
            if ($sealCertificate && $company->vf_debug_mode) {
                $endpoint = 'https://prewww10.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';
            } elseif ($sealCertificate && !$company->vf_debug_mode) {
                $endpoint = 'https://www10.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';
            } elseif (!$sealCertificate && $company->vf_debug_mode) {
                $endpoint = 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';
            } else {
                $endpoint = 'https://www1.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';
            }
        }

        // obtener el wsdl
        $wsdl = $company->vf_debug_mode
            ? 'https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SistemaFacturacion.wsdl'
            : 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SistemaFacturacion.wsdl';

        $options = [
            'local_cert' => Certificate::getCertificatePem($company),
            'passphrase' => $company->vf_password,
            'trace' => true,
            'exceptions' => true,
            'cache_wsdl' => 0, // WSDL_CACHE_NONE,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                    'crypto_method' => 33, // STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                ],
            ]),
            'soap_version' => SOAP_1_1,
            'style' => SOAP_DOCUMENT,
            'use' => SOAP_LITERAL
        ];

        $client = new SoapClient($wsdl, $options);
        $client->__setLocation($endpoint);
        return $client;
    }

    /**
     * Parsea y valida una respuesta XML de la AEAT y devuelve los datos interpretados.
     * Esta función convierte cualquier respuesta XML SOAP a un array manteniendo su estructura
     * completa sin depender de nombres de nodos específicos.
     *
     * @param array $result
     * @param string|null $xml
     * @return void
     * @throws Exception
     */
    private static function parseResponse(array &$result, ?string $xml): void
    {
        if (empty($xml) || !str_starts_with(trim($xml), '<')) {
            // Verificación de que el contenido parece ser XML válido
            $result['errors'][] = Tools::lang()->trans('verifactu-invalid-xml-response');
            return;
        }

        try {
            $simpleXml = new SimpleXMLElement($xml);
            $data = self::xmlToArray($simpleXml, true); // usa true si quieres preservar namespaces
        } catch (Exception $e) {
            $result['errors'][] = Tools::lang()->trans('verifactu-xml-parse-error', [
                '%error%' => $e->getMessage(),
            ]);
            return;
        }

        self::parseFromArray($result, $data);
    }

    /**
     * Convierte un objeto SimpleXMLElement a array preservando toda la estructura y namespaces.
     * Esta función es genérica y funciona con cualquier estructura XML.
     *
     * @param SimpleXMLElement $xml
     * @param bool $preserveNamespaces Si true, preserva los elementos con namespaces
     * @return array|string
     */
    private static function xmlToArray(SimpleXMLElement $xml, bool $preserveNamespaces = false): array|string
    {
        // Para nodos de texto simple, devolver directamente el valor como string
        if (count($xml->attributes()) === 0 && count($xml->children()) === 0 &&
            count($xml->getNamespaces(false)) === 0) {
            $value = trim((string)$xml);
            return $value !== '' ? $value : [];
        }

        $result = [];

        // Procesar atributos
        foreach ($xml->attributes() as $name => $value) {
            $result['@' . $name] = trim((string)$value);
        }

        // Procesar atributos con namespace si es necesario
        if ($preserveNamespaces) {
            foreach ($xml->getNamespaces(true) as $prefix => $namespace) {
                if ($prefix !== '') {
                    foreach ($xml->attributes($namespace) as $name => $value) {
                        $result['@' . $prefix . ':' . $name] = trim((string)$value);
                    }
                }
            }
        }

        // Procesar nodos hijos (sin namespace)
        $childrenProcessed = false;
        foreach ($xml->children() as $name => $child) {
            $childrenProcessed = true;
            $childValue = self::xmlToArray($child, $preserveNamespaces);

            if (isset($result[$name])) {
                // Si ya existe este nombre de nodo, convertir a array si no lo es
                if (!isset($result[$name][0])) {
                    $temp = $result[$name];
                    $result[$name] = [$temp];
                }
                $result[$name][] = $childValue;
            } else {
                $result[$name] = $childValue;
            }
        }

        // Procesar nodos con namespace si es necesario
        if ($preserveNamespaces) {
            foreach ($xml->getNamespaces(true) as $prefix => $namespace) {
                if ($prefix !== '') {
                    foreach ($xml->children($namespace) as $name => $child) {
                        $childrenProcessed = true;
                        $childValue = self::xmlToArray($child, $preserveNamespaces);

                        // Usamos el nombre sin namespace como clave
                        if (isset($result[$name])) {
                            if (!isset($result[$name][0])) {
                                $temp = $result[$name];
                                $result[$name] = [$temp];
                            }
                            $result[$name][] = $childValue;
                        } else {
                            $result[$name] = $childValue;
                        }
                    }
                }
            }
        }

        // Si no tiene hijos, pero tiene contenido de texto, agregar como valor
        if (!$childrenProcessed && (string)$xml !== '') {
            $textValue = trim((string)$xml);
            if (!empty($textValue)) {
                // Si ya hay atributos, usar _value como clave para el texto
                if (count($result) > 0) {
                    $result['_value'] = $textValue;
                } else {
                    // Si no hay atributos, solo devolver el texto
                    return $textValue;
                }
            }
        }

        return $result;
    }

    /**
     * Interpreta respuestas tipo alta/anulación.
     */
    private static function parseAltaSubsanacionAnulacion(array &$result, array $parsed): void
    {
        if (!isset($parsed['RespuestaLinea'])) {
            $result['errors'][] = Tools::lang()->trans('verifactu-invalid-response-structure');
            return;
        }

        // Manejo de la estructura con RespuestaLinea
        $respuestas = isset($parsed['RespuestaLinea'][0]) ? $parsed['RespuestaLinea'] : [$parsed['RespuestaLinea']];
        foreach ($respuestas as $respuesta) {
            $result['invoices'][$respuesta['RefExterna']] = [
                'IDEmisorFactura' => $respuesta['IDFactura']['IDEmisorFactura'] ?? '',
                'NumSerieFactura' => $respuesta['IDFactura']['NumSerieFactura'] ?? '',
                'FechaExpedicionFactura' => $respuesta['IDFactura']['FechaExpedicionFactura'] ?? '',
                'Operacion' => $respuesta['Operacion']['TipoOperacion'] ?? '',
                'EstadoRegistro' => $respuesta['EstadoRegistro'] ?? '',
                'CodigoErrorRegistro' => $respuesta['CodigoErrorRegistro'] ?? '',
                'DescripcionErrorRegistro' => $respuesta['DescripcionErrorRegistro'] ?? '',
            ];
        }
    }

    /**
     * Interpreta respuestas tipo consulta.
     */
    private static function parseConsulta(array &$result, array $parsed): void
    {
        if ($parsed['ResultadoConsulta'] === 'SinDatos') {
            return;
        }

        $respuestas = isset($parsed['RegistroRespuestaConsultaFactuSistemaFacturacion'][0]) ? $parsed['RegistroRespuestaConsultaFactuSistemaFacturacion'] : [$parsed['RegistroRespuestaConsultaFactuSistemaFacturacion']];
        foreach ($respuestas as $respuesta) {
            $data = [
                'NumSerieFactura' => $respuesta['IDFactura']['NumSerieFactura'] ?? '',
                'FechaExpedicionFactura' => $respuesta['IDFactura']['FechaExpedicionFactura'] ?? '',
                'NombreRazon' => $respuesta['DatosRegistroFacturacion']['Destinatarios']['IDDestinatario']['NombreRazon'] ?? '',
                'NIF' => $respuesta['DatosRegistroFacturacion']['Destinatarios']['IDDestinatario']['NIF'] ?? '',
                'EstadoRegistro' => $respuesta['EstadoRegistro']['EstadoRegistro'] ?? '',
                'TimestampPresentacion' => $respuesta['DatosPresentacion']['TimestampPresentacion'] ?? '',
                'RefExterna' => isset($respuesta['DatosRegistroFacturacion']['RefExterna']) && is_string($respuesta['DatosRegistroFacturacion']['RefExterna'])
                    ? $respuesta['DatosRegistroFacturacion']['RefExterna'] : '',
                'link_invoice' => '',
                'json_data' => json_encode($respuesta),
            ];

            // si hay RefExterna, buscamos la factura
            if (!empty($data['RefExterna'])) {
                $ref = explode('|', $data['RefExterna']);
                $invoice = new FacturaCliente();
                if ($invoice->loadFromCode($ref[0])) {
                    $data['link_invoice'] = $invoice->url();
                }
            }

            $result['invoices'][] = $data;
        }
    }

    /**
     * Detecta el tipo de respuesta y la interpreta.
     */
    private static function parseFromArray(array &$result, array $parsed): void
    {
        // Detectar respuesta de registro de facturas (alta/anulación)
        if (isset($parsed['Body']['RespuestaRegFactuSistemaFacturacion'])) {
            self::parseAltaSubsanacionAnulacion($result, $parsed['Body']['RespuestaRegFactuSistemaFacturacion']);
        } elseif (isset($parsed['Body']['RespuestaConsultaFactuSistemaFacturacion'])) {
            // Detectar respuesta de consulta de facturas
            self::parseConsulta($result, $parsed['Body']['RespuestaConsultaFactuSistemaFacturacion']);
        }

        // calcular el tiempo de espera
        self::calcularTiempoEsperaEnvio($parsed);
    }

    /**
     * Extrae información detallada de un error SOAP
     *
     * @param SoapFault $fault La excepción SoapFault capturada
     * @return string Mensaje de error detallado
     */
    private static function getSoapFaultErrorMessage(SoapFault $fault): string
    {
        $errorMessage = $fault->getMessage();

        // Extraer información adicional del error si está disponible
        if (isset($fault->detail)) {
            $detail = $fault->detail;
            if (is_object($detail)) {
                foreach (get_object_vars($detail) as $key => $value) {
                    if (is_object($value) && isset($value->Descripcion)) {
                        $errorMessage .= " - " . $value->Descripcion;
                    }
                }
            }
        }

        // Extraer información del faultcode si está disponible
        if (isset($fault->faultcode)) {
            $errorMessage = "[" . $fault->faultcode . "] " . $errorMessage;
        }

        // Si hay información en faultactor, agregarla
        if (!empty($fault->faultactor)) {
            $errorMessage .= " (Actor: " . $fault->faultactor . ")";
        }

        // Analizar si hay información XML en la respuesta
        if (str_contains($errorMessage, '<?xml') || $fault->getMessage() != $errorMessage) {
            try {
                // Intentar encontrar el XML en el mensaje
                if (str_contains($errorMessage, '<?xml')) {
                    $xmlString = substr($errorMessage, strpos($errorMessage, '<?xml'));
                } else {
                    // Si no encontramos el XML pero sabemos que el mensaje ha sido modificado,
                    // intentamos usar directamente el mensaje original del fault
                    $xmlString = $fault->getMessage();
                }

                // Verificar que el string comience con '<' antes de intentar parsearlo
                if (!empty($xmlString) && strpos(trim($xmlString), '<') === 0) {
                    // Intentar parsear el XML
                    $xml = new SimpleXMLElement($xmlString);

                    // Extraer información del formato específico de la AEAT
                    // Ejemplo: <faultstring>Codigo[4104].El NIF del titular en la cabecera no está identificado.</faultstring>
                    $errorInfo = [];

                    // Namespace por defecto o específicos según el formato del XML
                    $namespaces = ['' => 'default'];
                    if ($xml->getNamespaces(true)) {
                        $namespaces = $xml->getNamespaces(true);
                    }

                    foreach ($namespaces as $prefix => $namespace) {
                        $nsPrefix = empty($prefix) ? '' : $prefix . ':';
                        $xpath = ($nsPrefix ? "//{$nsPrefix}Body/{$nsPrefix}Fault/" : "//Body/Fault/");

                        // Buscar elementos comunes en faults SOAP
                        $elements = [
                            'faultcode' => 'Código',
                            'faultstring' => 'Error',
                            'detail' => 'Detalle'
                        ];

                        foreach ($elements as $element => $label) {
                            $nodes = $xml->xpath($xpath . $element);
                            if ($nodes && !empty((string)$nodes[0])) {
                                $content = (string)$nodes[0];

                                // Procesamiento especial para faultstring que puede contener código y mensaje
                                if ($element === 'faultstring' && preg_match('/Codigo\[(\d+)\]\.(.+)/i', $content, $matches)) {
                                    $errorInfo['Código'] = $matches[1];
                                    $errorInfo['Mensaje'] = trim($matches[2]);
                                } else {
                                    $errorInfo[$label] = $content;
                                }
                            }
                        }
                    }

                    // Construir un mensaje formateado
                    if (!empty($errorInfo)) {
                        $parsedMessage = [];
                        foreach ($errorInfo as $key => $value) {
                            $parsedMessage[] = "{$key}: {$value}";
                        }
                        $errorMessage = implode(" | ", $parsedMessage);
                    }
                } else {
                    // El string no parece ser un XML válido, intentemos extraer información del mensaje
                    if (preg_match('/Codigo\[(\d+)\]\.(.+)/i', $errorMessage, $matches)) {
                        $errorMessage = "Código: {$matches[1]} | Mensaje: " . trim($matches[2]);
                    }
                }
            } catch (Exception $e) {
                // Intenta extraer información del código de error directamente del mensaje
                if (preg_match('/Codigo\[(\d+)\]\.(.+)/i', $errorMessage, $matches)) {
                    $errorMessage = "Código: {$matches[1]} | Mensaje: " . trim($matches[2]);
                }
            }
        }

        return $errorMessage;
    }
}
