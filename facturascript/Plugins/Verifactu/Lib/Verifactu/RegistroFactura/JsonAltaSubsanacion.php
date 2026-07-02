<?php

/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroFactura;

use Exception;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\Verifactu\Lib\FiscalNumberValidator;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\JsonTrait;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRegistroFactura;

/**
 * Clase para generar el JSON de alta de una factura en Verifactu.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class JsonAltaSubsanacion
{
    use JsonTrait;
    use JsonRegistroFacturaTrait;

    /** @var string */
    private static $event;

    public static function generate(FacturaCliente $invoice, string $event): bool
    {
        try {
            // comprobamos que la factura existe
            if (empty($invoice->primaryColumnValue())) {
                Tools::log()->warning('record-not-found');
                return false;
            }

            // comprobamos que la empresa está configurada
            self::$company = $invoice->getCompany();
            if (false === self::$company->verifactuIsConfigured()) {
                return false;
            }

            if ($event === VerifactuRegistroFactura::EVENT_ALTA) {
                // comprobamos si la factura está dada de alta
                if ($invoice->verifactuCheckAlta()) {
                    Tools::log()->warning('verifactu-invoice-already-high', [
                        'model-code' => $invoice->primaryColumnValue(),
                        'model-class' => $invoice->modelClassName(),
                    ]);
                    return false;
                }

                // comprobamos si la factura está anulada
                if ($invoice->verifactuCheckAnulacion()) {
                    Tools::log()->warning('verifactu-invoice-already-annulled', [
                        'model-code' => $invoice->primaryColumnValue(),
                        'model-class' => $invoice->modelClassName(),
                    ]);
                    return false;
                }
            } elseif ($event === VerifactuRegistroFactura::EVENT_SUBSANACION) {
                // comprobamos si la factura está dada de alta
                if (!$invoice->verifactuCheckAlta()) {
                    Tools::log()->warning('verifactu-invoice-not-high', [
                        'model-code' => $invoice->primaryColumnValue(),
                        'model-class' => $invoice->modelClassName(),
                    ]);
                    return false;
                }

                // comprobamos si la factura está anulada
                if ($invoice->verifactuCheckAnulacion()) {
                    Tools::log()->warning('verifactu-invoice-already-annulled', [
                        'model-code' => $invoice->primaryColumnValue(),
                        'model-class' => $invoice->modelClassName(),
                    ]);
                    return false;
                }

                // comprueba que no tenga un registro de subsanación pendiente de enviar
                foreach ($invoice->verifactuGetRegistroFactura() as $log) {
                    if ($log->event === VerifactuRegistroFactura::EVENT_SUBSANACION && empty($log->status)) {
                        Tools::log()->warning('verifactu-invoice-substation-pending', [
                            'model-code' => $invoice->primaryColumnValue(),
                            'model-class' => $invoice->modelClassName(),
                        ]);
                        return false;
                    }
                }
            } else {
                Tools::log()->warning('verifactu-invalid-event', [
                    'event' => $event,
                    'model-code' => $invoice->primaryColumnValue(),
                    'model-class' => $invoice->modelClassName(),
                ]);
                return false;
            }

            // cargamos los datos generales
            self::$event = $event;
            self::loadData($invoice);

            if (false === self::jsonIDVersion()) {
                return false;
            } elseif (false === self::jsonIDFactura()) {
                return false;
            } elseif (false === self::jsonRefExterna()) {
                return false;
            } elseif (false === self::jsonNombreRazonEmisor()) {
                return false;
            } elseif (false === self::jsonSubsanacion()) {
                return false;
            } elseif (false === self::jsonRechazoPrevio()) {
                return false;
            } elseif (false === self::jsonTipoFactura()) {
                return false;
            } elseif (false === self::jsonTipoRectificativa()) {
                return false;
            } elseif (false === self::jsonFechaOperacion()) {
                return false;
            } elseif (false === self::jsonDescripcionOperacion()) {
                return false;
            } elseif (false === self::jsonFacturaSimplificadaArt7273()) {
                return false;
            } elseif (false === self::jsonFacturaSinIdentifDestinatarioArt61d()) {
                return false;
            } elseif (false === self::jsonDestinatarios()) {
                return false;
            } elseif (false === self::jsonCupon()) {
                return false;
            } elseif (false === self::jsonDesglose()) {
                return false;
            } elseif (false === self::jsonCuotaTotal()) {
                return false;
            } elseif (false === self::jsonImporteTotal()) {
                return false;
            } elseif (false === self::jsonSistemaInformatico()) {
                return false;
            } elseif (false === self::jsonFechaHoraHusoGenRegistro()) {
                return false;
            }

            // creamos el array JSON
            $data = self::$json;
            self::$json = [];
            self::$json['RegistroAlta'] = $data;

            // Validamos el JSON
            if (!JsonValidate::validate(self::$json)) {
                return false;
            }

            // creamos el sufijo del archivo JSON
            $fileSuffix = self::$event;
            if (self::$event === VerifactuRegistroFactura::EVENT_SUBSANACION) {
                // si es una subsanación, buscamos cuantos archivos de subsanación hay y sumamos 1
                $count = 1;
                foreach ($invoice->verifactuGetRegistroFactura() as $registroFactura) {
                    if ($registroFactura->event === VerifactuRegistroFactura::EVENT_SUBSANACION) {
                        $count++;
                    }
                }
                // el sufijo del archivo es 'subsanacion' + número de subsanación
                $fileSuffix .= '-' . $count;
            }

            // creamos el archivo JSON
            if (false === self::createFile($fileSuffix)) {
                return false;
            }

            // creamos el evento de registro de factura
            if (false === self::createEvent(self::$event)) {
                return false;
            }

            return true;
        } catch (Exception $e) {
            Tools::log()->error('json-high-substation-error', [
                '%error%' => $e->getMessage(),
                'model-code' => self::$invoice->primaryColumnValue(),
                'model-class' => self::$invoice->modelClassName(),
            ]);
            return false;
        }
    }

    private static function getTypeRectificativa(): string
    {
        $invoiceCifNif = FiscalNumberValidator::normaliceCifNif(self::$invoice->cifnif, '/^[A-Z0-9]{1,9}$/');
        $invoiceRefundCifNif = FiscalNumberValidator::normaliceCifNif(self::$invoiceRefund->cifnif, '/^[A-Z0-9]{1,9}$/');

        // si el cliente de la factura original y el cliente de la rectificativa es diferente, entonces S
        if (
            $invoiceCifNif !== $invoiceRefundCifNif
            || self::$invoice->direccion !== self::$invoiceRefund->direccion
            || self::$invoice->apartado !== self::$invoiceRefund->apartado
            || self::$invoice->ciudad !== self::$invoiceRefund->ciudad
            || self::$invoice->provincia !== self::$invoiceRefund->provincia
            || self::$invoice->codpais !== self::$invoiceRefund->codpais
            || self::$invoice->codpostal !== self::$invoiceRefund->codpostal
        ) {
            return 'S';
        }

        // si el total de la factura es igual al total de la rectificativa, entonces S
        if (abs(self::$invoice->total) === abs(self::$invoiceRefund->total)) {
            return 'S';
        }

        // contamos cuantos cambios de descripciones hay entre las facturas
        $changesDesc = 0;
        $linesRect = self::$invoiceRefund->getLines();
        foreach (self::$lines as $line) {
            foreach ($linesRect as $lineRefund) {
                if ($line->descripcion !== $lineRefund->descripcion) {
                    $changesDesc++;
                }
            }
        }

        // si la mayoría de las líneas cambiadas son de descripción, entonces S
        if ($changesDesc > count(self::$lines) / 2) {
            return 'S';
        }

        return 'I';
    }

    private static function jsonCuotaTotal(): bool
    {
        self::$json['CuotaTotal'] = number_format(self::$invoice->totaliva, 2, '.', '');
        return true;
    }

    private static function jsonCupon(): bool
    {
        // El valor de cupón solo puede ser S si el tipo de factura es R1 o R5
        if (!in_array(self::$json['TipoFactura'], ['R1', 'R5'])) {
            return true;
        }

        // comprobar si la factura tiene algún descuento
        $discount = false;
        foreach (self::$lines as $line) {
            if ($line->dtopor > 0 || $line->dtopor2 > 0) {
                $discount = true;
                break;
            }
        }

        if (self::$invoice->dtopor1 > 0 || self::$invoice->dtopor2 > 0) {
            $discount = true;
        }

        if ($discount) {
            self::$json['Cupon'] = 'S';
        }

        return true;
    }

    private static function jsonDescripcionOperacion(): bool
    {
        self::$json['DescripcionOperacion'] = 'Venta de productos o servicios';
        return true;
    }

    private static function jsonDesglose(): bool
    {
        // obtenemos los subtotales de la factura
        $breakdowns = JsonDesglose::getBreakdowns(self::$company, self::$invoice, self::$lines);

        // si no hay subtotales, terminamos
        if (empty($breakdowns)) {
            Tools::log()->error('not-found-breakdowns', [
                'model-code' => self::$invoice->primaryColumnValue(),
                'model-class' => self::$invoice->modelClassName(),
            ]);
            return false;
        } elseif (count($breakdowns) > 12) {
            // si hay más de 12 subtotales, terminamos
            Tools::log()->error('too-many-breakdowns', [
                'model-code' => self::$invoice->primaryColumnValue(),
                'model-class' => self::$invoice->modelClassName(),
                '%subtotals%' => count($breakdowns),
            ]);
            return false;
        }

        $key1 = 'Desglose';
        $key2 = 'DetalleDesglose';

        // recorremos los subtotales calculados
        foreach ($breakdowns as $breakdown) {
            self::$json[$key1][$key2]['Impuesto'] = $breakdown['Impuesto'];
            self::$json[$key1][$key2]['ClaveRegimen'] = $breakdown['ClaveRegimen'];

            // Añadir CalificacionOperacion solo si hay código y no hay exención
            if (!empty($breakdown['CalificacionOperacion']) && empty($breakdown['OperacionExenta'])) {
                self::$json[$key1][$key2]['CalificacionOperacion'] = $breakdown['CalificacionOperacion'];
            }

            // Validar OperacionExenta según las reglas de negocio
            $operacionExentaValida = true;
            if (!empty($breakdown['OperacionExenta'])) {
                // Si Impuesto es '01' (IVA), '03' (IGIC) o no se cumplimenta y ClaveRegimen es 01
                // no pueden marcarse las OperacionExenta E2, E3
                $impuestoRestringido = in_array($breakdown['Impuesto'], ['01', '03']) || empty($breakdown['Impuesto']);
                $claveRegimenRestringida = $breakdown['ClaveRegimen'] === '01';
                $operacionExentaRestringida = in_array($breakdown['OperacionExenta'], ['E2', 'E3']);

                if ($impuestoRestringido && $claveRegimenRestringida && $operacionExentaRestringida) {
                    $operacionExentaValida = false;
                    Tools::log()->error('invalid-operacion-exenta-combination', [
                        'model-code' => self::$invoice->primaryColumnValue(),
                        'model-class' => self::$invoice->modelClassName(),
                        'impuesto' => $breakdown['Impuesto'],
                        'clave-regimen' => $breakdown['ClaveRegimen'],
                        'operacion-exenta' => $breakdown['OperacionExenta'],
                    ]);
                    return false;
                }
            }

            // Añadir OperacionExenta solo si hay código, no hay CalificacionOperacion y es válida
            if (!empty($breakdown['OperacionExenta']) && empty($breakdown['CalificacionOperacion']) && $operacionExentaValida) {
                self::$json[$key1][$key2]['OperacionExenta'] = $breakdown['OperacionExenta'];
            }

            // Si la operacion es exenta no se puede informar ninguno de los campos
            // TipoImpositivo, CuotaRepercutida, TipoRecargoEquivalencia y CuotaRecargoEquivalencia
            if (empty($breakdown['OperacionExenta'])) {
                self::$json[$key1][$key2]['TipoImpositivo'] = number_format($breakdown['TipoImpositivo'], 2, '.', '');
                self::$json[$key1][$key2]['CuotaRepercutida'] = number_format($breakdown['CuotaRepercutida'], 2, '.', '');

                if ($breakdown['TipoRecargoEquivalencia'] > 0) {
                    self::$json[$key1][$key2]['TipoRecargoEquivalencia'] = number_format($breakdown['TipoRecargoEquivalencia'], 2, '.', '');
                    self::$json[$key1][$key2]['CuotaRecargoEquivalencia'] = number_format($breakdown['CuotaRecargoEquivalencia'], 2, '.', '');
                }
            }

            self::$json[$key1][$key2]['BaseImponibleOimporteNoSujeto'] = number_format($breakdown['BaseImponibleOimporteNoSujeto'], 2, '.', '');

            // El campo BaseImponibleACoste solo puede estar cumplimentado si la ClaveRegimen es = '06' o Impuesto = '02' (IPSI) o Impuesto = '05' (Otros).
            if ($breakdown['ClaveRegimen'] == '06' || in_array($breakdown['Impuesto'], ['02', '05'])) {
                self::$json[$key1][$key2]['BaseImponibleACoste'] = number_format($breakdown['BaseImponibleACoste'], 2, '.', '');
            }
        }

        return true;
    }

    private static function jsonDestinatarios(): bool
    {
        // Si TipoFactura es F2 o R5 el bloque Destinatarios no puede estar cumplimentado
        if (in_array(self::$json['TipoFactura'], ['F2', 'R5'])) {
            return true;
        }

        $key1 = 'Destinatarios';
        $key2 = 'IDDestinatario';

        $recipientNif = FiscalNumberValidator::normaliceCifNif(self::$invoice->cifnif, '/^[A-Z0-9]{1,9}$/');
        $recipientName = trim(self::$invoice->nombrecliente);
        $recipientDir = trim(self::$invoice->direccion);
        $recipientCodPostal = trim(self::$invoice->codpostal);
        $recipientCity = trim(self::$invoice->ciudad);
        $recipientProvince = trim(self::$invoice->provincia);
        $recipientCountry = trim(self::$invoice->codpais);
        $countryCode = self::$invoice->codpais;

        if (
            self::$serie->tipo !== 'S'
            && (empty($recipientNif)
                || empty($recipientName)
                || empty($recipientDir)
                || empty($recipientCodPostal)
                || empty($recipientCity)
                || empty($recipientProvince)
                || empty($recipientCountry))
        ) {
            Tools::log()->warning('json-missing-recipient-data', [
                'model-code'      => self::$invoice->primaryColumnValue(),
                'model-class'     => self::$invoice->modelClassName(),
                '%codserie%'      => self::$invoice->codserie,
                '%cifnif%'        => $recipientNif,
                '%nombrecliente%' => $recipientName,
                '%codpais%'       => $recipientCountry,
                '%poblacion%'     => $recipientCity,
                '%codpostal%'     => $recipientCodPostal,
                '%direccion%'     => $recipientDir,
                '%provincia%'     => $recipientProvince,
            ]);

            return false;
        }

        // Si simplificada y sin datos, usar por defecto
        if (self::$serie->tipo === 'S') {
            if (empty($recipientNif)) {
                $recipientNif = '99999999R';
            }
            if (empty($recipientName)) {
                $recipientName = 'Consumidor final';
            }
        } elseif (false === FiscalNumberValidator::validate(self::$invoice->getSubject()->tipoidfiscal, $recipientNif, true)) {
            // si no es simplificada y el NIF no es válido, terminamos
            Tools::log()->warning('json-invalid-recipient-nif', [
                'model-code' => self::$invoice->primaryColumnValue(),
                'model-class' => self::$invoice->modelClassName(),
                '%codserie%' => self::$invoice->codserie,
                '%cifnif%' => $recipientNif,
                '%nombrecliente%' => $recipientName,
            ]);
            return false;
        }

        self::$json[$key1][$key2]['NombreRazon'] = Tools::textBreak($recipientName, 120, '');

        // comprobamos si el NIF es válido y Español
        $isValidNif = FiscalNumberValidator::validate(self::$customer->tipoidfiscal, $recipientNif, true);
        if ($countryCode === 'ESP' && $isValidNif) {
            self::$json[$key1][$key2]['NIF'] = $recipientNif;
            return true;
        }

        $idType = $countryCode === 'ESP' ? '07' : self::getIDType(self::$customer->tipoidfiscal);
        $recipientId = $countryCode === 'ESP' && !$isValidNif
            ? 'DOC-' . self::$invoice->codserie . '-' . self::$invoice->numero
            : $recipientNif;

        if (empty($recipientId)) {
            Tools::log()->warning('json-invoice-missing-idotro', [
                'model-code' => self::$invoice->primaryColumnValue(),
                'model-class' => self::$invoice->modelClassName(),
                '%codserie%' => self::$invoice->codserie,
                '%cifnif%' => $recipientNif,
                '%nombrecliente%' => $recipientName,
                '%countryCode%' => $countryCode,
                '%idType%' => $idType,
                '%recipientId%' => $recipientId,
            ]);
            return false;
        }

        self::$json[$key1][$key2]['IDOtro']['CodigoPais'] = $countryCode;
        self::$json[$key1][$key2]['IDOtro']['IDType'] = $idType;
        self::$json[$key1][$key2]['IDOtro']['ID'] = $recipientId;
        return true;
    }

    private static function jsonFacturaSimplificadaArt7273(): bool
    {
        $invoiceCifNIf = FiscalNumberValidator::normaliceCifNif(self::$invoice->cifnif, '/^[A-Z0-9]{1,9}$/');

        // comprobamos si es una factura simplificada
        if (self::$serie->tipo === 'S') {
            // comprobamos si hay datos del cliente
            if (!empty(self::$invoice->nombrecliente) && !empty($invoiceCifNIf)) {
                $facturaSimplificadaArt7273 = 'S';
            } else {
                $facturaSimplificadaArt7273 = 'N';
            }
        } else {
            // comprobamos si hay datos del cliente
            if (!empty(self::$invoice->nombrecliente) && !empty($invoiceCifNIf)) {
                $facturaSimplificadaArt7273 = 'N';
            } else {
                $facturaSimplificadaArt7273 = 'N';
            }
        }

        self::$json['FacturaSimplificadaArt7273'] = $facturaSimplificadaArt7273;
        return true;
    }

    private static function jsonFacturaSinIdentifDestinatarioArt61d(): bool
    {
        $invoiceCifNIf = FiscalNumberValidator::normaliceCifNif(self::$invoice->cifnif, '/^[A-Z0-9]{1,9}$/');

        // comprobamos si es una factura simplificada
        if (self::$serie->tipo === 'S') {
            // comprobamos si hay datos del cliente
            if (!empty(self::$invoice->nombrecliente) && !empty($invoiceCifNIf)) {
                $facturaSinIdentifDestinatarioArt61d = 'N';
            } else {
                $facturaSinIdentifDestinatarioArt61d = 'S';
            }
        } else {
            // comprobamos si hay datos del cliente
            if (!empty(self::$invoice->nombrecliente) && !empty($invoiceCifNIf)) {
                $facturaSinIdentifDestinatarioArt61d = 'N';
            } else {
                $facturaSinIdentifDestinatarioArt61d = 'S';
            }
        }

        self::$json['FacturaSinIdentifDestinatarioArt61d'] = $facturaSinIdentifDestinatarioArt61d;
        return true;
    }

    private static function jsonFechaOperacion(): bool
    {
        $date = self::$invoice->fechadevengo ?? self::$invoice->fecha;
        self::$json['FechaOperacion'] = date('d-m-Y', strtotime($date));
        return true;
    }

    private static function jsonIDFactura(): bool
    {
        self::$json['IDFactura']['IDEmisorFactura'] = FiscalNumberValidator::normaliceCifNif(self::$company->cifnif, '/^[A-Z0-9]{1,9}$/');
        self::$json['IDFactura']['NumSerieFactura'] = Tools::textBreak(self::$invoice->codserie . self::$invoice->numero, 60, '');
        self::$json['IDFactura']['FechaExpedicionFactura'] = date('d-m-Y', strtotime(self::$invoice->fecha));
        return true;
    }

    private static function jsonImporteTotal(): bool
    {
        self::$json['ImporteTotal'] = number_format(self::$invoice->total, 2, '.', '');
        return true;
    }

    private static function jsonNombreRazonEmisor(): bool
    {
        self::$json['NombreRazonEmisor'] = Tools::textBreak(self::$company->nombre, 120, '');
        return true;
    }

    private static function jsonRechazoPrevio(): bool
    {
        // si el ejercicio de la factura está en modo no-vertifactu, terminamos
        if (self::$exercise->vf_mode === 'no-verifactu') {
            return true;
        }

        // si existe el campo Subsanacion y no tiene valor S, terminamos
        if (isset(self::$json['Subsanacion']) && self::$json['Subsanacion'] !== 'S') {
            return true;
        }

        if (self::$event === VerifactuRegistroFactura::EVENT_SUBSANACION) {
            // si es una subsanación, comprobamos si hay rechazo previo
            self::$json['RechazoPrevio'] = self::$invoice->vf_intents_subsanacion > 0 ? 'S' : 'N';
        } elseif (self::$event === VerifactuRegistroFactura::EVENT_ALTA) {
            // si es un alta, comprobamos si hay rechazo previo
            self::$json['RechazoPrevio'] = self::$invoice->vf_intents_alta > 0 ? 'S' : 'N';
        } else {
            // evento no válido
            return false;
        }

        return true;
    }

    private static function jsonSubsanacion(): bool
    {
        self::$json['Subsanacion'] = self::$event === VerifactuRegistroFactura::EVENT_SUBSANACION ? 'S' : 'N';
        return true;
    }

    private static function jsonTipoFactura(): bool
    {
        // si hay factura rectificativa y la serie de la rectificativa es simplificada, entonces R5
        if (false === empty(self::$invoiceRefund->primaryColumnValue()) && self::$invoiceRefund->getSerie()->tipo === 'S') {
            $tipoFactura = 'R5';
        } elseif (false === empty(self::$invoiceRefund->primaryColumnValue())) {
            // si hay factura rectificativa y la serie de la rectificativa es no simplificada, entonces R4
            $tipoFactura = 'R4';
        } elseif (self::$serie->tipo === 'S') {
            // si es una factura simplificada y no hay factura rectificativa, entonces F2
            $tipoFactura = 'F2';
        } else {
            // si es una factura no simplificada y no hay factura rectificativa, entonces F1
            $tipoFactura = 'F1';
        }

        self::$json['TipoFactura'] = $tipoFactura;
        return true;
    }

    private static function jsonTipoRectificativa(): bool
    {
        // si no hay factura rectificativa, terminamos
        if (empty(self::$invoiceRefund->primaryColumnValue())) {
            return true;
        }

        self::$json['TipoRectificativa'] = self::getTypeRectificativa();
        if (self::$json['TipoRectificativa'] === 'S') {
            $facturasRect = 'FacturasRectificadas';
            $idFactura = 'IDFacturaRectificada';
        } else {
            $facturasRect = 'FacturasSustituidas';
            $idFactura = 'IDFacturaSustituida';
        }

        // Debe informarse el campo FacturasSustituidas solo si la factura es de tipo F3
        if ($facturasRect === 'FacturasSustituidas' && self::$json['TipoFactura'] !== 'F3') {
            return true;
        }

        self::$json[$facturasRect][$idFactura]['IDEmisorFactura'] = FiscalNumberValidator::normaliceCifNif(self::$invoiceRefund->getCompany()->cifnif, '/^[A-Z0-9]{1,9}$/');
        self::$json[$facturasRect][$idFactura]['NumSerieFactura'] = Tools::textBreak(self::$invoiceRefund->codserie . self::$invoiceRefund->numero, 60, '');
        self::$json[$facturasRect][$idFactura]['FechaExpedicionFactura'] = date('d-m-Y', strtotime(self::$invoiceRefund->fecha));

        self::$json['ImporteRectificacion']['BaseRectificada'] = number_format(self::$invoiceRefund->neto, 2, '.', '');
        self::$json['ImporteRectificacion']['CuotaRectificada'] = number_format(self::$invoiceRefund->totaliva, 2, '.', '');
        self::$json['ImporteRectificacion']['CuotaRecargoRectificado'] = number_format(self::$invoiceRefund->totalrecargo, 2, '.', '');

        return true;
    }
}
