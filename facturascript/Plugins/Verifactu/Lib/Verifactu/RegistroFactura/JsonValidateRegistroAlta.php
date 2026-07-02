<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroFactura;

use DateTime;
use FacturaScripts\Core\Lib\Vies;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroFactura\JsonValidateTrait;

/**
 * Clase para validar un JSON de alta y subsanación en Verifactu.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class JsonValidateRegistroAlta
{
    use JsonValidateTrait;

    /** @var array */
    private static $json = [];

    /** @var string */
    private static $event = 'RegistroAlta';

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

        if (!self::idFactura()) {
            return false;
        } elseif (!self::rechazoPrevio()) {
            return false;
        } elseif (!self::tipoRectificativa()) {
            return false;
        } elseif (!self::facturasRectificadas()) {
            return false;
        } elseif (!self::facturasSustituidas()) {
            return false;
        } elseif (!self::importeRectificacion()) {
            return false;
        } elseif (!self::fechaOperacion()) {
            return false;
        } elseif (!self::facturaSimplificadaArt7273()) {
            return false;
        } elseif (!self::facturaSinIdentifDestinatarioArt61d()) {
            return false;
        } elseif (!self::macrodato()) {
            return false;
        } elseif (!self::emitidaPorTerceroODestinatario()) {
            return false;
        } elseif (!self::tercero()) {
            return false;
        } elseif (!self::destinatarios()) {
            return false;
        } elseif (!self::cupon()) {
            return false;
        } elseif (!self::desglose()) {
            return false;
        } elseif (!self::cuotaTotal()) {
            return false;
        } elseif (!self::importeTotal()) {
            return false;
        } elseif (!self::huellaAnterior()) {
            return false;
        } elseif (!self::sistemaInformatico()) {
            return false;
        } elseif (!self::fechaHoraHusoGenRegistro()) {
            return false;
        } elseif (!self::numRegistroAcuerdoFacturacion()) {
            return false;
        } elseif (!self::idAcuerdoSistemaInformatico()) {
            return false;
        } elseif (!self::huella()) {
            return false;
        }

        return true;
    }

    private static function cuotaTotal(): bool
    {
        // Se validará que sea igual a Ʃ (CuotaRepercutida + CuotaRecargoEquivalencia) de todas las
        // líneas de detalle de desglose. En caso contrario se devolverá un aviso de error (no generará rechazo),
        // admitiéndose un margen de error de +/- 10,00 euros
        $cuotaTotal = 0.0;
        foreach (self::$json[self::$event]['Desglose'] as $desglose) {
            if (isset($desglose['CuotaRepercutida'])) {
                $cuotaTotal += (float)$desglose['CuotaRepercutida'];
            }
            if (isset($desglose['CuotaRecargoEquivalencia'])) {
                $cuotaTotal += (float)$desglose['CuotaRecargoEquivalencia'];
            }
        }

        if (abs($cuotaTotal - (float)self::$json[self::$event]['CuotaTotal']) > 10.0) {
            Tools::log()->warning('json-invalid-cuota-total', [
                '%calculated%' => $cuotaTotal,
                '%expected%' => self::$json[self::$event]['CuotaTotal'],
            ]);
        }

        return true;
    }

    private static function cupon(): bool
    {
        // Sólo se podrá rellenar con “S” (no es obligatorio) si TipoFactura = ”R5” o “R1”
        if (isset(self::$json[self::$event]['Cupon']) && self::$json[self::$event]['Cupon'] === 'S') {
            if (!in_array(self::$json[self::$event]['TipoFactura'], ['R5', 'R1'])) {
                Tools::log()->error('json-invalid-cupon-only-with-r5-r1');
                return false;
            }
        }

        return true;
    }

    private static function desglose(): bool
    {
        return JsonValidateDesglose::validate(self::$json[self::$event]);
    }

    private static function destinatarios(): bool
    {
        // si existe y hay más de 1000, error
        if (isset(self::$json[self::$event]['Destinatarios'])
            && count(self::$json[self::$event]['Destinatarios']) > 1000) {
            Tools::log()->error('json-invalid-destinatarios-more-than-1000');
            return false;
        }

        // Si TipoFactura es “F1”, “F3”, “R1”, “R2”, “R3” o “R4”,
        // la agrupación Destinatarios tiene que estar cumplimentada, con al menos un destinatario
        if (in_array(self::$json[self::$event]['TipoFactura'], ['F1', 'F3', 'R1', 'R2', 'R3', 'R4'])
            && (!isset(self::$json[self::$event]['Destinatarios']) || empty(self::$json[self::$event]['Destinatarios']))) {
            Tools::log()->error('json-missing-destinatarios-required-for-f1-f3-r');
            return false;
        }

        // Si TipoFactura es “F2” o “R5”, la agrupación Destinatarios no puede estar cumplimentada
        if (in_array(self::$json[self::$event]['TipoFactura'], ['F2', 'R5'])
            && isset(self::$json[self::$event]['Destinatarios']) && !empty(self::$json[self::$event]['Destinatarios'])) {
            Tools::log()->error('json-invalid-destinatarios-not-allowed-for-f2-r5');
            return false;
        }

        // recorremos los destinatarios y validamos cada uno
        foreach (self::$json[self::$event]['Destinatarios'] ?? [] as $destinatario) {
            // Si se cumplimenta NIF, no deberá existir la agrupación IDOtro y viceversa,
            // pero es obligatorio que se cumplimente uno de los dos
            if (isset($destinatario['NIF']) && isset($destinatario['IDOtro'])) {
                Tools::log()->error('json-invalid-nif-and-idotro-both-defined-destinatarios');
                return false;
            } elseif (!isset($destinatario['NIF']) && empty($destinatario['IDOtro'])) {
                Tools::log()->error('json-missing-nif-or-idotro-required-destinatarios');
                return false;
            }

            // Si el campo IDType = “07” (No censado), el campo CodigoPais debe ser “ES”
            if (isset($destinatario['IDOtro']['IDType']) && $destinatario['IDOtro']['IDType'] === '07'
                && (!isset($destinatario['IDOtro']['CodigoPais']) || $destinatario['IDOtro']['CodigoPais'] !== 'ES')) {
                Tools::log()->error('json-invalid-codigo-pais-not-es-for-idtype-07-destinatarios');
                return false;
            }

            // Cuando uno o varios destinatarios se identifiquen a través de la agrupación IDOtro e IDType sea “02”,
            // se validará que el campo identificador se ajuste a la estructura de NIF-IVA de
            // alguno de los Estados Miembros y debe estar identificado
            if (isset($destinatario['IDOtro']['IDType']) && $destinatario['IDOtro']['IDType'] === '02'
                && isset($destinatario['IDOtro']['ID'])
                && !in_array($destinatario['IDOtro']['CodigoPais'], Vies::EU_COUNTRIES)) {
                Tools::log()->error('json-invalid-codigo-pais-not-in-eu-destinatarios');
                return false;
            }

            // Cuando uno o varios destinatarios se identifiquen a través de la agrupación IDOtro y
            // CodigoPais sea "ES", se validará que el campo IDType sea “03” o “07”
            if (isset($destinatario['IDOtro']['CodigoPais']) && $destinatario['IDOtro']['CodigoPais'] === 'ES'
                && isset($destinatario['IDOtro']['IDType']) && !in_array($destinatario['IDOtro']['IDType'], ['03', '07'])) {
                Tools::log()->error('json-invalid-idtype-for-codigo-pais-es-destinatarios');
                return false;
            }

            // Cuando se identifique a través del bloque “IDOtro” y IDType sea “02”, se validará que
            // TipoFactura sea “F1”, “F3”, “R1”, “R2”, “R3” ó “R4”
            if (isset($destinatario['IDOtro']['IDType']) && $destinatario['IDOtro']['IDType'] === '02'
                && !in_array(self::$json[self::$event]['TipoFactura'], ['F1', 'F3', 'R1', 'R2', 'R3', 'R4'])) {
                Tools::log()->error('json-invalid-idtype-02-only-with-f1-f3-r');
                return false;
            }
        }

        return true;
    }

    private static function emitidaPorTerceroODestinatario(): bool
    {
        // Si es igual a “T”, el bloque Tercero será de cumplimentación obligatoria
        if (isset(self::$json[self::$event]['EmitidaPorTerceroODestinatario']) && self::$json[self::$event]['EmitidaPorTerceroODestinatario'] === 'T') {
            if (!isset(self::$json[self::$event]['Tercero']) || empty(self::$json[self::$event]['Tercero'])) {
                Tools::log()->error('json-missing-tercero-required-for-t');
                return false;
            }
        }

        // Si es igual a “D”, el bloque Destinatarios será de cumplimentación obligatoria
        if (isset(self::$json[self::$event]['EmitidaPorTerceroODestinatario']) && self::$json[self::$event]['EmitidaPorTerceroODestinatario'] === 'D') {
            if (!isset(self::$json[self::$event]['Destinatarios']) || empty(self::$json[self::$event]['Destinatarios'])) {
                Tools::log()->error('json-missing-destinatarios-required-for-d');
                return false;
            }
        }

        return true;
    }

    private static function facturasRectificadas(): bool
    {
        // si no está el campo TipoRectificativa, terminamos
        if (!isset(self::$json[self::$event]['TipoRectificativa'])) {
            return true;
        }

        // Solo podrá incluirse el campo FacturasRectificadas (no es obligatoria)
        // si TipoFactura es igual a “R1”, “R2”, “R3”, “R4” o “R5”
        if (isset(self::$json[self::$event]['FacturasRectificadas'])
            && !in_array(self::$json[self::$event]['TipoFactura'], ['R1', 'R2', 'R3', 'R4', 'R5'])) {
            Tools::log()->error('json-invalid-facturas-rectificadas-only-with-r');
            return false;
        }

        return true;
    }

    private static function facturaSimplificadaArt7273(): bool
    {
        // si el campo FacturaSimplificadaArt7273 no está definido, terminamos
        if (!isset(self::$json[self::$event]['FacturaSimplificadaArt7273'])) {
            return true;
        }

        // Sólo se podrá rellenar con “S” si TipoFactura=“F1” o “F3” o “R1” o “R2” o “R3” o “R4”
        if (self::$json[self::$event]['FacturaSimplificadaArt7273'] === 'S'
            && !in_array(self::$json[self::$event]['TipoFactura'], ['F1', 'F3', 'R1', 'R2', 'R3', 'R4'])) {
            Tools::log()->error('json-invalid-factura-simplificada-art-72-73-only-with-f1-f3-r');
            return false;
        }

        return true;
    }

    private static function facturaSinIdentifDestinatarioArt61d(): bool
    {
        // si el campo FacturaSinIdentifDestinatarioArt61d no está definido, terminamos
        if (!isset(self::$json[self::$event]['FacturaSinIdentifDestinatarioArt61d'])) {
            return true;
        }

        // Sólo se podrá rellenar con “S” si TipoFactura=”F2” o “R5”
        if (self::$json[self::$event]['FacturaSinIdentifDestinatarioArt61d'] === 'S'
            && !in_array(self::$json[self::$event]['TipoFactura'], ['F2', 'R5'])) {
            Tools::log()->error('json-invalid-factura-sin-identif-destinatario-art-61d-only-with-f2-r5');
            return false;
        }

        return true;
    }

    private static function facturasSustituidas(): bool
    {
        // si no está el campo TipoRectificativa, terminamos
        if (!isset(self::$json[self::$event]['TipoRectificativa'])) {
            return true;
        }

        // Solo podrá incluirse el campo FacturasSustituidas (no es obligatoria) cuando el campo TipoFactura="F3"
        if (isset(self::$json[self::$event]['FacturasSustituidas'])
            && self::$json[self::$event]['TipoFactura'] !== 'F3') {
            Tools::log()->error('json-invalid-facturas-sustituidas-only-with-f3');
            return false;
        }

        return true;
    }

    private static function fechaOperacion(): bool
    {
        // si no existe el campo FechaOperacion, no se valida
        if (!isset(self::$json[self::$event]['FechaOperacion'])) {
            return true;
        }

        // La FechaOperacion no debe ser inferior a la fecha actual menos veinte años
        // y no debe ser superior al año siguiente de la fecha actual
        $currentDate = new DateTime();
        $minDate = (clone $currentDate)->modify('-20 years');
        $maxDate = (clone $currentDate)->modify('+1 year');
        $operationDate = new DateTime(self::$json[self::$event]['FechaOperacion']);
        if ($operationDate < $minDate || $operationDate > $maxDate) {
            Tools::log()->error('json-invalid-operation-date', [
                '%minDate%' => $minDate->format('Y-m-d'),
                '%maxDate%' => $maxDate->format('Y-m-d'),
            ]);
            return false;
        }

        // Si Impuesto = “01” (IVA), “03” (IGIC) o no se cumplimenta (considerándose “01” - IVA), el
        // campo FechaOperacion solo podrá ser superior a la fecha actual, si ClaveRegimen= "14" o "15”
        foreach (self::$json[self::$event]['Desglose'] as $desglose) {
            if (in_array($desglose['Impuesto'], ['01', '03']) && in_array($desglose['ClaveRegimen'], ['14', '15'])) {
                if ($operationDate < $currentDate) {
                    Tools::log()->error('json-invalid-operation-date-current');
                    return false;
                }
            }
        }

        return true;
    }

    private static function idAcuerdoSistemaInformatico(): bool
    {
        // Si se informa, debe existir el IdAcuerdoSistemaInformatico en la AEAT
        if (isset(self::$json[self::$event]['IdAcuerdoSistemaInformatico'])
            && !empty(self::$json[self::$event]['IdAcuerdoSistemaInformatico'])) {
            // Aquí se debería validar con la AEAT si el ID de acuerdo existe
            // Por ahora, solo se registra un aviso
            Tools::log()->warning('json-id-acuerdo-sistema-informatico-not-validated', [
                '%id%' => self::$json[self::$event]['IdAcuerdoSistemaInformatico'],
            ]);
        }

        return true;
    }

    private static function idFactura(): bool
    {
        // El NIF del campo IDEmisorFactura debe ser el mismo que el del campo NIF de la agrupación ObligadoEmision del bloque Cabecera
        // Esto se valída en el cron al momento de mandar el JSON

        // La FechaExpedicionFactura no podrá ser superior a la fecha actual ni inferior al 28/10/2024
        $currentDate = new DateTime();
        $minDate = new DateTime('2024-10-28');
        $invoiceDate = new DateTime(self::$json[self::$event]['IDFactura']['FechaExpedicionFactura']);
        if ($invoiceDate > $currentDate || $invoiceDate < $minDate) {
            Tools::log()->error('json-invalid-invoice-date');
            return false;
        }

        // NumSerieFactura solo puede contener caracteres ASCII del 32 a 126 (caracteres imprimibles)
        if (!preg_match('/^[\x20-\x7E]*$/', self::$json[self::$event]['IDFactura']['NumSerieFactura'])) {
            Tools::log()->error('json-invalid-invoice-number');
            return false;
        }

        // Si Impuesto = “01” (IVA), “03” (IGIC) o no se cumplimenta (considerándose “01” - IVA), la
        // FechaExpedicionFactura solo puede ser anterior a la FechaOperacion, si ClaveRegimen=
        // "14" o "15”.
        foreach (self::$json[self::$event]['Desglose'] as $desglose) {
            // si no hay FechaOperacion, no se valida
            if (!isset(self::$json[self::$event]['FechaOperacion'])) {
                continue;
            }

            if (in_array($desglose['Impuesto'], ['01', '03']) && in_array($desglose['ClaveRegimen'], ['14', '15'])) {
                $fechaOperacion = new DateTime(self::$json[self::$event]['FechaOperacion']);
                if ($invoiceDate >= $fechaOperacion) {
                    Tools::log()->error('json-invalid-invoice-date-operation');
                    return false;
                }
            }
        }

        return true;
    }

    private static function importeRectificacion(): bool
    {
        // Solo deberá incluirse el campo ImporteRectificacion si el campo TipoRectificativa = "S"
        if (isset(self::$json[self::$event]['TipoRectificativa']) && self::$json[self::$event]['TipoRectificativa'] === 'S') {
            if (!isset(self::$json[self::$event]['ImporteRectificacion']) || empty(self::$json[self::$event]['ImporteRectificacion'])) {
                Tools::log()->error('json-missing-importe-rectificacion-only-with-s');
                return false;
            }
        }

        // Obligatorio si TipoRectificativa = “S”
        if (isset(self::$json[self::$event]['TipoRectificativa']) && self::$json[self::$event]['TipoRectificativa'] === 'S'
            && (!isset(self::$json[self::$event]['ImporteRectificacion']) || empty(self::$json[self::$event]['ImporteRectificacion']))) {
            Tools::log()->error('json-missing-importe-rectificacion-required-for-s');
            return false;
        }

        return true;
    }

    private static function importeTotal(): bool
    {
        // Se validará que sea igual a Ʃ (BaseImponibleOimporteNoSujeto + CuotaRepercutida + CuotaRecargoEquivalencia)
        // de todas las líneas de detalle de desglose. En caso contrario se devolverá un aviso de error
        // (no generará rechazo), admitiéndose un margen de error de +/- 10,00 euros
        $validate = true;
        $importeTotal = 0.0;
        foreach (self::$json[self::$event]['Desglose'] as $desglose) {
            if (isset($desglose['BaseImponibleOimporteNoSujeto'])) {
                $importeTotal += (float)$desglose['BaseImponibleOimporteNoSujeto'];
            }
            if (isset($desglose['CuotaRepercutida'])) {
                $importeTotal += (float)$desglose['CuotaRepercutida'];
            }
            if (isset($desglose['CuotaRecargoEquivalencia'])) {
                $importeTotal += (float)$desglose['CuotaRecargoEquivalencia'];
            }
            if (isset($desglose['ClaveRegimen']) && in_array($desglose['ClaveRegimen'], ['03', '05', '06', '08', '09'])) {
                $validate = false;
            }
        }

        // Esta validación no se aplicará cuando ClaveRegimen sea “03”, “05”, “06”, “08” o “09”
        if ($validate) {
            if (abs($importeTotal - (float)self::$json[self::$event]['ImporteTotal']) > 10.0) {
                Tools::log()->warning('json-invalid-importe-total', [
                    '%calculated%' => $importeTotal,
                    '%expected%' => self::$json[self::$event]['ImporteTotal'],
                ]);
            }
        }

        return true;
    }

    private static function macrodato(): bool
    {
        // Campo obligatorio si ImporteTotal >= |100.000.000,00| (valor absoluto)
        if (abs(self::$json[self::$event]['ImporteTotal']) >= 100000000) {
            if (!isset(self::$json[self::$event]['Macrodato']) || empty(self::$json[self::$event]['Macrodato'])) {
                Tools::log()->error('json-missing-macrodato-required-for-large-amount');
                return false;
            }
        }

        return true;
    }

    private static function numRegistroAcuerdoFacturacion(): bool
    {
        // Si se informa, debe existir el NumRegistroAcuerdoFacturacion en la AEAT
        if (isset(self::$json[self::$event]['NumRegistroAcuerdoFacturacion'])
            && !empty(self::$json[self::$event]['NumRegistroAcuerdoFacturacion'])) {
            // Aquí se debería validar con la AEAT si el número de registro existe
            // Por ahora, solo se registra un aviso
            Tools::log()->warning('json-num-registro-acuerdo-facturacion-not-validated', [
                '%num%' => self::$json[self::$event]['NumRegistroAcuerdoFacturacion'],
            ]);
        }

        return true;
    }

    private static function rechazoPrevio(): bool
    {
        // Solo podrá incluirse el campo RechazoPrevio si se ha informado el campo Subsanacion y tiene el valor “S”
        if (isset(self::$json[self::$event]['RechazoPrevio']) && (!isset(self::$json[self::$event]['Subsanacion']) || self::$json[self::$event]['Subsanacion'] !== 'S')) {
            Tools::log()->error('json-missing-rechazo-previo-only-with-subsanacion');
            return false;
        }

        // No podrá informarse el campo RechazoPrevio con valor “S” si no se informa el campo Subsanación
        // o este tiene el valor “N”
        if (isset(self::$json[self::$event]['RechazoPrevio']) && self::$json[self::$event]['RechazoPrevio'] === 'S'
            && (!isset(self::$json[self::$event]['Subsanacion']) || self::$json[self::$event]['Subsanacion'] !== 'S')) {
            Tools::log()->error('json-invalid-rechazo-previo-only-with-subsanacion-s');
            return false;
        }

        return true;
    }

    private static function tipoRectificativa(): bool
    {
        // Solo podrá incluirse el campo TipoRectificativa este campo si el valor del campo TipoFactura es igual a “R1”, “R2”, “R3”, “R4” o “R5”
        if (isset(self::$json[self::$event]['TipoFactura']) && in_array(self::$json[self::$event]['TipoFactura'], ['R1', 'R2', 'R3', 'R4', 'R5'])) {
            if (!isset(self::$json[self::$event]['TipoRectificativa']) || empty(self::$json[self::$event]['TipoRectificativa'])) {
                Tools::log()->error('json-missing-tipo-rectificativa-only-with-r');
                return false;
            }
        }

        // El campo TipoRectificativa es obligatorio si TipoFactura es igual a “R1”, “R2”, “R3”, “R4” o “R5”
        if (isset(self::$json[self::$event]['TipoFactura']) && in_array(self::$json[self::$event]['TipoFactura'], ['R1', 'R2', 'R3', 'R4', 'R5'])
            && (!isset(self::$json[self::$event]['TipoRectificativa']) || empty(self::$json[self::$event]['TipoRectificativa']))) {
            Tools::log()->error('json-missing-tipo-rectificativa-required-for-r');
            return false;
        }

        return true;
    }

    private static function tercero(): bool
    {
        // si no existe el campo tercero, no se valida
        if (!isset(self::$json[self::$event]['Tercero'])) {
            return true;
        }

        // Solo podrá cumplimentarse si EmitidaPorTerceroODestinatario es “T”
        if (isset(self::$json[self::$event]['EmitidaPorTerceroODestinatario']) && self::$json[self::$event]['EmitidaPorTerceroODestinatario'] === 'T') {
            if (!isset(self::$json[self::$event]['Tercero']) || empty(self::$json[self::$event]['Tercero'])) {
                Tools::log()->error('json-missing-tercero-required-for-t-third');
                return false;
            }
        }

        // Si se identifica mediante NIF, el NIF debe estar identificado y ser distinto del NIF del campo
        // IDEmisorFactura de la agrupación IDFactura
        if (isset(self::$json[self::$event]['Tercero']['NIF']) && !empty(self::$json[self::$event]['Tercero']['NIF'])) {
            if (self::$json[self::$event]['Tercero']['NIF'] === self::$json[self::$event]['IDFactura']['IDEmisorFactura']) {
                Tools::log()->error('json-invalid-nif-tercero-same-as-emisor-third');
                return false;
            }
        }

        // Si se cumplimenta NIF, no deberá existir la agrupación IDOtro y viceversa, pero es obligatorio
        // que se cumplimente uno de los dos
        if (isset(self::$json[self::$event]['Tercero']['NIF']) && isset(self::$json[self::$event]['Tercero']['IDOtro'])) {
            Tools::log()->error('json-invalid-nif-and-idotro-both-defined-third');
            return false;
        } elseif (!isset(self::$json[self::$event]['Tercero']['NIF']) && empty(self::$json[self::$event]['Tercero']['IDOtro'])) {
            Tools::log()->error('json-missing-nif-or-idotro-required-third');
            return false;
        }

        // Si el campo IDType = “02” (NIF-IVA), no será exigible el campo CodigoPais
        if (isset(self::$json[self::$event]['Tercero']['IDOtro']['IDType'])
            && self::$json[self::$event]['Tercero']['IDOtro']['IDType'] === '02'
            && isset(self::$json[self::$event]['Tercero']['IDOtro']['CodigoPais'])) {
            Tools::log()->error('json-invalid-codigo-pais-not-required-for-nif-iva-third');
            return false;
        }

        // Cuando el tercero se identifique a través de la agrupación IDOtro e IDType sea “02”,
        // se validará que el campo identificador ID se ajuste a la estructura de NIF-IVA de alguno de los
        // Estados Miembros y debe estar identificado
        if (isset(self::$json[self::$event]['Tercero']['IDOtro']['IDType'])
            && self::$json[self::$event]['Tercero']['IDOtro']['IDType'] === '02'
            && isset(self::$json[self::$event]['Tercero']['IDOtro']['ID'])
            && !in_array(self::$json[self::$event]['Tercero']['IDOtro']['CodigoPais'], Vies::EU_COUNTRIES)) {
            Tools::log()->error('json-invalid-codigo-pais-not-in-eu-third');
            return false;
        }

        // Si se identifica a través de la agrupación IDOtro y CodigoPais sea "ES",
        // se validará que el campo IDType sea “03”
        if (isset(self::$json[self::$event]['Tercero']['IDOtro']['CodigoPais'])
            && self::$json[self::$event]['Tercero']['IDOtro']['CodigoPais'] === 'ES'
            && isset(self::$json[self::$event]['Tercero']['IDOtro']['IDType'])
            && self::$json[self::$event]['Tercero']['IDOtro']['IDType'] !== '03') {
            Tools::log()->error('json-invalid-idtype-for-codigo-pais-es-third');
            return false;
        }

        // No se admite el tipo de identificación IDType “07”
        if (isset(self::$json[self::$event]['Tercero']['IDOtro']['IDType'])
            && self::$json[self::$event]['Tercero']['IDOtro']['IDType'] === '07') {
            Tools::log()->error('json-invalid-idtype-07-third');
            return false;
        }

        return true;
    }
}