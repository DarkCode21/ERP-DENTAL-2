<?php

/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroFactura;


use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Plugins\Verifactu\Lib\FiscalNumberValidator;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRegistroFactura;

/**
 * Clase para generar el Hash en el JSON de alta, subsanación y anulación en Verifactu.
 * Validaciones realizadas a fecha del 07-08-2025 en la versión 0.1.2 del registro de hash de Verifactu.
 * https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/Veri-Factu_especificaciones_huella_hash_registros.pdf
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class Hash
{
    public static function generate(VerifactuRegistroFactura $regInvoice): bool
    {
        // si el registro ya tiene huella, no hacemos nada
        if (!empty($regInvoice->hash)) {
            return true;
        }

        // Comprueba si el archivo existe
        if (!file_exists($regInvoice->file_json)) {
            return false;
        }

        // Leemos el contenido del archivo
        $fileContent = file_get_contents($regInvoice->file_json);
        if (false === $fileContent) {
            return false;
        }

        // Decodificamos el JSON
        $invoiceJson = json_decode($fileContent, true);
        if (null === $invoiceJson) {
            return false;
        }

        // obtenemos el evento de la factura
        $event = $regInvoice->event === VerifactuRegistroFactura::EVENT_ANULACION ? 'RegistroAnulacion' : 'RegistroAlta';

        $previousRegInvoice = self::getPreviousRegInvoice($regInvoice);
        if (false === self::jsonEncadenamiento($event, $previousRegInvoice, $invoiceJson)) {
            return false;
        }

        if ($event === 'RegistroAnulacion') {
            self::jsonHuellaAnulacion($event, $invoiceJson);
        } else {
            self::jsonHuella($event, $invoiceJson);
        }

        if (empty($invoiceJson[$event]['Huella'])) {
            return false;
        }

        // Guardamos el JSON actualizado
        $jsonContent = json_encode($invoiceJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (false === file_put_contents($regInvoice->file_json, $jsonContent)) {
            return false;
        }

        // actualizamos el registro de factura con la huella
        $regInvoice->hash = $invoiceJson[$event]['Huella'];
        return $regInvoice->save();
    }

    private static function getPreviousRegInvoice(VerifactuRegistroFactura $regInvoice): VerifactuRegistroFactura
    {
        $previousRegInvoice = new VerifactuRegistroFactura();
        $where = [
            new DataBaseWhere('idempresa', $regInvoice->idempresa),
            new DataBaseWhere('id', $regInvoice->id, '<'),
        ];
        $previousRegInvoice->loadFromCode('', $where, ['id' => 'DESC']);
        return $previousRegInvoice;
    }

    private static function jsonEncadenamiento(string $event, VerifactuRegistroFactura $previousRegInvoice, array &$invoiceJson): bool
    {
        $key1 = 'Encadenamiento';

        // si no existe evento de factura previo, no hay encadenamiento, entonces añadimos el primer registro
        if (empty($previousRegInvoice->primaryColumnValue())) {
            $invoiceJson[$event][$key1]['PrimerRegistro'] = 'S';
            return true;
        }

        // si el evento previo no tiene hash, no podemos encadenar
        if (empty($previousRegInvoice->hash)) {
            return false;
        }

        // si existe evento de factura previo, añadimos el registro anterior
        $key2 = 'RegistroAnterior';
        $previousInvoice = $previousRegInvoice->getInvoice();
        $invoiceJson[$event][$key1][$key2]['IDEmisorFactura'] = FiscalNumberValidator::normaliceCifNif($previousRegInvoice->getCompany()->cifnif, '/^[A-Z0-9]{1,9}$/');
        $invoiceJson[$event][$key1][$key2]['NumSerieFactura'] = Tools::textBreak($previousInvoice->codserie . $previousInvoice->numero, 60, '');
        $invoiceJson[$event][$key1][$key2]['FechaExpedicionFactura'] = date('d-m-Y', strtotime($previousInvoice->fecha));
        $invoiceJson[$event][$key1][$key2]['Huella'] = $previousRegInvoice->hash;
        return true;
    }

    private static function jsonHuella(string $event, array &$invoiceJson): void
    {
        // si alguno de los siguientes datos está vacío o no existe, terminamos
        if (
            empty($invoiceJson[$event]['IDFactura']['IDEmisorFactura'])
            || empty($invoiceJson[$event]['IDFactura']['NumSerieFactura'])
            || empty($invoiceJson[$event]['IDFactura']['FechaExpedicionFactura'])
            || empty($invoiceJson[$event]['TipoFactura'])
            || empty($invoiceJson[$event]['FechaHoraHusoGenRegistro'])
        ) {
            return;
        }

        // creamos el string
        $string = 'IDEmisorFactura=' . $invoiceJson[$event]['IDFactura']['IDEmisorFactura']
            . '&NumSerieFactura=' . $invoiceJson[$event]['IDFactura']['NumSerieFactura']
            . '&FechaExpedicionFactura=' . $invoiceJson[$event]['IDFactura']['FechaExpedicionFactura']
            . '&TipoFactura=' . $invoiceJson[$event]['TipoFactura']
            . '&CuotaTotal=' . $invoiceJson[$event]['CuotaTotal']
            . '&ImporteTotal=' . $invoiceJson[$event]['ImporteTotal']
            . '&Huella=' . ($invoiceJson[$event]['Encadenamiento']['RegistroAnterior']['Huella'] ?? '')
            . '&FechaHoraHusoGenRegistro=' . $invoiceJson[$event]['FechaHoraHusoGenRegistro'];

        $invoiceJson[$event]['TipoHuella'] = '01';
        $invoiceJson[$event]['Huella'] = strtoupper(hash('sha256', $string));
    }

    private static function jsonHuellaAnulacion(string $event, array &$invoiceJson): void
    {
        // si alguno de los siguientes datos está vacío o no existe, terminamos
        if (
            empty($invoiceJson[$event]['IDFactura']['IDEmisorFacturaAnulada'])
            || empty($invoiceJson[$event]['IDFactura']['NumSerieFacturaAnulada'])
            || empty($invoiceJson[$event]['IDFactura']['FechaExpedicionFacturaAnulada'])
            || empty($invoiceJson[$event]['FechaHoraHusoGenRegistro'])
        ) {
            return;
        }

        // creamos el string
        $string = 'IDEmisorFacturaAnulada=' . $invoiceJson[$event]['IDFactura']['IDEmisorFacturaAnulada']
            . '&NumSerieFacturaAnulada=' . $invoiceJson[$event]['IDFactura']['NumSerieFacturaAnulada']
            . '&FechaExpedicionFacturaAnulada=' . $invoiceJson[$event]['IDFactura']['FechaExpedicionFacturaAnulada']
            . '&Huella=' . ($invoiceJson[$event]['Encadenamiento']['RegistroAnterior']['Huella'] ?? '')
            . '&FechaHoraHusoGenRegistro=' . $invoiceJson[$event]['FechaHoraHusoGenRegistro'];

        $invoiceJson[$event]['TipoHuella'] = '01';
        $invoiceJson[$event]['Huella'] = strtoupper(hash('sha256', $string));
    }
}
