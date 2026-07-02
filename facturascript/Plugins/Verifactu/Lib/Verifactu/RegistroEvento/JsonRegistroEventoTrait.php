<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroEvento;

use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\JsonTrait;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRegistroEvento;

/**
 * Trait padre para generar datos comunes en el JSON de registro de eventos.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait JsonRegistroEventoTrait
{
    private static function init(): bool
    {
        if (false === self::jsonIDVersion()) {
            return false;
        } elseif (false === self::jsonSistemaInformatico('Evento')) {
            return false;
        } elseif (false === self::jsonFechaHoraHusoGenRegistro('FechaHoraHusoGenEvento', 'Evento')) {
            return false;
        }

        return true;
    }

    private static function createEvent(string $typeName): bool
    {
        $regInvoice = new VerifactuRegistroEvento();
        $regInvoice->idempresa = self::$company->idempresa;
        $regInvoice->codejercicio = self::$exercise->codejercicio ?? null;
        $regInvoice->type = self::$json['RegistroEvento']['Evento']['TipoEvento'];
        $regInvoice->type_name = $typeName;
        $regInvoice->file_json = self::$filePath;
        if (false === $regInvoice->save()) {
            self::deleteFile(self::$filePath);
            Tools::log()->error('verifactu-event-not-saved', [
                '%type%' => self::$json['RegistroEvento']['Evento']['TipoEvento'],
            ]);
            return false;
        }

        return true;
    }

    private static function createFile(string $fileName): bool
    {
        // creamos la carpeta donde se guardará el fichero
        $folderPath = JsonTrait::getFolderVerifactu() . self::$company->idempresa . '/RegistrosEventos/' . date('Y');
        if (false === Tools::folderCheckOrCreate($folderPath)) {
            Tools::log()->warning('cannot-create-folder', array(
                '%folder%' => $folderPath,
            ));
            return false;
        }

        // obtenemos la ruta del fichero
        self::$filePath = $folderPath . '/' . Tools::slug(Tools::dateTime() . '-' . $fileName) . '.json';

        // eliminar el archivo si ya existe
        self::deleteFile(self::$filePath);

        // guardamos el JSON en el fichero
        if (false === file_put_contents(self::$filePath, json_encode(self::$json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
            Tools::log()->warning('cannot-save-file', [
                '%file%' => self::$filePath,
            ]);
            self::deleteFile(self::$filePath);
            return false;
        }

        return true;
    }

    private static function save(string $fileName): bool
    {
        // creamos el array JSON
        $data = self::$json;
        self::$json = [];
        self::$json['RegistroEvento'] = $data;

        // creamos el archivo JSON
        if (false === self::createFile($fileName)) {
            return false;
        }

        // creamos el evento de registro de factura
        if (false === self::createEvent($fileName)) {
            return false;
        }

        return true;
    }
}