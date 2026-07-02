<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroFactura;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Exception;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\Verifactu\Lib\FiscalNumberValidator;

/**
 * Clase para generar códigos QR para facturas según las especificaciones de VeriFactu.
 * Validaciones realizadas a fecha del 07-08-2025 en la versión 0.4.7 del registro de QR de Verifactu.
 * https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/DetalleEspecificacTecnCodigoQRfactura.pdf
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class QR
{
    /** @var Empresa */
    private static $company;

    /** @var Cliente */
    private static $customer;

    /** @var Ejercicio */
    private static $exercise;

    /** @var FacturaCliente */
    private static $invoice;

    public static function generate(FacturaCliente $invoice): string
    {
        try {
            // Obtener datos necesarios
            self::$invoice = $invoice;
            self::$customer = $invoice->getSubject();
            self::$company = $invoice->getCompany();
            self::$exercise = $invoice->getExercise();

            // Construir el string de datos según la especificación
            $qrData = self::buildQRData();

            $options = new QROptions([
                'version' => QRCode::VERSION_AUTO,
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel' => QRCode::ECC_Q,
                'scale' => 5,
                'imageBase64' => true,
            ]);

            $qrcode = new QRCode($options);
            return $qrcode->render($qrData);

        } catch (Exception $e) {
            Tools::log()->error('verifactu-qr-error', [
                '%error%' => $e->getMessage(),
                'model-code' => self::$invoice->primaryColumnValue(),
                'model-class' => self::$invoice->modelClassName(),
            ]);
            return '';
        }
    }

    private static function buildQRData(): string
    {
        if (self::$exercise->vf_mode === 'verifactu') {
            $url = self::$company->vf_debug_mode
                ? 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR'
                : 'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR';
        } else {
            $url = self::$company->vf_debug_mode
                ? 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQRNoVerifactu'
                : 'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQRNoVerifactu';
        }

        return $url
            . '?nif=' . urlencode(FiscalNumberValidator::normaliceCifNif(self::$company->cifnif, '/^[A-Z0-9]{1,9}$/', '99999999R'))
            . '&numserie=' . urlencode(Tools::textBreak(self::$invoice->codserie . self::$invoice->numero, 60, ''))
            . '&fecha=' . urlencode(date('d-m-Y', strtotime(self::$invoice->fecha)))
            . '&importe=' . urlencode(self::$invoice->total);
    }
}