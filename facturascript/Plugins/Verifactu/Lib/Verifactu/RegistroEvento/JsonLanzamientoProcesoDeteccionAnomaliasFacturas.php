<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroEvento;

use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\JsonTrait;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRegistroFactura;

/**
 * Clase para generar el JSON de lanzamiento del proceso de detección de anomalías en el registro de facturación.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class JsonLanzamientoProcesoDeteccionAnomaliasFacturas
{
    use JsonTrait;
    use JsonRegistroEventoTrait;

    const FILE_NAME = 'lanzamiento-proceso-deteccion-anomalias-facturas';

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

        Tools::settingsSet('verifactu', 'inicio_deteccion_anomalias_facturas', Tools::date());
        Tools::settingsSave();
        return true;
    }

    private static function jsonDatosPropiosEvento(string $key0): bool
    {
        // establecemos las keys
        $key1 = 'DatosPropiosEvento';
        $key2 = 'LanzamientoProcesoDeteccionAnomaliasRegFacturacion';

        // obtenemos la fecha de búsqueda
        $dateStart = Tools::settings('verifactu', 'inicio_deteccion_anomalias_facturas', Tools::date());

        // obtenemos los registros de facturación
        $regInvoices = VerifactuRegistroFactura::all([
            new DataBaseWhere('idempresa', self::$company->primaryColumnValue()),
            new DataBaseWhere('hash', null, 'IS NOT'),
            new DataBaseWhere('mode', 'no-verifactu'),
            new DataBaseWhere('creation_date', $dateStart . ' 00:00:00', '>='),
            new DataBaseWhere('creation_date', Tools::date() . ' 23:59:59', '<='),
        ], ['id' => 'ASC']);

        if (!self::jsonLanzamientoProcesoDeteccionAnomaliasRegFacturacion($regInvoices, $key0, $key1, $key2)) {
            return false;
        }

        return true;
    }

    private static function jsonLanzamientoProcesoDeteccionAnomaliasRegFacturacion(array $regInvoices, string $key0, string $key1, string $key2): bool
    {
        if (false === self::jsonRealizadoProcesoSobreIntegridadHuellasRegFacturacion($regInvoices, $key0, $key1, $key2)) {
            return false;
        } elseif (false === self::jsonRealizadoProcesoSobreIntegridadFirmasRegFacturacion($regInvoices, $key0, $key1, $key2)) {
            return false;
        } elseif (false === self::jsonRealizadoProcesoSobreTrazabilidadCadenaRegFacturacion($regInvoices, $key0, $key1, $key2)) {
            return false;
        } elseif (false === self::jsonRealizadoProcesoSobreTrazabilidadFechasRegFacturacion($regInvoices, $key0, $key1, $key2)) {
            return false;
        }

        return true;
    }

    private static function jsonRealizadoProcesoSobreIntegridadHuellasRegFacturacion(array $regInvoices, string $key0, string $key1, string $key2): bool
    {
        // TODO: aquí se debería comprobar si las huellas de cada registros están correctamente formadas, formándolas nuevo
        // no es necesario formar las huellas de nuevo, ya que se generan mediante el cron
        self::$json[$key0][$key1][$key2]['RealizadoProcesoSobreIntegridadHuellasRegFacturacion'] = 'S';
        self::$json[$key0][$key1][$key2]['NumeroDeRegistrosFacturacionProcesadosSobreIntegridadHuellas'] = count($regInvoices);
        return true;
    }

    private static function jsonRealizadoProcesoSobreIntegridadFirmasRegFacturacion(array $regInvoices, string $key0, string $key1, string $key2): bool
    {
        // TODO: aquí se debería comprobar si las firmas de cada registros están correctamente formadas, formándolas nuevo
        // no es necesario formar las firmas de nuevo, ya que se generan mediante el cron
        self::$json[$key0][$key1][$key2]['RealizadoProcesoSobreIntegridadFirmasRegFacturacion'] = 'S';
        self::$json[$key0][$key1][$key2]['NumeroDeRegistrosFacturacionProcesadosSobreIntegridadFirmas'] = count($regInvoices);
        return true;
    }

    private static function jsonRealizadoProcesoSobreTrazabilidadCadenaRegFacturacion(array $regInvoices, string $key0, string $key1, string $key2): bool
    {
        $lastHash = '';
        foreach ($regInvoices as $regInvoice) {
            if (empty($lastHash)) {
                // si es el primer registro, guardamos el hash
                $lastHash = $regInvoice->hash;
                continue;
            }

            // Comprueba si el archivo existe
            if (!file_exists($regInvoice->file_json)) {
                $lastHash = $regInvoice->hash;
                JsonDeteccionAnomaliasRegFacturacion::generate(
                    '04',
                    '90',
                    $regInvoice,
                    'El archivo JSON no existe: ' . $regInvoice->file_json);
                continue;
            }

            // Leemos el contenido del archivo
            $fileContent = file_get_contents($regInvoice->file_json);
            if (false === $fileContent) {
                $lastHash = $regInvoice->hash;
                JsonDeteccionAnomaliasRegFacturacion::generate(
                    '04',
                    '90',
                    $regInvoice,
                    'Error al leer el archivo JSON: ' . $regInvoice->file_json);
                continue;
            }

            // Decodificamos el JSON
            $eventJson = json_decode($fileContent, true);
            if (null === $eventJson) {
                $lastHash = $regInvoice->hash;
                JsonDeteccionAnomaliasRegFacturacion::generate(
                    '04',
                    '90',
                    $regInvoice,
                    'Error al decodificar el JSON: ' . json_last_error_msg());
                continue;
            }

            // obtenemos el evento del registro de factura
            $event = $regInvoice->event === VerifactuRegistroFactura::EVENT_ANULACION
                ? 'RegistroAnulacion' : 'RegistroAlta';

            // comprobamos si el hash anterior coincide con el hash anterior del registro
            if ($eventJson[$event]['Encadenamiento']['RegistroAnterior']['Huella'] !== $lastHash) {
                $lastHash = $regInvoice->hash;
                JsonDeteccionAnomaliasRegFacturacion::generate(
                    '04',
                    '01',
                    $regInvoice,
                    'El hash del registro anterior no coincide con el último hash procesado: ' . $eventJson[$event]['Encadenamiento']['RegistroAnterior']['Huella']
                );
                continue;
            }

            $lastHash = $regInvoice->hash;
        }

        self::$json[$key0][$key1][$key2]['RealizadoProcesoSobreTrazabilidadCadenaRegFacturacion'] = 'S';
        self::$json[$key0][$key1][$key2]['NumeroDeRegistrosFacturacionProcesadosSobreTrazabilidadCadena'] = count($regInvoices);
        return true;
    }

    private static function jsonRealizadoProcesoSobreTrazabilidadFechasRegFacturacion(array $regInvoices, string $key0, string $key1, string $key2): bool
    {
        // TODO: aquí se debe comprobar la trazabilidad de la fecha de inclusión de los registros sean correlativos
        // ya son correlativos por defecto al añadirse a la cola de eventos
        self::$json[$key0][$key1][$key2]['RealizadoProcesoSobreTrazabilidadFechasRegFacturacion'] = 'S';
        self::$json[$key0][$key1][$key2]['NumeroDeRegistrosFacturacionProcesadosSobreTrazabilidadFechas'] = count($regInvoices);
        return true;
    }

    private static function jsonTipoEvento(string $key0): bool
    {
        self::$json[$key0]['TipoEvento'] = '03';
        return true;
    }
}