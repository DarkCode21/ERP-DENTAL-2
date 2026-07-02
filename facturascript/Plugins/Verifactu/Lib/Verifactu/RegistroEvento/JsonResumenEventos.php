<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroEvento;

use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\JsonTrait;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRegistroEvento;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRegistroFactura;

/**
 * Clase para generar el JSON de resumen de eventos de Verifactu.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class JsonResumenEventos
{
    use JsonTrait;
    use JsonRegistroEventoTrait;

    const FILE_NAME = 'resumen-eventos';

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

        Tools::settingsSet('verifactu', 'inicio_resumen_eventos', Tools::date());
        Tools::settingsSave();
        return true;
    }

    private static function jsonDatosPropiosEvento(string $key0): bool
    {
        // establecemos las keys
        $key1 = 'DatosPropiosEvento';
        $key2 = 'ResumenEventos';

        // obtenemos la fecha de búsqueda
        $dateStartEvent = Tools::settings('verifactu', 'inicio_resumen_eventos', Tools::date());

        // obtenemos los registros de eventos
        $regEvents = VerifactuRegistroEvento::all([
            new DataBaseWhere('idempresa', self::$company->primaryColumnValue()),
            new DataBaseWhere('hash', null, 'IS NOT'),
            new DataBaseWhere('creation_date', $dateStartEvent . ' 00:00:00', '>='),
            new DataBaseWhere('creation_date', Tools::date() . ' 23:59:59', '<='),
        ], ['id' => 'ASC']);

        // obtenemos la fecha de búsqueda
        $dateStartInvoice = Tools::settings('verifactu', 'inicio_deteccion_anomalias_facturas', Tools::date());

        // obtenemos los registros de facturación
        $regInvoices = VerifactuRegistroFactura::all([
            new DataBaseWhere('idempresa', self::$company->primaryColumnValue()),
            new DataBaseWhere('hash', null, 'IS NOT'),
            new DataBaseWhere('mode', 'no-verifactu'),
            new DataBaseWhere('creation_date', $dateStartInvoice . ' 00:00:00', '>='),
            new DataBaseWhere('creation_date', Tools::date() . ' 23:59:59', '<='),
        ], ['id' => 'ASC']);

        if (!self::jsonResumenEventos($regEvents, $regInvoices, $key0, $key1, $key2)) {
            return false;
        }

        return true;
    }

    private static function jsonRegistroFacturacionFinalPeriodo(array $regInvoices, string $key0, string $key1, string $key2): bool
    {
        // si no hay registros de facturación, no hacemos nada
        if (empty($regInvoices)) {
            return true;
        }

        // obtenemos el último registro de facturación
        $regInvoice = end($regInvoices);

        // Comprueba si el archivo existe
        if (!file_exists($regInvoice->file_json)) {
            return true;
        }

        // Leemos el contenido del archivo
        $fileContent = file_get_contents($regInvoice->file_json);
        if (false === $fileContent) {
            return true;
        }

        // Decodificamos el JSON
        $eventJson = json_decode($fileContent, true);
        if (null === $eventJson) {
            return true;
        }

        // obtenemos el evento del registro de factura
        $event = $regInvoice->event === VerifactuRegistroFactura::EVENT_ANULACION
            ? 'RegistroAnulacion' : 'RegistroAlta';

        // añadimos el registro de facturación final del periodo
        self::$json[$key0][$key1][$key2]['RegistroFacturacionFinalPeriodo']['IDEmisorFactura'] = $eventJson[$event]['IDFactura']['IDEmisorFactura'];
        self::$json[$key0][$key1][$key2]['RegistroFacturacionFinalPeriodo']['NumSerieFactura'] = $eventJson[$event]['IDFactura']['NumSerieFactura'];
        self::$json[$key0][$key1][$key2]['RegistroFacturacionFinalPeriodo']['FechaExpedicionFactura'] = $eventJson[$event]['IDFactura']['FechaExpedicionFactura'];
        self::$json[$key0][$key1][$key2]['RegistroFacturacionFinalPeriodo']['Huella'] = $eventJson[$event]['Huella'];

        return true;
    }

    private static function jsonRegistroFacturacionInicialPeriodo(array $regInvoices, string $key0, string $key1, string $key2): bool
    {
        // si no hay registros de facturación, no hacemos nada
        if (empty($regInvoices)) {
            return true;
        }

        // obtenemos el primer registro de facturación
        $regInvoice = reset($regInvoices);

        // Comprueba si el archivo existe
        if (!file_exists($regInvoice->file_json)) {
            return true;
        }

        // Leemos el contenido del archivo
        $fileContent = file_get_contents($regInvoice->file_json);
        if (false === $fileContent) {
            return true;
        }

        // Decodificamos el JSON
        $eventJson = json_decode($fileContent, true);
        if (null === $eventJson) {
            return true;
        }

        // obtenemos el evento del registro de factura
        $event = $regInvoice->event === VerifactuRegistroFactura::EVENT_ANULACION
            ? 'RegistroAnulacion' : 'RegistroAlta';

        // añadimos el registro de facturación inicial del periodo
        self::$json[$key0][$key1][$key2]['RegistroFacturacionInicialPeriodo']['IDEmisorFactura'] = $eventJson[$event]['IDFactura']['IDEmisorFactura'];
        self::$json[$key0][$key1][$key2]['RegistroFacturacionInicialPeriodo']['NumSerieFactura'] = $eventJson[$event]['IDFactura']['NumSerieFactura'];
        self::$json[$key0][$key1][$key2]['RegistroFacturacionInicialPeriodo']['FechaExpedicionFactura'] = $eventJson[$event]['IDFactura']['FechaExpedicionFactura'];
        self::$json[$key0][$key1][$key2]['RegistroFacturacionInicialPeriodo']['Huella'] = $eventJson[$event]['Huella'];

        return true;
    }

    private static function jsonResumenEventos(array $regEvents, array $regInvoices, string $key0, string $key1, string $key2): bool
    {
        if (!self::jsonResumenTipoEvento($regEvents, $key0, $key1, $key2)) {
            return false;
        } elseif (!self::jsonRegistroFacturacionInicialPeriodo($regInvoices, $key0, $key1, $key2)) {
            return false;
        } elseif (!self::jsonRegistroFacturacionFinalPeriodo($regInvoices, $key0, $key1, $key2)) {
            return false;
        } elseif (!self::jsonTotales($regInvoices, $key0, $key1, $key2)) {
            return false;
        }

        return true;
    }

    private static function jsonResumenTipoEvento(array $regEvents, string $key0, string $key1, string $key2): bool
    {
        // creamos un array por cada tipo de evento y su contador
        $eventTypes = [];
        foreach ($regEvents as $event) {
            if (!isset($eventTypes[$event->type])) {
                $eventTypes[$event->type] = 0;
            }
            $eventTypes[$event->type]++;
        }

        // si hay más de 20 tipos, no hacemos nada
        if (count($eventTypes) > 20) {
            return false;
        }

        // añadimos los eventos al JSON
        foreach ($eventTypes as $type => $count) {
            self::$json[$key0][$key1][$key2]['TipoEvento'][] = [
                'TipoEvento' => $type,
                'NumeroDeEventos' => $count,
            ];
        }

        return true;
    }

    private static function jsonTipoEvento(string $key0): bool
    {
        self::$json[$key0]['TipoEvento'] = '10';
        return true;
    }

    private static function jsonTotales(array $regInvoices, string $key0, string $key1, string $key2): bool
    {
        // debemos obtener una serie de totales de las facturas
        $data = [
            'altas' => 0,
            'anulaciones' => 0,
            'cuotas' => 0.0,
            'importes' => 0.0,
        ];

        foreach ($regInvoices as $regInvoice) {
            if ($regInvoices->event === VerifactuRegistroFactura::EVENT_ALTA) {
                $data['altas']++;
            } elseif ($regInvoices->event === VerifactuRegistroFactura::EVENT_ANULACION) {
                $data['anulaciones']++;
            } else {
                continue;
            }

            // Comprueba si el archivo existe
            if (!file_exists($regInvoice->file_json)) {
                continue;
            }

            // Leemos el contenido del archivo
            $fileContent = file_get_contents($regInvoice->file_json);
            if (false === $fileContent) {
                continue;
            }

            // Decodificamos el JSON
            $eventJson = json_decode($fileContent, true);
            if (null === $eventJson) {
                continue;
            }

            // obtenemos el evento del registro de factura
            $event = $regInvoice->event === VerifactuRegistroFactura::EVENT_ANULACION
                ? 'RegistroAnulacion' : 'RegistroAlta';

            // sumamos las cuotas e importes
            $data['cuotas'] += $eventJson[$event]['CuotaTotal'];
            $data['importes'] += $eventJson[$event]['ImporteTotal'];
        }

        // añadimos los totales al JSON
        self::$json[$key0][$key1][$key2]['NumeroDeRegistrosFacturacionAltaGenerados'] = $data['altas'];
        self::$json[$key0][$key1][$key2]['SumaCuotaTotalAlta'] = number_format($data['cuotas'], 2, '.', '');
        self::$json[$key0][$key1][$key2]['SumaImporteTotalAlta1'] = number_format($data['importes'], 2, '.', '');
        self::$json[$key0][$key1][$key2]['NumeroDeRegistrosFacturacionAnulacionGenerados'] = $data['anulaciones'];

        return true;
    }
}