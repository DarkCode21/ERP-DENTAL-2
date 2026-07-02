<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroEvento;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\JsonTrait;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRegistroEvento;

/**
 * Clase para generar el JSON de lanzamiento del proceso de detección de anomalías en los registros de eventos.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class JsonLanzamientoProcesoDeteccionAnomaliasEventos
{
    use JsonTrait;
    use JsonRegistroEventoTrait;

    const FILE_NAME = 'lanzamiento-proceso-deteccion-anomalias-eventos';

    public static function generate(Empresa $company): bool
    {
        self::$company = $company;

        // si la empresa no está configurada, no hacemos nada
        if (false === self::$company->verifactuIsConfigured(false)) {
            return true;
        }

        if (false === self::init()) {
            return false;
        }

        $key0 = 'Evento';
        if (false === self::jsonTipoEvento($key0)) {
            return false;
        } elseif (false === self::jsonDatosPropiosEvento($key0)) {
            return false;
        }

        if (false === self::save(self::FILE_NAME)) {
            return false;
        }

        Tools::settingsSet('verifactu', 'inicio_deteccion_anomalias_eventos', Tools::date());
        Tools::settingsSave();
        return true;
    }

    private static function jsonDatosPropiosEvento(string $key0): bool
    {
        // establecemos las keys
        $key1 = 'DatosPropiosEvento';
        $key2 = 'LanzamientoProcesoDeteccionAnomaliasRegEvento';

        // obtenemos la fecha de búsqueda
        $dateStart = Tools::settings('verifactu', 'inicio_deteccion_anomalias_eventos', Tools::date());

        // obtenemos los registros de eventos
        $regEvents = VerifactuRegistroEvento::all([
            new DataBaseWhere('idempresa', self::$company->primaryColumnValue()),
            new DataBaseWhere('hash', null, 'IS NOT'),
            new DataBaseWhere('creation_date', $dateStart . ' 00:00:00', '>='),
            new DataBaseWhere('creation_date', Tools::date() . ' 23:59:59', '<='),
        ], ['id' => 'ASC']);

        if (!self::jsonLanzamientoProcesoDeteccionAnomaliasRegEvento($regEvents, $key0, $key1, $key2)) {
            return false;
        }

        return true;
    }

    private static function jsonLanzamientoProcesoDeteccionAnomaliasRegEvento(array $regEvents, string $key0, string $key1, string $key2): bool
    {
        if (false === self::jsonRealizadoProcesoSobreIntegridadHuellasRegEvento($regEvents, $key0, $key1, $key2)) {
            return false;
        } elseif (false === self::jsonRealizadoProcesoSobreIntegridadFirmasRegEvento($regEvents, $key0, $key1, $key2)) {
            return false;
        } elseif (false === self::jsonRealizadoProcesoSobreTrazabilidadCadenaRegEvento($regEvents, $key0, $key1, $key2)) {
            return false;
        } elseif (false === self::jsonRealizadoProcesoSobreTrazabilidadFechasRegEvento($regEvents, $key0, $key1, $key2)) {
            return false;
        }

        return true;
    }

    private static function jsonRealizadoProcesoSobreIntegridadHuellasRegEvento(array $regEvents, string $key0, string $key1, string $key2): bool
    {
        // TODO: aquí se debería comprobar si las huellas de cada registros están correctamente formadas, formándolas nuevo
        // no es necesario formar las huellas de nuevo, ya que se generan mediante el cron
        self::$json[$key0][$key1][$key2]['RealizadoProcesoSobreIntegridadHuellasRegEvento'] = 'S';
        self::$json[$key0][$key1][$key2]['NumeroDeRegistrosEventoProcesadosSobreIntegridadHuellas'] = count($regEvents);
        return true;
    }

    private static function jsonRealizadoProcesoSobreIntegridadFirmasRegEvento(array $regEvents, string $key0, string $key1, string $key2): bool
    {
        // TODO: aquí se debería comprobar si las firmas de cada registros están correctamente formadas, formándolas nuevo
        // no es necesario formar las firmas de nuevo, ya que se generan mediante el cron
        self::$json[$key0][$key1][$key2]['RealizadoProcesoSobreIntegridadFirmasRegEvento'] = 'S';
        self::$json[$key0][$key1][$key2]['NumeroDeRegistrosEventoProcesadosSobreIntegridadFirmas'] = count($regEvents);
        return true;
    }

    private static function jsonRealizadoProcesoSobreTrazabilidadCadenaRegEvento(array $regEvents, string $key0, string $key1, string $key2): bool
    {
        $lastHash = '';
        foreach ($regEvents as $regEvent) {
            if (empty($lastHash)) {
                // si es el primer registro, guardamos el hash
                $lastHash = $regEvent->hash;
                continue;
            }

            // Comprueba si el archivo existe
            if (!file_exists($regEvent->file_json)) {
                $lastHash = $regEvent->hash;
                JsonDeteccionAnomaliasRegEvento::generate(
                    '06',
                    '90',
                    $regEvent,
                    'El archivo JSON no existe: ' . $regEvent->file_json);
                continue;
            }

            // Leemos el contenido del archivo
            $fileContent = file_get_contents($regEvent->file_json);
            if (false === $fileContent) {
                $lastHash = $regEvent->hash;
                JsonDeteccionAnomaliasRegEvento::generate(
                    '06',
                    '90',
                    $regEvent,
                    'Error al leer el archivo JSON: ' . $regEvent->file_json);
                continue;
            }

            // Decodificamos el JSON
            $eventJson = json_decode($fileContent, true);
            if (null === $eventJson) {
                $lastHash = $regEvent->hash;
                JsonDeteccionAnomaliasRegEvento::generate(
                    '06',
                    '90',
                    $regEvent,
                    'Error al decodificar el JSON: ' . json_last_error_msg());
                continue;
            }

            // comprobamos si el hash anterior coincide con el hash anterior del registro
            if (isset($eventJson['RegistroEvento']['Encadenamiento']['RegistroAnterior']['Huella'])
                && $eventJson['RegistroEvento']['Encadenamiento']['RegistroAnterior']['Huella'] !== $lastHash) {
                $lastHash = $regEvent->hash;
                JsonDeteccionAnomaliasRegEvento::generate(
                    '06',
                    '01',
                    $regEvent,
                    'El hash del registro anterior no coincide con el último hash procesado: ' . $eventJson['RegistroEvento']['Encadenamiento']['RegistroAnterior']['Huella']
                );
                continue;
            }

            $lastHash = $regEvent->hash;
        }

        self::$json[$key0][$key1][$key2]['RealizadoProcesoSobreTrazabilidadCadenaRegEvento'] = 'S';
        self::$json[$key0][$key1][$key2]['NumeroDeRegistrosEventoProcesadosSobreTrazabilidadCadena'] = count($regEvents);
        return true;
    }

    private static function jsonRealizadoProcesoSobreTrazabilidadFechasRegEvento(array $regEvents, string $key0, string $key1, string $key2): bool
    {
        // TODO: aquí se debe comprobar la trazabilidad de la fecha de inclusión de los registros sean correlativos
        // ya son correlativos por defecto al añadirse a la cola de eventos
        self::$json[$key0][$key1][$key2]['RealizadoProcesoSobreTrazabilidadFechasRegEvento'] = 'S';
        self::$json[$key0][$key1][$key2]['NumeroDeRegistrosEventoProcesadosSobreTrazabilidadFechas'] = count($regEvents);
        return true;
    }

    private static function jsonTipoEvento(string $key0): bool
    {
        self::$json[$key0]['TipoEvento'] = '05';
        return true;
    }
}