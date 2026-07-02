<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Tickets;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Tickets\Normal;
use FacturaScripts\Dinamic\Model\Ticket;
use FacturaScripts\Dinamic\Model\TicketPrinter;
use Mike42\Escpos\Printer;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Verifactu extends Normal
{
    protected static function setBody(ModelClass $model, TicketPrinter $printer): void
    {
        parent::setBody($model, $printer);

        // si el modelo no es una factura de cliente, no añadimos el QR
        if ($model->modelClassName() !== 'FacturaCliente') {
            return;
        }

        // si la empresa está en modo pruebas y el modo debug no está activado, no añadimos el QR
        if ($model->getCompany()->vf_debug_mode && !Tools::config('debug')) {
            return;
        }

        // si la factura no está enviada, no añadimos el QR
        if (!$model->verifactuCheckAlta()) {
            return;
        }

        static::$escpos->setJustification(Printer::JUSTIFY_CENTER);
        static::$escpos->qrCode($model->verifactuGetQr(), Printer::QR_ECLEVEL_L, 7);
        static::$escpos->text("\nVeri*Factu\n");
        static::$escpos->setJustification();
    }
}
