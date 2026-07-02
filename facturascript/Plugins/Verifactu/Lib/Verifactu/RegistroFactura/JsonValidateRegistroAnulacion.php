<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroFactura;

use FacturaScripts\Core\Lib\Vies;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroFactura\JsonValidateTrait;

/**
 * Clase para validar un JSON de anulación en Verifactu.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class JsonValidateRegistroAnulacion
{
    use JsonValidateTrait;

    /** @var array */
    private static $json = [];

    /** @var string */
    private static $event = 'RegistroAnulacion';

    public static function validate(array $json): bool
    {
        // comprobamos que el JSON no esté vacío
        self::$json = $json;
        if (empty($json)) {
            Tools::log()->error('json-empty');
            return false;
        }

        // comprobamos que el evento sea RegistroAlta
        if (!isset(self::$json[self::$event])) {
            Tools::log()->error('json-not-event-found', [
                '%event%' => self::$event,
            ]);
            return false;
        }

        if (false === self::idFactura()) {
            return false;
        } elseif (false === self::generadoPor()) {
            return false;
        } elseif (false === self::generador()) {
            return false;
        } elseif (!self::huellaAnterior()) {
            return false;
        } elseif (!self::sistemaInformatico()) {
            return false;
        } elseif (!self::fechaHoraHusoGenRegistro()) {
            return false;
        } elseif (!self::huella()) {
            return false;
        }

        return true;
    }

    private static function generador(): bool
    {
        // Si se informa esta agrupación, debe haberse informado el campo GeneradoPor
        if (isset(self::$json[self::$event]['Generador']) && !isset(self::$json[self::$event]['GeneradoPor'])) {
            Tools::log()->error('json-missing-group-generadopor');
            return false;
        }

        // Si se identifica mediante NIF, el NIF debe estar identificado y ser distinto del campo NIF de la
        // agrupación ObligadoEmisión del bloque Cabecera
        // Está comprobación se hace en el cron al momento de mandar el JSON

        // Si se cumplimenta NIF, no deberá existir la agrupación IDOtro y viceversa,
        // pero es obligatorio que se cumplimente uno de los dos
        if (isset(self::$json[self::$event]['Generador']['NIF']) && isset(self::$json[self::$event]['Generador']['IDOtro'])) {
            Tools::log()->error('json-invalid-nif-and-idotro-both-defined-generador');
            return false;
        } elseif (!isset(self::$json[self::$event]['Generador']['NIF']) && empty(self::$json[self::$event]['Generador']['IDOtro'])) {
            Tools::log()->error('json-missing-nif-or-idotro-required-generador');
            return false;
        }

        // Si el campo IDType = “02” (NIF-IVA), no será exigible el campo CodigoPais
        if (isset(self::$json[self::$event]['Generador']['IDOtro']['IDType'])
            && self::$json[self::$event]['Generador']['IDOtro']['IDType'] === '02'
            && isset(self::$json[self::$event]['Generador']['IDOtro']['CodigoPais'])) {
            Tools::log()->error('json-invalid-codigo-pais-not-required-for-nif-iva-generador');
            return false;
        }

        // Cuando el generador se identifique a través de la agrupación IDOtro e IDType sea “02”, se
        // validará que el campo identificador ID se ajuste a la estructura de NIF-IVA de alguno de los
        // Estados Miembros y debe estar identificado
        if (isset(self::$json[self::$event]['Generador']['IDOtro']['IDType'])
            && self::$json[self::$event]['Generador']['IDOtro']['IDType'] === '02'
            && isset(self::$json[self::$event]['Generador']['IDOtro']['ID'])
            && !in_array(self::$json[self::$event]['Generador']['IDOtro']['CodigoPais'], Vies::EU_COUNTRIES)) {
            Tools::log()->error('json-invalid-codigo-pais-not-in-eu-generador');
            return false;
        }

        // Si el valor de GeneradoPor es igual a “E”, debe estar relleno el campo NIF en el generador
        if (isset(self::$json[self::$event]['GeneradoPor']) && self::$json[self::$event]['GeneradoPor'] === 'E'
            && !isset(self::$json[self::$event]['Generador']['NIF'])) {
            Tools::log()->error('json-missing-nif-generador-required-when-generadopor-e');
            return false;
        }

        // Si el valor de GeneradoPor es igual a “D”, cuando el Generador se identifique a través del
        // bloque IDOtro y CodigoPais sea "ES", se validará que el campo IDType sea “03” o “07”
        if (isset(self::$json[self::$event]['GeneradoPor']) && self::$json[self::$event]['GeneradoPor'] === 'D'
            && isset(self::$json[self::$event]['Generador']['IDOtro']['CodigoPais'])
            && self::$json[self::$event]['Generador']['IDOtro']['CodigoPais'] === 'ES'
            && isset(self::$json[self::$event]['Generador']['IDOtro']['IDType'])
            && !in_array(self::$json[self::$event]['Generador']['IDOtro']['IDType'], ['03', '07'])) {
            Tools::log()->error('json-invalid-idtype-generador-required-when-generadopor-d');
            return false;
        }

        // Si el valor del campo GeneradoPor es igual a “T”
        // 1 - Si se identifica a través de la agrupación IDOtro y CodigoPais sea "ES", se validará que el campo IDType sea “03”
        // 2 - No se admite el tipo de identificación IDType “07” (“No censado”)
        if (isset(self::$json[self::$event]['GeneradoPor']) && self::$json[self::$event]['GeneradoPor'] === 'T') {
            if (isset(self::$json[self::$event]['Generador']['IDOtro']['CodigoPais'])
                && self::$json[self::$event]['Generador']['IDOtro']['CodigoPais'] === 'ES'
                && isset(self::$json[self::$event]['Generador']['IDOtro']['IDType'])
                && self::$json[self::$event]['Generador']['IDOtro']['IDType'] !== '03') {
                Tools::log()->error('json-invalid-idtype-generador-required-when-generadopor-t');
                return false;
            }
            if (isset(self::$json[self::$event]['Generador']['IDOtro']['IDType'])
                && self::$json[self::$event]['Generador']['IDOtro']['IDType'] === '07') {
                Tools::log()->error('json-invalid-idtype-not-allowed-generador');
                return false;
            }
        }

        return true;
    }


    private static function generadoPor(): bool
    {
        // Si se informa este campo, deberá informarse la agrupación Generador
        if (isset(self::$json[self::$event]['GeneradoPor']) && !isset(self::$json[self::$event]['Generador'])) {
            Tools::log()->error('json-missing-group-generador');
            return false;
        }

        return true;
    }

    private static function idFactura(): bool
    {
        // El NIF del campo IDEmisorFacturaAnulada debe ser el mismo que el del campo NIF de la agrupación ObligadoEmision del bloque Cabecera
        // Esto se valída en el cron al momento de mandar el JSON
        return true;
    }
}