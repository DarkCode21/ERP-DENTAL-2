<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroFactura;

use FacturaScripts\Dinamic\Lib\InvoiceOperation;
use FacturaScripts\Dinamic\Lib\OperacionIVA;
use FacturaScripts\Dinamic\Lib\ProductType;
use FacturaScripts\Dinamic\Lib\RegimenIVA;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\Impuesto;
use FacturaScripts\Dinamic\Model\LineaFacturaCliente;
use FacturaScripts\Plugins\Verifactu\Lib\FiscalNumberValidator;

/**
 * Clase para calcular el desglose de la factura de alta o subsanación para Verifactu.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class JsonDesglose
{
    /** @var Empresa */
    private static $company;

    /** @var FacturaCliente */
    private static $invoice;

    /** @var array */
    private static $lines;

    public static function getBreakdowns(Empresa $company, FacturaCliente $invoice, array $lines): array
    {
        $breakdowns = [];
        self::$company = $company;
        self::$invoice = $invoice;
        self::$lines = $lines;

        // obtenemos las líneas que se van a enviar
        $vfLines = [];
        foreach ($lines as $line) {
            if ($line->vf_send || is_null($line->vf_send)) {
                $vfLines[] = $line;
            }
        }

        foreach ($vfLines as $line) {
            $tax = $line->getTax();
            $impuesto = self::getImpuesto($line, $tax);
            $claveRegimen = $tax->operacion === OperacionIVA::ES_OPERATION_03
                ? self::getClaveRegimenIGIC($line) : self::getClaveRegimen($line);
            $calificacionOperacion = self::getCalificacionOperacion($line);
            $operacionExenta = self::getOperacionExenta($line);

            $key = implode('|', [
                $line->iva,
                $line->recargo,
                $impuesto,
                $claveRegimen,
                $calificacionOperacion,
                $operacionExenta,
            ]);

            if (false === isset($breakdowns[$key])) {
                $breakdowns[$key] = [
                    'Impuesto' => $impuesto,
                    'ClaveRegimen' => $claveRegimen,
                    'CalificacionOperacion' => $calificacionOperacion,
                    'OperacionExenta' => $operacionExenta,
                    'TipoImpositivo' => $line->iva,
                    'BaseImponibleOimporteNoSujeto' => 0,
                    'BaseImponibleACoste' => 0,
                    'CuotaRepercutida' => 0,
                    'TipoRecargoEquivalencia' => $line->recargo,
                    'CuotaRecargoEquivalencia' => 0,
                ];
            }

            $tax = $line->getTax();
            $pvpTotal = $line->pvptotal * (100 - $invoice->dtopor1) / 100 * (100 - $invoice->dtopor2) / 100;

            $breakdowns[$key]['BaseImponibleOimporteNoSujeto'] += $pvpTotal;
            $breakdowns[$key]['BaseImponibleACoste'] += $line->cantidad * $line->coste;

            $breakdowns[$key]['CuotaRepercutida'] += $tax->tipo === Impuesto::TYPE_FIXED_VALUE ?
                $pvpTotal * $line->iva :
                $pvpTotal * $line->iva / 100;

            $breakdowns[$key]['CuotaRecargoEquivalencia'] += $tax->tipo === Impuesto::TYPE_FIXED_VALUE ?
                $pvpTotal * $line->recargo :
                $pvpTotal * $line->recargo / 100;
        }

        // redondeamos los valores
        foreach ($breakdowns as $key => $subtotal) {
            $breakdowns[$key]['BaseImponibleOimporteNoSujeto'] = round($subtotal['BaseImponibleOimporteNoSujeto'], 2);
            $breakdowns[$key]['BaseImponibleACoste'] = round($subtotal['BaseImponibleACoste'], 2);
            $breakdowns[$key]['CuotaRepercutida'] = round($subtotal['CuotaRepercutida'], 2);
            $breakdowns[$key]['CuotaRecargoEquivalencia'] = round($subtotal['CuotaRecargoEquivalencia'], 2);
        }

        return $breakdowns;
    }

    /**
     * Determina la calificación de la operación para el desglose de la factura
     *
     * La calificación de la operación identifica si la operación está sujeta o no a IVA,
     * y en caso de estar sujeta, si está exenta o no, y si hay inversión del sujeto pasivo.
     *
     * Valores posibles:
     * S1 - Operación Sujeta y No exenta - Sin inversión del sujeto pasivo.
     * S2 - Operación Sujeta y No exenta - Con Inversión del sujeto pasivo
     * N1 - Operación No Sujeta artículo 7, 14, otros.
     * N2 - Operación No Sujeta por Reglas de localización.
     *
     * @return string Código de la calificación de la operación
     */
    private static function getCalificacionOperacion(LineaFacturaCliente $line): string
    {
        // Si la línea tiene IVA positivo y no hay exención
        if ($line->iva > 0 && empty($line->excepcioniva)) {
            return 'S1'; // Operación Sujeta y No exenta - Sin inversión del sujeto pasivo
        }

        // Comprobar si es una operación con inversión del sujeto pasivo
        if (!empty($line->excepcioniva) && $line->excepcioniva === RegimenIVA::ES_TAX_EXCEPTION_PASSIVE_SUBJECT) {
            return 'S2'; // Operación Sujeta y No exenta - Con Inversión del sujeto pasivo
        }

        // Comprobar si es una operación no sujeta por reglas de localización
        // (operaciones fuera del territorio de aplicación del IVA)
        if (self::$invoice->codpais && self::$invoice->codpais !== self::$company->codpais) {
            return 'N2'; // Operación No Sujeta por Reglas de localización
        }

        // Comprobar si es una operación no sujeta por otros motivos
        if (!empty($line->excepcioniva) &&
            in_array($line->excepcioniva, [RegimenIVA::ES_TAX_EXCEPTION_ART_7, RegimenIVA::ES_TAX_EXCEPTION_ART_14])) {
            return 'N1'; // Operación No Sujeta artículo 7, 14, otros
        }

        // Por defecto, no se devuelve calificación si es operación común
        return '';
    }

    /**
     * Determina el código del régimen aplicable para una línea de factura.
     *
     * Esta función devuelve el código del régimen que corresponde a la línea proporcionada
     * basándose en el tipo de producto, los impuestos aplicables, el régimen del sujeto pasivo,
     * entre otros factores, usando la tabla L8A del modelo Verifactu.
     *
     * Códigos:
     * 01 - Operación en régimen general
     * 02 - Exportación
     * 03 - Operación de bienes usados
     * 04 - Régimen especial de oro de inversión
     * 05 - Régimen especial de agencias de viajes
     * 06 - Régimen especial grupo de entidades (nivel avanzado)
     * 07 - Régimen especial del criterio de caja
     * 08 - Operación con IGIC o IPSI
     * 09 - Operación de agencia de viajes
     * 10 - Operación suplida
     * 11 - Arrendamiento de local de negocio
     * 14 - Factura con IVA pendiente de devengo en certificaciones de obra cuyo destinatario sea una Administración Pública.
     * 15 - Factura con IVA pendiente de devengo en operaciones de tracto sucesivo.
     * 17 - Operación acogida a alguno de los regímenes previstos en el Capítulo XI del Título IX (OSS e IOSS).
     * 18 - Operación con recargo de equivalencia
     * 19 - Régimen especial de agricultura, ganadería y pesca
     * 20 - Régimen simplificado
     *
     * @param LineaFacturaCliente $line Objeto que representa la línea de factura para la cual se determinará el código del régimen.
     * @return string Código que corresponde al régimen aplicable para la línea proporcionada.
     */
    private static function getClaveRegimen(LineaFacturaCliente $line): string
    {
        // formateamos el cif del documento
        $cifNif = FiscalNumberValidator::normaliceCifNif(self::$invoice->cifnif, '/^[A-Z0-9]{1,9}$/');

        // obtenemos el primer carácter del cif
        $firstChar = substr($cifNif, 0, 1);

        // Obtener el producto asociado a la línea
        $producto = $line->getProducto();

        // Comprobar si es una operación de bienes usados (03)
        if ($producto->exists() && $producto->tipo === ProductType::SECOND_HAND) {
            return '03';
        }

        // Comprobar si es una operación de agencia de viajes (09)
        if ($producto->exists() && $producto->tipo === ProductType::TRAVEL) {
            return '09';
        }

        // Comprobar si es una operación con IGIC o IPSI (08)
        $tax = $line->getTax();
        if ($tax->operacion === OperacionIVA::ES_OPERATION_03) {
            return '08';
        }

        // Comprobar si es una operación con recargo de equivalencia (18)
        if ($line->recargo > 0) {
            return '18';
        }

        // Comprobar si es un arrendamiento de local de negocio (11)
        if (!empty($producto->primaryColumnValue()) && $producto->tipo === ProductType::LOCAL_BUSINESS_RENTAL) {
            return '11';
        }

        // Comprobar si es una exportación (02)
        if (self::$invoice->codpais && self::$invoice->codpais !== self::$company->codpais) {
            return '02';
        }

        // comprobar si la empresa tiene el régimen especial OSS o IOSS
        // y el país del cliente no es el mismo que el de la empresa
        if (in_array(self::$company->regimeniva, [
                RegimenIVA::TAX_SYSTEM_ONE_STOP_SHOP_OSS,
                RegimenIVA::TAX_SYSTEM_ONE_STOP_SHOP_IOSS,
            ]) && self::$invoice->codpais !== self::$company->codpais) {
            return '16';
        }

        // comprobar si la operación del documento es una operación de beneficio a terceros
        if (self::$invoice->operacion === InvoiceOperation::BENEFIT_THIRD_PARTIES) {
            return '10';
        }

        // comprobamos si el cif del documento es de una administración pública
        // y la operación del documento es una certificación de obra
        // y existe fecha de devengo mayor a la fecha del documento
        if (self::$invoice->operacion === InvoiceOperation::WORK_CERTIFICATION
            && in_array($firstChar, ['P', 'Q', 'S'])
            && !empty(self::$invoice->fechadevengo)
            && strtotime(self::$invoice->fechadevengo) > strtotime(self::$invoice->fecha)) {
            return '14';
        }

        // comprobar si la operación del documento es tracto sucesivo en la factura
        // y existe fecha de devengo mayor a la fecha del documento
        if (self::$invoice->operacion === InvoiceOperation::SUCCESSIVE_TRACT
            && !empty(self::$invoice->fechadevengo)
            && strtotime(self::$invoice->fechadevengo) > strtotime(self::$invoice->fecha)) {
            return '15';
        }

        // Comprobar si es régimen especial del criterio de caja (07)
        if (self::$company->regimeniva === RegimenIVA::TAX_SYSTEM_CASH_CRITERIA) {
            return '07';
        }

        // Comprobar si es régimen especial de agricultura, ganadería y pesca (19)
        if (self::$company->regimeniva === RegimenIVA::TAX_SYSTEM_AGRARIAN) {
            return '19';
        }

        // Comprobar si es régimen simplificado (20)
        if (self::$company->regimeniva === RegimenIVA::TAX_SYSTEM_SIMPLIFIED) {
            return '20';
        }

        // Comprobar si es régimen de oro de inversión (04)
        if (self::$company->regimeniva === RegimenIVA::TAX_SYSTEM_GOLD) {
            return '04';
        }

        // Comprobar si es régimen especial grupo de entidades en IVA (Nivel Avanzado) (06)
        if (self::$company->regimeniva === RegimenIVA::TAX_SYSTEM_GROUP_ENTITIES) {
            return '06';
        }

        // Comprobar si es régimen de agencias de viajes (05)
        if (self::$company->regimeniva === RegimenIVA::TAX_SYSTEM_TRAVEL) {
            return '05';
        }

        // Por defecto, operación de régimen general (01)
        return '01';
    }

    /**
     * Determina el código del régimen aplicable para una línea de factura con IGIC.
     *
     * Esta función devuelve el código del régimen que corresponde a la línea proporcionada
     * basándose en el tipo de producto, los impuestos aplicables, el régimen del sujeto pasivo,
     * entre otros factores, usando la tabla L8B del modelo Verifactu.
     *
     * Donde cambian los siguientes códigos, el resto son iguales:
     * 14 - Factura con IGIC pendiente de devengo en certificaciones de obra cuyo destinatario sea una Administración Pública.
     * 15 - Factura con IGIC pendiente de devengo en operaciones de tracto sucesivo.
     * 17 - Régimen especial de comerciante minorista
     * 18 - Régimen especial del pequeño empresario o profesional
     * 19 - Operaciones interiores exentas por aplicación artículo 25 Ley 19/1994
     *
     * @param LineaFacturaCliente $line
     * @return string
     */
    private static function getClaveRegimenIGIC(LineaFacturaCliente $line): string
    {
        // formateamos el cif del documento
        $cifNif = FiscalNumberValidator::normaliceCifNif(self::$invoice->cifnif, '/^[A-Z0-9]{1,9}$/');

        // obtenemos el primer carácter del cif
        $firstChar = substr($cifNif, 0, 1);

        // comprobamos si el cliente tiene un cif de administración pública
        // la operación del documento es operación de certificación de obra
        // y existe fecha de devengo mayor a la fecha de del documento
        if (self::$invoice->operacion === InvoiceOperation::WORK_CERTIFICATION
            && in_array($firstChar, ['P', 'Q', 'S'])
            && !empty(self::$invoice->fechadevengo)
            && strtotime(self::$invoice->fechadevengo) > strtotime(self::$invoice->fecha)) {
            return '14';
        }

        // comprobar si la operación del documento es tracto sucesivo en la factura
        // y existe fecha de devengo a futuro
        if (self::$invoice->operacion === InvoiceOperation::SUCCESSIVE_TRACT
            && !empty(self::$invoice->fechadevengo)
            && strtotime(self::$invoice->fechadevengo) > strtotime(self::$invoice->fecha)) {
            return '15';
        }

        // comprobar si la empresa tiene el régimen especial de comerciante minorista
        if (self::$company->regimeniva === RegimenIVA::TAX_SYSTEM_SPECIAL_RETAIL_TRADERS) {
            return '17';
        }

        // comprobar si la empresa tiene el régimen especial del pequeño empresario o profesional
        if (self::$company->regimeniva === RegimenIVA::TAX_SYSTEM_SPECIAL_SMALL_BUSINESS) {
            return '18';
        }

        // comprobar si la línea tiene una excepción de IVA con el código ES_25
        if (!empty($line->excepcioniva) && $line->excepcioniva === RegimenIVA::ES_TAX_EXCEPTION_E5) {
            return '19';
        }

        return self::getClaveRegimen($line);
    }

    /**
     * Obtiene el código identificador del tipo de impuesto aplicado.
     *
     * Este código se utiliza para identificar el tipo de impuesto que se aplica
     * a una transacción. El valor retornado está predefinido como "01", que
     * generalmente corresponde al IVA (Impuesto al Valor Agregado).
     *
     * Valores comunes:
     * 01 - Impuesto sobre el Valor Añadido (IVA)
     * 02 - Impuesto sobre la Producción, los Servicios y la Importación (IPSI) de Ceuta y Melilla
     * 03 - Impuesto General Indirecto Canario (IGIC)
     * 04 - Otros
     *
     * @return string Código del impuesto aplicado
     */
    private static function getImpuesto(LineaFacturaCliente $line, Impuesto $tax): string
    {
        return match ($tax->operacion) {
            OperacionIVA::ES_OPERATION_02 => '02',
            OperacionIVA::ES_OPERATION_03 => '03',
            OperacionIVA::ES_OPERATION_99 => '04',
            default => '01',
        };
    }

    /**
     * Determina el código de operación exenta para el desglose de la factura
     *
     * Este código se utiliza cuando la operación está exenta de IVA para indicar
     * el motivo de la exención. Solo aplica cuando la calificación de la operación
     * indica que la operación está sujeta pero exenta.
     *
     * Valores comunes:
     * E1 - Exenta por el artículo 20 de la Ley del IVA
     * E2 - Exenta por el artículo 21 de la Ley del IVA (exportaciones)
     * E3 - Exenta por el artículo 22 de la Ley del IVA
     * E4 - Exenta por los artículos 23 y 24 de la Ley del IVA
     * E5 - Exenta por el artículo 25 de la Ley del IVA
     * E6 - Exenta por otra normativa
     *
     * @return string Código de la operación exenta
     */
    private static function getOperacionExenta(LineaFacturaCliente $line): string
    {
        // Si la operación no está exenta, devolvemos un valor vacío
        if (empty($line->excepcioniva)) {
            return '';
        }

        return match ($line->excepcioniva) {
            'ES_20' => 'E1',
            'ES_21' => 'E2',
            'ES_22' => 'E3',
            'ES_23_24' => 'E4',
            'ES_25' => 'E5',
            'ES_ART_14' => 'E7',
            'ES_ART_15' => 'E8',
            default => 'E6',
        };
    }
}
