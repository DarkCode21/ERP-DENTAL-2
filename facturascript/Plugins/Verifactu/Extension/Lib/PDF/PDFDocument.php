<?php

/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Extension\Lib\PDF;

use Closure;
use FacturaScripts\Core\Tools;

use FacturaScripts\Core\Lib\PDF\PDFDocument as parentClass;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
abstract class PDFDocument extends parentClass
{
    public function qrImageHeader(): Closure
    {
        return function ($model) {
            // si el modelo no es una factura de cliente, no añadimos el QR
            if ($model->modelClassName() !== 'FacturaCliente') {
                return;
            }

            // si la empresa está en modo pruebas y el modo debug no está activado, no añadimos el QR
            if ($model->getCompany()->vf_debug_mode && !Tools::config('DEBUG')) {
                return;
            }

            // si la factura no está enviada, no añadimos el QR
            if (!$model->verifactuCheckAlta()) {
                return;
            }

            return $model->verifactuGetQr();
        };
    }

    public function qrTitleHeader(): Closure
    {
        return function ($model) {
            if (
                $model->modelClassName() !== 'FacturaCliente'
                || !$model->verifactuCheckAlta()
            ) {
                return;
            }

            return 'Veri*Factu';
        };
    }
}
