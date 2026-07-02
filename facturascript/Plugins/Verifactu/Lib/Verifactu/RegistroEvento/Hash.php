<?php

/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroEvento;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRegistroEvento;

/**
 * Clase para generar el Hash en el JSON de evento en Verifactu.
 * Validaciones realizadas a fecha del 07-08-2025 en la versión 0.1.2 del registro de hash de Verifactu.
 * https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/Veri-Factu_especificaciones_huella_hash_registros.pdf
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class Hash
{
    public static function generate(VerifactuRegistroEvento $regEvent): bool
    {
        // si el registro ya tiene huella, no hacemos nada
        if (!empty($regEvent->hash)) {
            return true;
        }

        // Comprueba si el archivo existe
        if (!file_exists($regEvent->file_json)) {
            return false;
        }

        // Leemos el contenido del archivo
        $fileContent = file_get_contents($regEvent->file_json);
        if (false === $fileContent) {
            return false;
        }

        // Decodificamos el JSON
        $eventJson = json_decode($fileContent, true);
        if (null === $eventJson) {
            return false;
        }

        $event = 'RegistroEvento';
        $previousRegEvent = self::getPreviousRegEvent($regEvent);
        if (false === self::jsonEncadenamiento($event, $previousRegEvent, $eventJson)) {
            return false;
        }

        self::jsonHuella($event, $eventJson);

        if (empty($eventJson[$event]['Evento']['HuellaEvento'])) {
            return false;
        }

        // Guardamos el JSON actualizado
        $jsonContent = json_encode($eventJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (false === file_put_contents($regEvent->file_json, $jsonContent)) {
            return false;
        }

        // actualizamos el registro de evento con la huella
        $regEvent->hash = $eventJson[$event]['Evento']['HuellaEvento'];
        return $regEvent->save();
    }

    private static function getPreviousRegEvent(VerifactuRegistroEvento $regEvent): VerifactuRegistroEvento
    {
        $previousRegInvoice = new VerifactuRegistroEvento();
        $where = [
            new DataBaseWhere('idempresa', $regEvent->idempresa),
            new DataBaseWhere('id', $regEvent->id, '<'),
        ];
        $previousRegInvoice->loadFromCode('', $where, ['id' => 'DESC']);
        return $previousRegInvoice;
    }

    private static function jsonEncadenamiento(string $event, VerifactuRegistroEvento $previousRegEvent, array &$eventJson): bool
    {
        $key1 = 'Evento';
        $key2 = 'Encadenamiento';

        // si no existe evento de factura previo, no hay encadenamiento, entonces añadimos el primer registro
        if (empty($previousRegEvent->primaryColumnValue())) {
            $eventJson[$event][$key1][$key2]['PrimerEvento'] = 'S';
            return true;
        }

        // si el evento previo no tiene hash, no podemos encadenar
        if (empty($previousRegEvent->hash)) {
            return false;
        }

        // Comprueba si el archivo existe
        if (!file_exists($previousRegEvent->file_json)) {
            return false;
        }

        // Leemos el contenido del archivo
        $previousFileContent = file_get_contents($previousRegEvent->file_json);
        if (false === $previousFileContent) {
            return false;
        }

        // Decodificamos el JSON
        $previousEventJson = json_decode($previousFileContent, true);
        if (null === $previousEventJson) {
            return false;
        }

        // si existe evento previo, añadimos el registro anterior
        $key3 = 'EventoAnterior';
        $eventJson[$event][$key1][$key2][$key3]['TipoEvento'] = $previousRegEvent->type;
        $eventJson[$event][$key1][$key2][$key3]['FechaHoraHusoGenEvento'] = $previousEventJson[$event][$key1]['FechaHoraHusoGenEvento'];
        $eventJson[$event][$key1][$key2][$key3]['HuellaEvento'] = $previousRegEvent->hash;
        return true;
    }

    private static function jsonHuella(string $event, array &$eventJson): void
    {
        $key1 = 'Evento';

        // si alguno de los siguientes datos está vacío o no existe, terminamos
        if (
            empty($eventJson[$event][$key1]['SistemaInformatico']['NIF'])
            || empty($eventJson[$event][$key1]['SistemaInformatico']['IdSistemaInformatico'])
            || empty($eventJson[$event][$key1]['SistemaInformatico']['Version'])
            || empty($eventJson[$event][$key1]['SistemaInformatico']['NumeroInstalacion'])
            || empty($eventJson[$event][$key1]['TipoEvento'])
        ) {
            return;
        }

        // creamos el string
        $string = 'NIF=' . $eventJson[$event][$key1]['SistemaInformatico']['NIF']
            . '&ID='
            . '&IdSistemaInformatico=' . $eventJson[$event][$key1]['SistemaInformatico']['IdSistemaInformatico']
            . '&Version=' . $eventJson[$event][$key1]['SistemaInformatico']['Version']
            . '&NumeroInstalacion=' . $eventJson[$event][$key1]['SistemaInformatico']['NumeroInstalacion']
            . '&NIF=' . ($eventJson[$event][$key1]['ObligadoEmision']['NIF'] ?? '')
            . '&TipoEvento=' . $eventJson[$event][$key1]['TipoEvento']
            . '&HuellaEvento=' . ($eventJson[$event][$key1]['Encadenamiento']['EventoAnterior']['HuellaEvento'] ?? '')
            . '&FechaHoraHusoGenEvento=' . $eventJson[$event][$key1]['FechaHoraHusoGenEvento'];

        $eventJson[$event][$key1]['TipoHuella'] = '01';
        $eventJson[$event][$key1]['HuellaEvento'] = strtoupper(hash('sha256', $string));
    }
}
