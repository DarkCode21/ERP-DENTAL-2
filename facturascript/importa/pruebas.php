<?php
$url = 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_NOBODY, true); // Solo cabeceras
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$response = curl_exec($ch);
if ($response === false) {
    echo 'Error cURL: ' . curl_error($ch);
} else {
    echo '<pre>' . htmlspecialchars($response) . '</pre>';
}
curl_close($ch);
?>