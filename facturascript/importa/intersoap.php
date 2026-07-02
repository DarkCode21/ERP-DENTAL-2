<?php
/**
 * Archivo externo para manejar conexiones SOAP con AEAT Verifactu
 * Recibe datos de ApiClient por POST y maneja el WSDL/SOAP remotamente
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1); // Habilitar errores para debugging

// Handler para errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - ERROR FATAL: " . $error['message'] . " en " . $error['file'] . ":" . $error['line'] . "\n", FILE_APPEND);
        if (!headers_sent()) {
            echo json_encode([
                'errors' => ['Error fatal: ' . $error['message']],
                'invoices' => []
            ]);
        }
    }
});

// Debug: Verificar que se está ejecutando
file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - intersoap iniciado\n", FILE_APPEND);

// Verificar datos POST
if (!isset($_POST['company_data']) || !isset($_POST['operation']) || !isset($_POST['data'])) {
    $error = [
        'errors' => ['Faltan parámetros requeridos. POST keys: ' . implode(', ', array_keys($_POST))],
        'invoices' => []
    ];
    file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - Error: " . json_encode($error) . "\n", FILE_APPEND);
    echo json_encode($error);
    exit;
}

$companyData = json_decode($_POST['company_data'], true);
$operation = $_POST['operation'];
$jsonData = json_decode($_POST['data'], true);

// Validar datos de empresa
if (!isset($companyData['cifnif']) || !isset($companyData['vf_password']) || !isset($companyData['idempresa'])) {
    echo json_encode([
        'errors' => ['Datos de empresa incompletos'],
        'invoices' => []
    ]);
    exit;
}

$debugMode = (bool)($companyData['vf_debug_mode'] ?? false);

// Simular Certificate::isSealCertificate - detectar si es certificado de sello
$sealCertificate = false; // Por defecto asumimos certificado normal
// Aquí podrías agregar lógica para detectar el tipo de certificado si es necesario

// Obtener el endpoint usando la misma lógica que createSoapClient
$requirement = ($operation === 'requirement');
if ($requirement) {
    if ($sealCertificate && $debugMode) {
        $endpoint = 'https://prewww10.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/RequerimientoSOAP';
    } elseif ($sealCertificate && !$debugMode) {
        $endpoint = 'https://www10.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/RequerimientoSOAP';
    } elseif (!$sealCertificate && $debugMode) {
        $endpoint = 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/RequerimientoSOAP';
    } else {
        $endpoint = 'https://www1.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/RequerimientoSOAP';
    }
} else {
    if ($sealCertificate && $debugMode) {
        $endpoint = 'https://prewww10.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';
    } elseif ($sealCertificate && !$debugMode) {
        $endpoint = 'https://www10.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';
    } elseif (!$sealCertificate && $debugMode) {
        $endpoint = 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';
    } else {
        $endpoint = 'https://www1.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';
    }
}

// obtener el wsdl
$wsdl = $debugMode
    ? 'https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SistemaFacturacion.wsdl'
    : 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SistemaFacturacion.wsdl';

// Buscar certificado PEM (convertir p12 a pem en el nombre)
$pemFileName = preg_replace('/\.p12$/i', '.pem', $companyData['vf_certificate']);
$certificatePaths = [
    __DIR__ . '/../MyFiles/Verifactu/' . $companyData['idempresa'] . '/' . $pemFileName,
    __DIR__ . '/../../MyFiles/Verifactu/' . $companyData['idempresa'] . '/' . $pemFileName,
    __DIR__ . '/../../../MyFiles/Verifactu/' . $companyData['idempresa'] . '/' . $pemFileName,
];

$certificatePath = null;
foreach ($certificatePaths as $path) {
    if (file_exists($path)) {
        $certificatePath = $path;
        break;
    }
}

if (!$certificatePath) {
    echo json_encode([
        'errors' => ['Certificado PEM no encontrado. Rutas probadas: ' . implode(', ', $certificatePaths)],
        'invoices' => []
    ]);
    exit;
}

file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - Usando certificado PEM: $certificatePath\n", FILE_APPEND);

file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - Intentando SOAP con endpoint: $endpoint\n", FILE_APPEND);
file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - WSDL: $wsdl\n", FILE_APPEND);
file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - Certificado: $certificatePath\n", FILE_APPEND);

// Test de conectividad básica
$parsedUrl = parse_url($endpoint);
$host = $parsedUrl['host'];
$port = $parsedUrl['port'] ?? 443;

$connection = @fsockopen($host, $port, $errno, $errstr, 10);
if (!$connection) {
    file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - ERROR conectividad: $errno - $errstr\n", FILE_APPEND);
    echo json_encode([
        'errors' => ["No se puede conectar a $host:$port - $errno: $errstr"],
        'invoices' => []
    ]);
    exit;
} else {
    fclose($connection);
    file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - Conectividad OK con $host:$port\n", FILE_APPEND);
}

try {
    // Configurar opciones SOAP usando certificado PEM
    $options = [
        'local_cert' => $certificatePath,
        'passphrase' => $companyData['vf_password'], // Mantener contraseña por si acaso
        'trace' => true,
        'exceptions' => true,
        'cache_wsdl' => 0, // WSDL_CACHE_NONE,
        'stream_context' => stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'crypto_method' => STREAM_CRYPTO_METHOD_ANY_CLIENT,
            ],
        ]),
        'soap_version' => SOAP_1_1,
        'style' => SOAP_DOCUMENT,
        'use' => SOAP_LITERAL
    ];

    // Intentar crear cliente SOAP con WSDL
    try {
        file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - Intentando crear SoapClient con WSDL\n", FILE_APPEND);
        $client = new SoapClient($wsdl, $options);
        $client->__setLocation($endpoint);
        file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - SoapClient con WSDL creado exitosamente\n", FILE_APPEND);
    } catch (SoapFault $wsdlError) {
        file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - WSDL falló: " . $wsdlError->getMessage() . "\n", FILE_APPEND);
        // Si falla el WSDL, usar modo no-WSDL con configuración más permisiva
        file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - Intentando SoapClient sin WSDL\n", FILE_APPEND);
        $client = new SoapClient(null, [
            'location' => $endpoint,
            'uri' => 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/',
            'local_cert' => $certificatePath,
            'passphrase' => $companyData['vf_password'],
            'trace' => true,
            'exceptions' => true,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                    'crypto_method' => STREAM_CRYPTO_METHOD_ANY_CLIENT,
                ]
            ]),
            'soap_version' => SOAP_1_1
        ]);
        file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - SoapClient sin WSDL creado exitosamente\n", FILE_APPEND);
    }

    // Procesar campo Signature si existe para SOAP
    if (isset($jsonData['RegistroFactura'])) {
        foreach ($jsonData['RegistroFactura'] as &$registro) {
            if (isset($registro['RegistroAlta']['Signature']) && is_array($registro['RegistroAlta']['Signature'])) {
                $signature = $registro['RegistroAlta']['Signature'];
                // Si tiene enc_value, usar solo ese valor
                if (isset($signature['enc_value'])) {
                    $registro['RegistroAlta']['Signature'] = $signature['enc_value'];
                    file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - Signature procesado: usando enc_value\n", FILE_APPEND);
                }
            }
        }
    }

    // Realizar llamada SOAP
    file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - Realizando llamada SOAP RegFactuSistemaFacturacion\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - Datos enviados: " . json_encode($jsonData, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
    
    $client->__soapCall('RegFactuSistemaFacturacion', [$jsonData]);
    $response = $client->__getLastResponse();
    
    file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - Llamada SOAP exitosa, respuesta recibida\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - Request SOAP: " . $client->__getLastRequest() . "\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - Response SOAP: " . substr($response, 0, 500) . "...\n", FILE_APPEND);

    // Respuesta exitosa básica
    $result = [
        'errors' => [],
        'invoices' => []
    ];

    if ($response && str_starts_with(trim($response), '<')) {
        if (strpos($response, 'RespuestaRegFactuSistemaFacturacion') !== false) {
            $result['invoices']['success'] = 'Enviado correctamente a AEAT via intersoap';
        } else {
            $result['errors'][] = 'Respuesta inesperada de AEAT';
        }
    } else {
        $result['errors'][] = 'Respuesta inválida de AEAT';
    }

    echo json_encode($result);

} catch (SoapFault $e) {
    file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - Error SOAP: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode([
        'errors' => ['Error SOAP en intersoap: ' . $e->getMessage()],
        'invoices' => []
    ]);
} catch (Exception $e) {
    file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - Error general: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode([
        'errors' => ['Error en intersoap: ' . $e->getMessage()],
        'invoices' => []
    ]);
} catch (Error $e) {
    file_put_contents(__DIR__ . '/intersoap_debug.log', date('Y-m-d H:i:s') . " - Error fatal: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode([
        'errors' => ['Error fatal en intersoap: ' . $e->getMessage()],
        'invoices' => []
    ]);
}
