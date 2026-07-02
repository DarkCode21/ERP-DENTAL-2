<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroFactura;

use DateTime;
use FacturaScripts\Core\Tools;

/**
 * Clase para validar el desglose de una factura en formato JSON para Verifactu.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class JsonValidateDesglose
{
    /** @var array */
    private static $json;

    public static function validate(array $json): bool
    {
        self::$json = $json;
        if (empty(self::$json)) {
            Tools::log()->error('json-empty');
            return false;
        }

        if (empty(self::$json['Desglose'])) {
            Tools::log()->error('json-desgloses-empty');
            return false;
        }

        foreach (self::$json['Desglose'] as $desglose) {
            if (!self::tipoImpositivo($desglose)) {
                return false;
            } elseif (!self::baseImponibleACoste($desglose)) {
                return false;
            } elseif (!self::tipoRecargoEquivalencia($desglose)) {
                return false;
            } elseif (!self::calificacionOperacion($desglose)) {
                return false;
            } elseif (!self::operacionExenta($desglose)) {
                return false;
            } elseif (!self::claveRegimen($desglose)) {
                return false;
            } elseif (!self::claveRegimen02($desglose)) {
                return false;
            } elseif (!self::claveRegimen03($desglose)) {
                return false;
            } elseif (!self::claveRegimen04($desglose)) {
                return false;
            } elseif (!self::claveRegimen06($desglose)) {
                return false;
            } elseif (!self::claveRegimen07($desglose)) {
                return false;
            } elseif (!self::claveRegimen08($desglose)) {
                return false;
            } elseif (!self::claveRegimen10($desglose)) {
                return false;
            } elseif (!self::claveRegimen11($desglose)) {
                return false;
            } elseif (!self::claveRegimen14($desglose)) {
                return false;
            } elseif (!self::claveRegimen20($desglose)) {
                return false;
            } elseif (!self::claveRegimen21($desglose)) {
                return false;
            } elseif (!self::cuotaRepercutida($desglose)) {
                return false;
            } elseif (!self::simplificadas($desglose)) {
                return false;
            }
        }

        return true;
    }

    private static function baseImponibleACoste(array $desglose): bool
    {
        // El campo BaseImponibleACoste solo puede estar cumplimentado si la
        // ClaveRegimen es = “06” o Impuesto = “02” (IPSI) o Impuesto = “05” (Otros)
        if (isset($desglose['BaseImponibleACoste'])
            && ($desglose['ClaveRegimen'] !== '06' || (!in_array($desglose['Impuesto'], ['02', '06'])))) {
            Tools::log()->error('json-desglose-base-imponible-acoste-invalid', [
                '%ClaveRegimen%' => $desglose['ClaveRegimen'],
                '%Impuesto%' => $desglose['Impuesto'],
            ]);
            return false;
        }

        return true;
    }

    private static function calificacionOperacion(array $desglose): bool
    {
        // Si CalificacionOperacion es “S2”, TipoFactura solo puede ser “F1”, “F3” , “R1”, “R2” , “R4”
        if (isset($desglose['CalificacionOperacion']) && $desglose['CalificacionOperacion'] === 'S2') {
            if (!in_array($desglose['TipoFactura'], ['F1', 'F3', 'R1', 'R2', 'R4'])) {
                Tools::log()->error('json-desglose-calificacion-operacion-s2-invalid', [
                    '%TipoFactura%' => $desglose['TipoFactura'],
                ]);
                return false;
            }
        }

        // Cuando CalificacionOperacion sea “S2”
        if (isset($desglose['CalificacionOperacion']) && $desglose['CalificacionOperacion'] === 'S2') {
            // TipoImpositivo = 0. (No se admite que vaya vacío o que el campo no exista)
            if (!isset($desglose['TipoImpositivo'])) {
                Tools::log()->error('json-desglose-calificacion-operacion-s2-tipo-impositivo-invalid');
                return false;
            }

            // CuotaRepercutida = 0. (No se admite que vaya vacío o que el campo no exista)
            if (!isset($desglose['CuotaRepercutida'])) {
                Tools::log()->error('json-desglose-calificacion-operacion-s2-cuota-repercutida-invalid');
                return false;
            }
        }

        // Si CalificacionOperacion es = “N1/N2” e Impuesto = ”01” (IVA) o no se
        // cumplimenta (considerándose “01” - IVA), no se puede informar ninguno de estos campos
        // 1 - TipoImpositivo, CuotaRepercutida
        // 2 - TipoRecargoEquivalencia, CuotaRecargoEquivalencia
        if (isset($desglose['CalificacionOperacion'])
            && in_array($desglose['CalificacionOperacion'], ['N1', 'N2'])
            && ($desglose['Impuesto'] === '01')) {
            if (isset($desglose['TipoImpositivo']) || isset($desglose['CuotaRepercutida'])) {
                Tools::log()->error('json-desglose-calificacion-operacion-n1-n2-tipo-impositivo-cuota-repercutida-invalid');
                return false;
            }
            if (isset($desglose['TipoRecargoEquivalencia']) || isset($desglose['CuotaRecargoEquivalencia'])) {
                Tools::log()->error('json-desglose-calificacion-operacion-n1-n2-invalid');
                return false;
            }
        }

        return true;
    }

    private static function claveRegimen(array $desglose): bool
    {
        // Solo podrá incluirse el campo ClaveRegimen si Impuesto = “01” (IVA), “03” (IGIC) o no se cumplimenta
        // (considerándose “01” - IVA) y será obligatorio
        if (!isset($desglose['ClaveRegimen'])
            && in_array($desglose['Impuesto'], ['01', '03'])) {
            Tools::log()->error('json-desglose-clave-regimen-required', [
                '%Impuesto%' => $desglose['Impuesto'],
            ]);
            return false;
        }

        // Si Impuesto = “01” (IVA) o no se cumplimenta (considerándose “01” - IVA), el valor
        // de ClaveRegimen deberá estar cumplimentado y contenido en lista L8A
        if ($desglose['Impuesto'] === '01'
            && (!isset($desglose['ClaveRegimen']) || !in_array($desglose['ClaveRegimen'], ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '14', '15', '17', '18', '19', '20']))) {
            Tools::log()->error('json-desglose-clave-regimen-invalid');
            return false;
        }

        // Si Impuesto = “03” (IGIC), el valor de ClaveRegimen deberá estar cumplimentado
        // y contenido en lista L8B. Adicionalmente, puede contener el valor “20”
        // (Operaciones sujetas al IPSI)
        if ($desglose['Impuesto'] === '03'
            && (!isset($desglose['ClaveRegimen']) || !in_array($desglose['ClaveRegimen'], ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '14', '15', '17', '18', '19', '20']))) {
            Tools::log()->error('json-desglose-clave-regimen-igic-invalid');
            return false;
        }

        return true;
    }

    private static function claveRegimen02(array $desglose): bool
    {
        // Si Impuesto = “01” (IVA), “03” (IGIC) o no se cumplimenta (considerándose “01” - IVA), si
        // clave de ClaveRegimen es igual a “02”, solo puede estar cumplimentado OperacionExenta
        if (in_array($desglose['Impuesto'], ['01', '03'])
            && $desglose['ClaveRegimen'] === '02'
            && !isset($desglose['OperacionExenta'])) {
            Tools::log()->error('json-desglose-clave-regimen-02-operacion-exenta-invalid');
            return false;
        }

        return true;
    }

    private static function claveRegimen03(array $desglose): bool
    {
        // Si Impuesto = “01” (IVA), “03” (IGIC) o no se cumplimenta (considerándose “01” - IVA),
        // cuando ClaveRegimen sea igual a “03”, si se cumplimenta CalificacionOperacion, este
        // campo solo puede contener el valor “S1”

        /* Aclaración: ClaveRegimen “03” es compatible con operación exenta, ya que el artículo 137.
            Dos. 5ª de la Ley 37/1992, de 28 de diciembre, del Impuesto sobre el Valor Añadido
            contempla expresamente la posibilidad de aplicar exenciones en REBU (Régimen especial de
            bienes usados, objetos de arte, antigüedades y objetos de colección).
        */

        if (in_array($desglose['Impuesto'], ['01', '03'])
            && $desglose['ClaveRegimen'] === '03'
            && isset($desglose['CalificacionOperacion'])
            && $desglose['CalificacionOperacion'] !== 'S1') {
            Tools::log()->error('json-desglose-clave-regimen-03-calificacion-operacion-invalid');
            return false;
        }

        return true;
    }

    private static function claveRegimen04(array $desglose): bool
    {
        // Si Impuesto = “01” (IVA), “03” (IGIC) o no se cumplimenta (considerándose “01” - IVA),
        // si clave de ClaveRegimen es igual a “04”, CalificacionOperacion solo puede ser “S2”,
        // o bien OperacionExenta
        if (in_array($desglose['Impuesto'], ['01', '03'])
            && $desglose['ClaveRegimen'] === '04'
            && ((isset($desglose['CalificacionOperacion']) && $desglose['CalificacionOperacion'] !== 'S2')
                || !isset($desglose['OperacionExenta']))) {
            Tools::log()->error('json-desglose-clave-regimen-04-calificacion-operacion-invalid');
            return false;
        }

        return true;
    }

    private static function claveRegimen06(array $desglose): bool
    {
        // si el impuesto no es 01 o 03, terminamos
        if (!in_array($desglose['Impuesto'], ['01', '03'])) {
            return true;
        }

        // si ClaveRegimen no es 06, terminamos
        if ($desglose['ClaveRegimen'] !== '06') {
            return true;
        }

        // Se validará que TipoFactura sea distinto de “F2”, “F3”, “R5”
        if (in_array($desglose['TipoFactura'], ['F2', 'F3', 'R5'])) {
            Tools::log()->error('json-desglose-clave-regimen-06-tipo-factura-invalid', [
                '%TipoFactura%' => $desglose['TipoFactura'],
            ]);
            return false;
        }

        // Campo BaseImponibleACoste deberá estar cumplimentado
        if (!isset($desglose['BaseImponibleACoste'])) {
            Tools::log()->error('json-desglose-clave-regimen-06-base-imponible-acoste-required');
            return false;
        }

        return true;
    }

    private static function claveRegimen07(array $desglose): bool
    {
        // si el impuesto no es 01 o 03, terminamos
        if (!in_array($desglose['Impuesto'], ['01', '03'])) {
            return true;
        }

        // si ClaveRegimen no es 07, terminamos
        if ($desglose['ClaveRegimen'] !== '07') {
            return true;
        }

        // CalificacionOperacion no puede ser “S2”, “N1”, “N2”
        if (isset($desglose['CalificacionOperacion'])
            && in_array($desglose['CalificacionOperacion'], ['S2', 'N1', 'N2'])) {
            Tools::log()->error('json-desglose-clave-regimen-07-calificacion-operacion-invalid', [
                '%CalificacionOperacion%' => $desglose['CalificacionOperacion'],
            ]);
            return false;
        }

        // OperacionExenta no puede ser “E2”, “E3”, “E4” y “E5”
        if (isset($desglose['OperacionExenta'])
            && in_array($desglose['OperacionExenta'], ['E2', 'E3', 'E4', 'E5'])) {
            Tools::log()->error('json-desglose-clave-regimen-07-operacion-exenta-invalid', [
                '%OperacionExenta%' => $desglose['OperacionExenta'],
            ]);
            return false;
        }

        return true;
    }

    private static function claveRegimen08(array $desglose): bool
    {
        // si el impuesto no es 01 o 03, terminamos
        if (!in_array($desglose['Impuesto'], ['01', '03'])) {
            return true;
        }

        // si ClaveRegimen no es 08, terminamos
        if ($desglose['ClaveRegimen'] !== '08') {
            return true;
        }

        // CalificacionOperacion tiene que ser “N2” y siempre debe ir relleno
        if (!isset($desglose['CalificacionOperacion'])
            || $desglose['CalificacionOperacion'] !== 'N2') {
            Tools::log()->error('json-desglose-clave-regimen-08-calificacion-operacion-invalid');
            return false;
        }

        return true;
    }

    private static function claveRegimen10(array $desglose): bool
    {
        // si el impuesto no es 01 o 03, terminamos
        if (!in_array($desglose['Impuesto'], ['01', '03'])) {
            return true;
        }

        // si ClaveRegimen no es 10, terminamos
        if ($desglose['ClaveRegimen'] !== '10') {
            return true;
        }

        // CalificacionOperacion tiene que ser “N1” y siempre debe ir relleno
        if (!isset($desglose['CalificacionOperacion'])
            || $desglose['CalificacionOperacion'] !== 'N1') {
            Tools::log()->error('json-desglose-clave-regimen-10-calificacion-operacion-invalid');
            return false;
        }

        // TipoFactura tiene que ser “F1”
        if (!isset($desglose['TipoFactura']) || $desglose['TipoFactura'] !== 'F1') {
            Tools::log()->error('json-desglose-clave-regimen-10-tipo-factura-invalid');
            return false;
        }

        // Todos los destinatarios tienen que estar identificado mediante NIF
        foreach (self::$json['Destinatarios'] as $destinatario) {
            if (empty($destinatario['NIF'])) {
                Tools::log()->error('json-desglose-clave-regimen-10-destinatario-nif-required');
                return false;
            }
        }

        return true;
    }

    private static function claveRegimen11(array $desglose): bool
    {
        // si el impuesto no es 01, terminamos
        if ($desglose['Impuesto'] !== '01') {
            return true;
        }

        // si ClaveRegimen no es 11, terminamos
        if ($desglose['ClaveRegimen'] !== '11') {
            return true;
        }

        // únicamente se admitirá el TipoImpositivo = 21
        if (!isset($desglose['TipoImpositivo']) || $desglose['TipoImpositivo'] !== 21) {
            Tools::log()->error('json-desglose-clave-regimen-11-tipo-impositivo-invalid', [
                '%TipoImpositivo%' => $desglose['TipoImpositivo'],
            ]);
            return false;
        }

        return true;
    }

    private static function claveRegimen14(array $desglose): bool
    {
        // si el impuesto no es 01 o 03, terminamos
        if (!in_array($desglose['Impuesto'], ['01', '03'])) {
            return true;
        }

        // si ClaveRegimen no es 14, terminamos
        if ($desglose['ClaveRegimen'] !== '14') {
            return true;
        }

        // si no existe el campo FechaOperacion, avisamos
        if (!isset(self::$json['FechaOperacion'])) {
            Tools::log()->error('json-desglose-clave-regimen-14-fecha-operacion-required');
            return false;
        }

        // FechaOperacion debe ser posterior a fecha de expedición
        $fechaOperacion = new DateTime();
        $fechaOperacion->setTimestamp(strtotime(self::$json['FechaOperacion']));
        $fechaExpedicion = new DateTime();
        $fechaExpedicion->setTimestamp(strtotime(self::$json['IDFactura']['FechaExpedicionFactura']));
        if ($fechaOperacion <= $fechaExpedicion) {
            Tools::log()->error('json-desglose-clave-regimen-14-fecha-operacion-invalid', [
                '%FechaOperacion%' => self::$json['FechaOperacion'],
                '%FechaExpedicion%' => self::$json['IDFactura']['FechaExpedicionFactura'],
            ]);
            return false;
        }

        // Todos los destinatarios tienen que estar identificados mediante NIF y comenzar por “P”,”Q”,”S” o “V”
        foreach (self::$json['Destinatarios'] as $destinatario) {
            if (empty($destinatario['NIF']) || !preg_match('/^[PQSV]/', $destinatario['NIF'])) {
                Tools::log()->error('json-desglose-clave-regimen-14-destinatario-nif-invalid', [
                    '%NIF%' => $destinatario['NIF'] ?? 'N/A',
                ]);
                return false;
            }
        }

        // TipoFactura: se validará que tipo de factura sea “F1”, “R1”, “R2”, “R3” o “R4”
        if (!isset($desglose['TipoFactura'])
            || !in_array($desglose['TipoFactura'], ['F1', 'R1', 'R2', 'R3', 'R4'])) {
            Tools::log()->error('json-desglose-clave-regimen-14-tipo-factura-invalid', [
                '%TipoFactura%' => $desglose['TipoFactura'] ?? 'N/A',
            ]);
            return false;
        }

        return true;
    }

    private static function claveRegimen20(array $desglose): bool
    {
        // si el impuesto no es 03, terminamos
        if ($desglose['Impuesto'] !== '03') {
            return true;
        }

        // ClaveRegimen debe estar contenido en la
        // lista L8B y adicionalmente puede contener el valor “20” (Operaciones sujetas al IPSI)
        if (!isset($desglose['ClaveRegimen'])
            || !in_array($desglose['ClaveRegimen'], ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '14', '15', '17', '18', '19', '20'])) {
            Tools::log()->error('json-desglose-clave-regimen-20-invalid', [
                '%ClaveRegimen%' => $desglose['ClaveRegimen'] ?? 'N/A',
            ]);
            return false;
        }

        return true;
    }

    private static function claveRegimen21(array $desglose): bool
    {
        // si el impuesto no es 03, terminamos
        if ($desglose['Impuesto'] !== '03') {
            return true;
        }

        // ClaveRegimen debe estar contenido en la
        // lista L8B y adicionalmente puede contener el valor “21” (Régimen simplificado)
        if (!isset($desglose['ClaveRegimen'])
            || !in_array($desglose['ClaveRegimen'], ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '14', '15', '17', '18', '19', '21'])) {
            Tools::log()->error('json-desglose-clave-regimen-21-invalid', [
                '%ClaveRegimen%' => $desglose['ClaveRegimen'] ?? 'N/A',
            ]);
            return false;
        }

        return true;
    }

    private static function cuotaRepercutida(array $desglose): bool
    {
        // si no existe el campo CuotaRepercutida, terminamos
        if (!isset($desglose['CuotaRepercutida'])) {
            return true;
        }

        // El campo CuotaRepercutida solo podrá ser distinta de cero (positivo o negativo) si CalificacionOperacion es “S1”
        if (isset($desglose['CuotaRepercutida'])
            && $desglose['CuotaRepercutida'] !== 0
            && (isset($desglose['CalificacionOperacion']) && $desglose['CalificacionOperacion'] !== 'S1')) {
            Tools::log()->error('json-desglose-cuota-repercutida-invalid', [
                '%CalificacionOperacion%' => $desglose['CalificacionOperacion'] ?? 'N/A',
            ]);
            return false;
        }

        // Si CalificacionOperacion es “S1” y BaseImponibleACoste no está cumplimentada, se validará que...
        if (isset($desglose['CalificacionOperacion'])
            && $desglose['CalificacionOperacion'] === 'S1'
            && !isset($desglose['BaseImponibleACoste'])) {
            // TipoImpositivo: campo obligatorio
            if (!isset($desglose['TipoImpositivo'])) {
                Tools::log()->error('json-desglose-cuota-repercutida-tipo-impositivo-required');
                return false;
            }

            // CuotaRepercutida: campo obligatorio y deberá validarse (excepto si TipoRectificativa = “I” o TipoFactura “R2”, “R3”) que:
            if (!isset($desglose['CuotaRepercutida'])) {
                Tools::log()->error('json-desglose-cuota-repercutida-required');
                return false;
            }

            // 1 - CuotaRepercutida y BaseImponibleOimporteNoSujeto deben tener el mismo signo.
            if (isset($desglose['BaseImponibleOimporteNoSujeto'])
                && (($desglose['CuotaRepercutida'] > 0 && $desglose['BaseImponibleOimporteNoSujeto'] < 0)
                    || ($desglose['CuotaRepercutida'] < 0 && $desglose['BaseImponibleOimporteNoSujeto'] > 0))) {
                Tools::log()->error('json-desglose-cuota-repercutida-signo-invalid-1');
                return false;
            }

            // 2 - [CuotaRepercutida] = ([BaseImponibleOimporteNoSujeto] * TipoImpositivo) / 100 +/- 10,00 euros
            if (isset($desglose['BaseImponibleOimporteNoSujeto'])
                && abs($desglose['CuotaRepercutida'] - (($desglose['BaseImponibleOimporteNoSujeto'] * $desglose['TipoImpositivo']) / 100)) > 10) {
                Tools::log()->error('json-desglose-cuota-repercutida-valor-invalid', [
                    '%calculated%' => (($desglose['BaseImponibleOimporteNoSujeto'] * $desglose['TipoImpositivo']) / 100),
                    '%expected%' => $desglose['CuotaRepercutida'],
                    '%CuotaRepercutida%' => $desglose['CuotaRepercutida'],
                    '%BaseImponibleOimporteNoSujeto%' => $desglose['BaseImponibleOimporteNoSujeto'],
                    '%TipoImpositivo%' => $desglose['TipoImpositivo'],
                ]);
                return false;
            }
        }

        // Si CalificacionOperacion es “S1” y BaseImponibleACoste está cumplimentada, se validará que...
        if (isset($desglose['CalificacionOperacion'])
            && $desglose['CalificacionOperacion'] === 'S1'
            && isset($desglose['BaseImponibleACoste'])) {
            // TipoImpositivo: campo obligatorio
            if (!isset($desglose['TipoImpositivo'])) {
                Tools::log()->error('json-desglose-cuota-repercutida-tipo-impositivo-required');
                return false;
            }

            // CuotaRepercutida: campo obligatorio y deberá validarse (excepto si TipoRectificativa = “I” o TipoFactura “R2”, “R3”) que
            if (!isset($desglose['CuotaRepercutida'])) {
                Tools::log()->error('json-desglose-cuota-repercutida-required');
                return false;
            }

            // 1 - CuotaRepercutida y BaseImponibleACoste deben tener el mismo signo
            if (($desglose['CuotaRepercutida'] > 0 && $desglose['BaseImponibleACoste'] < 0)
                || ($desglose['CuotaRepercutida'] < 0 && $desglose['BaseImponibleACoste'] > 0)) {
                Tools::log()->error('json-desglose-cuota-repercutida-signo-invalid-2');
                return false;
            }

            // 2 - [CuotaRepercutida] = ([BaseImponibleACoste] * TipoImpositivo) / 100 +/-10,00 euros
            if (abs($desglose['CuotaRepercutida'] - (($desglose['BaseImponibleACoste'] * $desglose['TipoImpositivo']) / 100)) > 10) {
                Tools::log()->error('json-desglose-cuota-repercutida-valor-invalid', [
                    '%calculated%' => (($desglose['BaseImponibleACoste'] * $desglose['TipoImpositivo']) / 100),
                    '%expected%' => $desglose['CuotaRepercutida'],
                    '%CuotaRepercutida%' => $desglose['CuotaRepercutida'],
                    '%BaseImponibleACoste%' => $desglose['BaseImponibleACoste'],
                    '%TipoImpositivo%' => $desglose['TipoImpositivo'],
                ]);
                return false;
            }
        }

        return true;
    }

    private static function operacionExenta(array $desglose): bool
    {
        // si no hay campo OperacionExenta, terminamos
        if (!isset($desglose['OperacionExenta'])) {
            return true;
        }

        // Si Impuesto = “01” (IVA) o no se cumplimenta (considerándose “01” - IVA), el valor
        // de OperacionExenta deberá estar contenido en lista L10
        if ($desglose['Impuesto'] === '01'
            && (!isset($desglose['OperacionExenta']) || !in_array($desglose['OperacionExenta'], ['E1', 'E2', 'E3', 'E4', 'E5', 'E6']))) {
            Tools::log()->error('json-desglose-operacion-exenta-invalid');
            return false;
        }

        // Si Impuesto = “03” (IGIC), el valor de OperacionExenta deberá estar contenido en
        // lista L10 y adicionalmente podrá contener los valores “E7” y “E8”
        if ($desglose['Impuesto'] === '03'
            && (!isset($desglose['OperacionExenta']) || !in_array($desglose['OperacionExenta'], ['E7', 'E8']))) {
            Tools::log()->error('json-desglose-operacion-exenta-igic-invalid');
            return false;
        }

        // Si Impuesto = “01” (IVA), “03” (IGIC) o no se cumplimenta (considerándose “01” - IVA), y
        // ClaveRegimen es igual a “01”, no pueden marcarse los valores de OperacionExenta “E2” y “E3”
        if (in_array($desglose['Impuesto'], ['01', '03'])
            && $desglose['ClaveRegimen'] === '01'
            && (isset($desglose['OperacionExenta']) && in_array($desglose['OperacionExenta'], ['E2', 'E3']))) {
            Tools::log()->error('json-desglose-operacion-exenta-clave-regimen-01-invalid');
            return false;
        }

        // Si el campo OperacionExenta está cumplimentado no se pueden informar ninguno
        // de estos campos: TipoImpositivo, CuotaRepercutida, TipoRecargoEquivalencia y CuotaRecargoEquivalencia
        if (!empty($desglose['OperacionExenta'])
            && (isset($desglose['TipoImpositivo']) || isset($desglose['CuotaRepercutida'])
                || isset($desglose['TipoRecargoEquivalencia']) || isset($desglose['CuotaRecargoEquivalencia']))) {
            Tools::log()->error('json-desglose-operacion-exenta-tipo-impositivo-cuota-repercutida-tipo-recargo-invalid');
            return false;
        }

        return true;
    }

    private static function simplificadas(array $desglose): bool
    {
        // si la factura no es simplificada, terminamos
        if (!isset($desglose['TipoFactura']) || $desglose['TipoFactura'] !== 'F2') {
            return true;
        }

        // validará que Ʃ (BaseImponibleOimporteNoSujeto + CuotaRepercutida)
        // de todas las líneas de detalle no sea superior a 3.000,00 euros.
        // Se admitirá un error de + 10,00 euros

        /*
         * Esta validación no se aplicará cuando exista acuerdo de facturación, es decir, cuando el
            campo NumRegistroAcuerdoFacturacion esté cumplimentado. Esta validación tampoco se
            aplicará cuando el campo FacturaSinIdentifDestinatarioArticulo61d = “S”.
        */

        if (isset($desglose['NumRegistroAcuerdoFacturacion'])
            || (isset($desglose['FacturaSinIdentifDestinatarioArticulo61d']) && $desglose['FacturaSinIdentifDestinatarioArticulo61d'] === 'S')) {
            return true;
        }

        $totalBaseImponible = 0;
        $totalCuotaRepercutida = 0;
        foreach (self::$json['Desglose'] as $linea) {
            if (isset($linea['BaseImponibleOimporteNoSujeto'])) {
                $totalBaseImponible += $linea['BaseImponibleOimporteNoSujeto'];
            }
            if (isset($linea['CuotaRepercutida'])) {
                $totalCuotaRepercutida += $linea['CuotaRepercutida'];
            }
        }

        $total = $totalBaseImponible + $totalCuotaRepercutida;
        if ($total > 3000 && $total - 10 > 3000) {
            Tools::log()->error('json-desglose-simplificadas-total-invalid', [
                '%Total%' => $total,
                '%TotalBaseImponible%' => $totalBaseImponible,
                '%TotalCuotaRepercutida%' => $totalCuotaRepercutida,
            ]);
            return false;
        }

        return true;
    }

    private static function tipoImpositivo(array $desglose): bool
    {
        // si el Impuesto no es 01 o CalificacionOperacion no es S1, terminamos
        if ($desglose['Impuesto'] !== '01'
            && (isset($desglose['CalificacionOperacion']) || $desglose['CalificacionOperacion'] !== 'S1')) {
            return true;
        }

        // Solo se permiten TipoImpositivo = 0; 2; 4; 5; 7,5; 10 y 21 (valores que indican el tanto por ciento)
        if (isset($desglose['TipoImpositivo']) && !in_array($desglose['TipoImpositivo'], [0, 2, 4, 5, 7.5, 10, 21])) {
            Tools::log()->error('json-desglose-tipo-impositivo-invalid', [
                'TipoImpositivo' => $desglose['TipoImpositivo'],
            ]);
            return false;
        }

        // Si FechaOperacion (FechaExpedicionFactura de la agrupación IDFactura si no
        // se informa FechaOperacion) ≥ 1 de julio de 2022 y ≤ 30 de septiembre de
        // 2024 se admitirá TipoImpositivo = 5, si no, no se admitirá 5
        // convertimos las fechas string a formato timestamp para comparar
        $dateOperation = new DateTime();
        $dateOperation->setTimestamp(strtotime(self::$json['FechaOperacion'] ?? self::$json['IDFactura']['FechaExpedicionFactura']));
        $dateStart = DateTime::createFromFormat('d-m-Y', '01-07-2022');
        $dateEnd = DateTime::createFromFormat('d-m-Y', '30-09-2024');
        if (($dateOperation < $dateStart || $dateOperation > $dateEnd) && isset($desglose['TipoImpositivo']) && $desglose['TipoImpositivo'] === '5') {
            Tools::log()->error('json-desglose-tipo-impositivo-5-invalid', [
                '%FechaOperacion%' => $dateOperation->format('Y-m-d'),
            ]);
            return false;
        }

        // Si FechaOperacion (FechaExpedicionFactura de la agrupación IDFactura si no
        // se informa FechaOperacion) ≥ 1 de octubre de 2024 y ≤ 31 de diciembre de
        // 2024 se admitirá el TipoImpositivo = 2
        $dateStart = DateTime::createFromFormat('d-m-Y', '01-10-2024');
        $dateEnd = DateTime::createFromFormat('d-m-Y', '31-12-2024');
        if (($dateOperation < $dateStart || $dateOperation > $dateEnd) && isset($desglose['TipoImpositivo']) && $desglose['TipoImpositivo'] === '2') {
            Tools::log()->error('json-desglose-tipo-impositivo-2-invalid', [
                '%FechaOperacion%' => $dateOperation->format('Y-m-d'),
            ]);
            return false;
        }

        // Si FechaOperacion (FechaExpedicionFactura de la agrupación IDFactura si no
        // se informa FechaOperacion) ≥ 1 de octubre de 2024 y ≤ 31 de diciembre de
        // 2024 se admitirá el TipoImpositivo = 7,5
        if (($dateOperation < $dateStart || $dateOperation > $dateEnd) && isset($desglose['TipoImpositivo']) && $desglose['TipoImpositivo'] === 7.5) {
            Tools::log()->error('json-desglose-tipo-impositivo-7-5-invalid', [
                '%FechaOperacion%' => $dateOperation->format('Y-m-d'),
            ]);
            return false;
        }

        return true;
    }

    private static function tipoRecargoEquivalencia(array $desglose): bool
    {
        // si el Impuesto no es 01 o CalificacionOperacion no es S1, terminamos
        if ($desglose['Impuesto'] !== '01'
            && (isset($desglose['CalificacionOperacion']) || $desglose['CalificacionOperacion'] !== 'S1')) {
            return true;
        }

        // Solo se permiten TipoRecargoEquivalencia = 0; 0,26; 0,5; 0,62; 1; 1,4; 1,75;
        // 5,2 (valores que indican el tanto por ciento)
        if (isset($desglose['TipoRecargoEquivalencia']) && !in_array($desglose['TipoRecargoEquivalencia'], [0, 0.26, 0.5, 0.62, 1, 1.4, 1.75, 5.2])) {
            Tools::log()->error('json-desglose-tipo-recargo-equivalencia-invalid', [
                '%TipoRecargoEquivalencia%' => $desglose['TipoRecargoEquivalencia'],
            ]);
            return false;
        }

        // Si TipoImpositivo es 21 sólo se admitirán TipoRecargoEquivalencia = 5,2 ó 1,75
        if (isset($desglose['TipoImpositivo']) && $desglose['TipoImpositivo'] === '21'
            && !in_array($desglose['TipoRecargoEquivalencia'], [5.2, 1.75])) {
            Tools::log()->error('json-desglose-tipo-recargo-equivalencia-21-invalid', [
                '%TipoRecargoEquivalencia%' => $desglose['TipoRecargoEquivalencia'],
            ]);
            return false;
        }

        // Si TipoImpositivo es 10 sólo se admitirá TipoRecargoEquivalencia = 1,4
        if (isset($desglose['TipoImpositivo']) && $desglose['TipoImpositivo'] === '10'
            && $desglose['TipoRecargoEquivalencia'] !== '1.4') {
            Tools::log()->error('json-desglose-tipo-recargo-equivalencia-10-invalid', [
                '%TipoRecargoEquivalencia%' => $desglose['TipoRecargoEquivalencia'],
            ]);
            return false;
        }

        // Si TipoImpositivo es 7,5 solo se admitirá TipoRecargoEquivalencia = 1
        // 1 - Si FechaOperacion (FechaExpedicionFactura de la agrupación IDFactura
        // si no se informa FechaOperacion) es mayor o igual que 1 de octubre de
        // 2024 y menor o igual que 31 de diciembre de 2024 se admitirá el
        // TipoRecargoEquivalencia = 1
        if (isset($desglose['TipoImpositivo']) && $desglose['TipoImpositivo'] === 7.5
            && $desglose['TipoRecargoEquivalencia'] !== 1) {
            $dateOperation = new DateTime();
            $dateOperation->setTimestamp(strtotime(self::$json['FechaOperacion'] ?? self::$json['IDFactura']['FechaExpedicionFactura']));
            $dateStart = DateTime::createFromFormat('d-m-Y', '01-10-2024');
            $dateEnd = DateTime::createFromFormat('d-m-Y', '31-12-2024');
            if ($dateOperation < $dateStart || $dateOperation > $dateEnd) {
                Tools::log()->error('json-desglose-tipo-recargo-equivalencia-7-5-invalid', [
                    '%FechaOperacion%' => $dateOperation->format('Y-m-d'),
                ]);
                return false;
            }
        }

        // Si tipo impositivo es 5
        if (isset($desglose['TipoImpositivo']) && $desglose['TipoImpositivo'] === 5) {
            // Si FechaOperacion (FechaExpedicionFactura de la agrupación IDFactura
            // si no se informa FechaOperacion) es igual o inferior al 31 de diciembre de
            // 2022, solo se admitirá TipoRecargoEquivalencia = 0,5
            $dateOperation = new DateTime();
            $dateOperation->setTimestamp(strtotime(self::$json['FechaOperacion'] ?? self::$json['IDFactura']['FechaExpedicionFactura']));
            $dateEnd = DateTime::createFromFormat('d-m-Y', '31-12-2022');
            if ($dateOperation <= $dateEnd && $desglose['TipoRecargoEquivalencia'] !== 0.5) {
                Tools::log()->error('json-desglose-tipo-recargo-equivalencia-5-invalid', [
                    '%FechaOperacion%' => $dateOperation->format('Y-m-d'),
                    '%TipoRecargoEquivalencia%' => $desglose['TipoRecargoEquivalencia'],
                ]);
                return false;
            }

            // Si FechaOperacion (FechaExpedicionFactura de la agrupación IDFactura
            // si no se informa FechaOperacion) es mayor o igual que 1 de enero de
            // 2023 y menor o igual que 30 de septiembre de 2024, solo se admitirá
            // TipoRecargoEquivalencia = 0,62
            $dateStart = DateTime::createFromFormat('d-m-Y', '01-01-2023');
            $dateEnd = DateTime::createFromFormat('d-m-Y', '30-09-2024');
            if ($dateOperation >= $dateStart && $dateOperation <= $dateEnd
                && $desglose['TipoRecargoEquivalencia'] !== 0.62) {
                Tools::log()->error('json-desglose-tipo-recargo-equivalencia-62-invalid', [
                    '%FechaOperacion%' => $dateOperation->format('Y-m-d'),
                    '%TipoRecargoEquivalencia%' => $desglose['TipoRecargoEquivalencia'],
                ]);
                return false;
            }
        }

        // Si TipoImpositivo es 4 sólo se admitirá TipoRecargoEquivalencia = 0,5
        if (isset($desglose['TipoImpositivo']) && $desglose['TipoImpositivo'] === 4 && $desglose['TipoRecargoEquivalencia'] !== 0.5) {
            Tools::log()->error('json-desglose-tipo-recargo-equivalencia-4-invalid', [
                '%TipoRecargoEquivalencia%' => $desglose['TipoRecargoEquivalencia'],
            ]);
            return false;
        }

        // Si TipoImpositivo es 2 sólo se admitirá TipoRecargoEquivalencia = 0,26
        if (isset($desglose['TipoImpositivo']) && $desglose['TipoImpositivo'] === 2 && $desglose['TipoRecargoEquivalencia'] !== 0.26) {
            // Si FechaOperacion (FechaExpedicionFactura de la agrupación IDFactura
            // si no se informa FechaOperacion) es mayor o igual que 1 de octubre de
            // 2024 y menor o igual que 31 de diciembre de 2024 se admitirá el
            // TipoRecargoEquivalencia = 0,26
            $dateOperation = new DateTime();
            $dateOperation->setTimestamp(strtotime(self::$json['FechaOperacion'] ?? self::$json['IDFactura']['FechaExpedicionFactura']));
            $dateStart = DateTime::createFromFormat('d-m-Y', '01-10-2024');
            $dateEnd = DateTime::createFromFormat('d-m-Y', '31-12-2024');
            if ($dateOperation < $dateStart || $dateOperation > $dateEnd) {
                Tools::log()->error('json-desglose-tipo-recargo-equivalencia-26-invalid', [
                    '%FechaOperacion%' => $dateOperation->format('Y-m-d'),
                    '%TipoRecargoEquivalencia%' => $desglose['TipoRecargoEquivalencia'],
                ]);
                return false;
            }
        }

        // Si tipo impositivo es 0
        if (isset($desglose['TipoImpositivo']) && $desglose['TipoImpositivo'] === 0) {
            // Si FechaOperacion (FechaExpedicionFactura de la agrupación IDFactura
            // si no se informa FechaOperacion) es mayor o igual que 1 de enero de
            // 2023 y menor o igual que 30 de septiembre de 2024, solo se admitirá
            // TipoRecargoEquivalencia = 0
            $dateOperation = new DateTime();
            $dateOperation->setTimestamp(strtotime(self::$json['FechaOperacion'] ?? self::$json['IDFactura']['FechaExpedicionFactura']));
            $dateStart = DateTime::createFromFormat('d-m-Y', '01-01-2023');
            $dateEnd = DateTime::createFromFormat('d-m-Y', '30-09-2024');
            if ($dateOperation >= $dateStart && $dateOperation <= $dateEnd
                && $desglose['TipoRecargoEquivalencia'] !== 0) {
                Tools::log()->error('json-desglose-tipo-recargo-equivalencia-0-invalid', [
                    '%FechaOperacion%' => $dateOperation->format('Y-m-d'),
                    '%TipoRecargoEquivalencia%' => $desglose['TipoRecargoEquivalencia'],
                ]);
                return false;
            }
        }

        return true;
    }
}