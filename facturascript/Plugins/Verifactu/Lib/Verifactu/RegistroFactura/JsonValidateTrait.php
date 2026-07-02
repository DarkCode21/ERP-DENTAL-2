<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroFactura;

use DateTime;
use FacturaScripts\Core\Lib\Vies;
use FacturaScripts\Core\Tools;

/**
 * Trait para validar el JSON de una factura en Verifactu.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait JsonValidateTrait
{
    private static function fechaHoraHusoGenRegistro(): bool
    {
        // Se validará que la FechaHoraHusoGenRegistro sea menor o igual que la fecha del sistema
        // de la AEAT, admitiéndose un margen de error. En caso de superar el umbral,
        // se devolverá un aviso de error (no generará rechazo)
        $currentDate = new DateTime();
        $jsonDate = new DateTime(self::$json[self::$event]['FechaHoraHusoGenRegistro']);
        if ($jsonDate > $currentDate) {
            Tools::log()->warning('json-invalid-fecha-hora-huso-gen-registro', [
                '%currentDate%' => $currentDate->format('Y-m-d H:i:s'),
                '%jsonDate%' => self::$json[self::$event]['FechaHoraHusoGenRegistro'],
            ]);
        }

        return true;
    }

    private static function huella(): bool
    {
        // La huella se añade desde el cron, por lo que no se valida aquí
        return true;
    }

    private static function huellaAnterior(): bool
    {
        // si no existe encadenamiento anterior, no se valida
        if (!isset(self::$json[self::$event]['Encadenamiento']['RegistroAnterior'])) {
            return true;
        }

        // Se validará que la huella del encadenamiento del registro anterior cumpla el formato de salida
        // del algoritmo SHA-256, siendo de 64 caracteres en hexadecimal y en mayúsculas. En caso
        // contrario se devolverá un aviso de error (no generará rechazo)
        if (!isset(self::$json[self::$event]['Encadenamiento']['RegistroAnterior']['Huella'])
            || !preg_match('/^[A-F0-9]{64}$/', self::$json[self::$event]['Encadenamiento']['RegistroAnterior']['Huella'])) {
            Tools::log()->warning('json-invalid-huella-anterior-format');
        }

        return true;
    }

    private static function sistemaInformatico(): bool
    {
        // Si se cumplimenta NIF, no deberá existir la agrupación IDOtro y viceversa,
        // pero es obligatorio que se cumplimente uno de los dos
        if (isset(self::$json[self::$event]['SistemaInformatico']['NIF']) && isset(self::$json[self::$event]['SistemaInformatico']['IDOtro'])) {
            Tools::log()->error('json-invalid-nif-and-idotro-both-defined-sistema-informatico');
            return false;
        } elseif (!isset(self::$json[self::$event]['SistemaInformatico']['NIF']) && empty(self::$json[self::$event]['SistemaInformatico']['IDOtro'])) {
            Tools::log()->error('json-missing-nif-or-idotro-required-sistema-informatico');
            return false;
        }

        // Si el campo IDType = “02” (NIF-IVA), no será exigible el campo CodigoPais
        if (isset(self::$json[self::$event]['SistemaInformatico']['IDOtro']['IDType'])
            && self::$json[self::$event]['SistemaInformatico']['IDOtro']['IDType'] === '02'
            && isset(self::$json[self::$event]['SistemaInformatico']['IDOtro']['CodigoPais'])) {
            Tools::log()->error('json-invalid-codigo-pais-not-required-for-nif-iva-sistema-informatico');
            return false;
        }

        // Cuando la persona o entidad productora del sistema informático se identifique a través de la
        // agrupación IDOtro e IDType sea “02”, se validará que el campo identificador se ajuste a la
        // estructura de NIF-IVA de alguno de los Estados Miembros y debe estar identificado
        if (isset(self::$json[self::$event]['SistemaInformatico']['IDOtro']['IDType'])
            && self::$json[self::$event]['SistemaInformatico']['IDOtro']['IDType'] === '02'
            && isset(self::$json[self::$event]['SistemaInformatico']['IDOtro']['ID'])
            && !in_array(self::$json[self::$event]['SistemaInformatico']['IDOtro']['CodigoPais'], Vies::EU_COUNTRIES)) {
            Tools::log()->error('json-invalid-codigo-pais-not-in-eu-sistema-informatico');
            return false;
        }

        // Si se identifica a través de la agrupación IDOtro y CodigoPais sea "ES",
        // se validará que el campo IDType sea “03”
        if (isset(self::$json[self::$event]['SistemaInformatico']['IDOtro']['CodigoPais'])
            && self::$json[self::$event]['SistemaInformatico']['IDOtro']['CodigoPais'] === 'ES'
            && isset(self::$json[self::$event]['SistemaInformatico']['IDOtro']['IDType'])
            && self::$json[self::$event]['SistemaInformatico']['IDOtro']['IDType'] !== '03') {
            Tools::log()->error('json-invalid-idtype-for-codigo-pais-es-sistema-informatico');
            return false;
        }

        // No se admite el tipo de identificación IDType “07” (“No censado”)
        if (isset(self::$json[self::$event]['SistemaInformatico']['IDOtro']['IDType'])
            && self::$json[self::$event]['SistemaInformatico']['IDOtro']['IDType'] === '07') {
            Tools::log()->error('json-invalid-idtype-07-sistema-informatico');
            return false;
        }

        // El campo IdSistemaInformatico deberá tener rellenas siempre las dos posiciones, cada una
        // de las cuales deberá ser una letra mayúscula, excepto la Ñ, o un dígito numérico
        if (isset(self::$json[self::$event]['SistemaInformatico']['IdSistemaInformatico'])
            && !preg_match('/^[A-Z0-9]{2}$/', self::$json[self::$event]['SistemaInformatico']['IdSistemaInformatico'])) {
            Tools::log()->error('json-invalid-id-sistema-informatico', [
                '%id%' => self::$json[self::$event]['SistemaInformatico']['IdSistemaInformatico'],
            ]);
            return false;
        }

        // El campo NombreSistemaInformatico es obligatorio y debe tener contenido
        if (isset(self::$json[self::$event]['SistemaInformatico']['NombreSistemaInformatico'])
            && empty(self::$json[self::$event]['SistemaInformatico']['NombreSistemaInformatico'])) {
            Tools::log()->error('json-missing-nombre-sistema-informatico-required');
            return false;
        }

        // El campo TipoUsoPosibleSoloVerifactu es obligatorio y debe tener contenido
        if (isset(self::$json[self::$event]['SistemaInformatico']['TipoUsoPosibleSoloVerifactu'])
            && empty(self::$json[self::$event]['SistemaInformatico']['TipoUsoPosibleSoloVerifactu'])) {
            Tools::log()->error('json-missing-tipo-uso-posible-solo-verifactu-required');
            return false;
        }

        // El campo TipoUsoPosibleMultiOT es obligatorio y debe tener contenido
        if (isset(self::$json[self::$event]['SistemaInformatico']['TipoUsoPosibleMultiOT'])
            && empty(self::$json[self::$event]['SistemaInformatico']['TipoUsoPosibleMultiOT'])) {
            Tools::log()->error('json-missing-tipo-uso-posible-multi-ot-required');
            return false;
        }

        return true;
    }
}