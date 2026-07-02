<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroFactura;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\Serie;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\JsonTrait;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRegistroFactura;

/**
 * Trait padre para generar datos comunes en el JSON de alta, subsanación y anulación.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait JsonRegistroFacturaTrait
{
    /** @var Cliente */
    private static $customer;

    /** @var FacturaCliente */
    private static $invoice;

    /** @var FacturaCliente */
    private static $invoicePreview;

    /** @var FacturaCliente */
    private static $invoiceRefund;

    /** @var array */
    private static $lines;

    /** @var Serie */
    private static $serie;

    private static function createEvent(string $event): bool
    {
        $regInvoice = new VerifactuRegistroFactura();
        $regInvoice->idempresa = self::$invoice->idempresa;
        $regInvoice->codejercicio = self::$invoice->codejercicio;
        $regInvoice->idfactura = self::$invoice->idfactura;
        $regInvoice->event = $event;
        $regInvoice->file_json = self::$filePath;
        $regInvoice->mode = self::$exercise->vf_mode;
        if (false === $regInvoice->save()) {
            self::deleteFile(self::$filePath);
            Tools::log()->error('verifactu-event-not-saved', [
                'model-code' => self::$invoice->primaryColumnValue(),
                'model-class' => self::$invoice->modelClassName(),
                '%event%' => $event,
            ]);
            return false;
        }

        return true;
    }

    private static function createFile(string $eventName): bool
    {
        // creamos la carpeta donde se guardará el fichero
        $folderPath = JsonTrait::getFolderVerifactu() . self::$company->idempresa . '/RegistrosFacturas/' . date('Y');
        if (false === Tools::folderCheckOrCreate($folderPath)) {
            Tools::log()->warning('cannot-create-folder', array(
                '%folder%' => $folderPath,
                'model-code' => self::$invoice->primaryColumnValue(),
                'model-class' => self::$invoice->modelClassName(),
            ));
            return false;
        }

        // obtenemos la ruta del fichero
        self::$filePath = $folderPath . '/' . self::$invoice->idfactura . '-' . $eventName . '.json';

        // eliminar el archivo si ya existe
        self::deleteFile(self::$filePath);

        // guardamos el JSON en el fichero
        if (false === file_put_contents(self::$filePath, json_encode(self::$json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
            Tools::log()->warning('cannot-save-file', [
                '%file%' => self::$filePath,
                'model-code' => self::$invoice->primaryColumnValue(),
                'model-class' => self::$invoice->modelClassName(),
            ]);
            self::deleteFile(self::$filePath);
            return false;
        }

        return true;
    }

    private static function getIDType(string $idFiscal): string
    {
        return match ($idFiscal) {
            'CIF', 'IFZ', 'NIF' => '02',
            'Pasaporte' => '03',
            'DNI' => '04',
            'NIE' => '05',
            default => '06',
        };
    }

    private static function getInvoiceRefund(): FacturaCliente
    {
        $refund = new FacturaCliente();
        $refund->loadFromCode(self::$invoice->idfacturarect);
        return $refund;
    }

    private static function loadData(FacturaCliente $invoice): void
    {
        self::$invoice = $invoice;
        self::$serie = $invoice->getSerie();
        self::$customer = $invoice->getSubject();
        self::$lines = $invoice->getLines();
        self::$invoiceRefund = self::getInvoiceRefund();
        self::$exercise = $invoice->getExercise();
    }

    private static function jsonRefExterna(): bool
    {
        self::$json['RefExterna'] = self::$invoice->idfactura . '|' . self::$invoice->codigo;
        return true;
    }
}
