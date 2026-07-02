<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroEvento;

use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\JsonTrait;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRegistroEvento;

/**
 * Clase para generar el JSON de detección de anomalías en el registro de eventos.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class JsonDeteccionAnomaliasRegEvento
{
    use JsonTrait;
    use JsonRegistroEventoTrait;

    const FILE_NAME = 'deteccion-anomalias-evento';

    public static function generate(string $typeEvent, string $typeAnomaly, VerifactuRegistroEvento $regEvent, string $message = ''): bool
    {
        if (false === self::init()) {
            return false;
        }

        $key0 = 'Evento';
        if (false === self::jsonTipoEvento($key0, $typeEvent)) {
            return false;
        } elseif (false === self::jsonDatosPropiosEvento($key0, $typeAnomaly, $regEvent, $message)) {
            return false;
        }

        if (false === self::save(self::FILE_NAME . '-' . $typeEvent . '-' . $typeAnomaly)) {
            return false;
        }

        return true;
    }

    private static function jsonDatosPropiosEvento(string $key0, string $typeAnomaly, VerifactuRegistroEvento $regEvent, string $message): bool
    {
        $key1 = 'DatosPropiosEvento';
        $key2 = 'DeteccionAnomaliasRegEvento';

        if (!self::jsonTipoAnomalia($key0, $key1, $key2, $typeAnomaly)) {
            return false;
        } elseif (!self::jsonOtrosDatosAnomalia($key0, $key1, $key2, $message)) {
            return false;
        } elseif (!self::jsonRegEventoAnomalo($key0, $key1, $key2, $regEvent)) {
            return false;
        }

        return true;
    }

    private static function jsonOtrosDatosAnomalia(string $key0, string $key1, string $key2, string $message): bool
    {
        if (empty($message)) {
            return true;
        }

        self::$json[$key0][$key1][$key2]['OtrosDatosAnomalia'] = $message;
        return true;
    }

    private static function jsonRegEventoAnomalo(string $key0, string $key1, string $key2, VerifactuRegistroEvento $regEvent): bool
    {
        // Comprueba si el archivo existe
        if (!file_exists($regEvent->file_json)) {
            return true;
        }

        // Leemos el contenido del archivo
        $fileContent = file_get_contents($regEvent->file_json);
        if (false === $fileContent) {
            return true;
        }

        // Decodificamos el JSON
        $eventJson = json_decode($fileContent, true);
        if (null === $eventJson) {
            return true;
        }

        $key3 = 'RegEventoAnomalo';
        self::$json[$key0][$key1][$key2][$key3]['IDEmisorFactura'] = $eventJson['RegistroEvento']['IDFactura']['IDEmisorFactura'];
        self::$json[$key0][$key1][$key2][$key3]['NumSerieFactura'] = $eventJson['RegistroEvento']['IDFactura']['NumSerieFactura'];
        self::$json[$key0][$key1][$key2][$key3]['FechaExpedicionFactura'] = $eventJson['RegistroEvento']['IDFactura']['FechaExpedicionFactura'];

        return true;
    }

    private static function jsonTipoAnomalia(string $key0, string $key1, string $key2, string $typeAnomaly): bool
    {
        self::$json[$key0][$key1][$key2]['TipoAnomalia'] = $typeAnomaly;
        return true;
    }

    private static function jsonTipoEvento(string $key0, string $typeEvent): bool
    {
        self::$json[$key0]['TipoEvento'] = $typeEvent;
        return true;
    }
}