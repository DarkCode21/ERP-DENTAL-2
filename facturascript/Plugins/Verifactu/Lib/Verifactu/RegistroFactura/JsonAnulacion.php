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
 * Clase para generar el XML de anulación de una factura en Verifactu.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class JsonAnulacion
{
    use JsonTrait;
    use JsonRegistroFacturaTrait;

    public static function generate(FacturaCliente $invoice): bool
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

            // comprobamos si la factura no está dada de alta
            if (false === $invoice->verifactuCheckAlta()) {
                Tools::log()->warning('verifactu-invoice-not-high', [
                    'model-code' => $invoice->primaryColumnValue(),
                    'model-class' => $invoice->modelClassName(),
                ]);
                return false;
            }

            // comprobamos si la factura ya está anulada
            if ($invoice->verifactuCheckAnulacion()) {
                Tools::log()->warning('verifactu-invoice-already-annulled', [
                    'model-code' => $invoice->primaryColumnValue(),
                    'model-class' => $invoice->modelClassName(),
                ]);
                return false;
            }

            // cargamos los datos generales
            self::loadData($invoice);

            if (false === self::jsonIDVersion()) {
                return false;
            } elseif (false === self::jsonIDFactura()) {
                return false;
            } elseif (false === self::jsonRefExterna()) {
                return false;
            } elseif (false === self::jsonSinRegistroPrevio()) {
                return false;
            } elseif (false === self::jsonRechazoPrevio()) {
                return false;
            } elseif (false === self::jsonGenerado()) {
                return false;
            } elseif (false === self::jsonSistemaInformatico()) {
                return false;
            } elseif (false === self::jsonFechaHoraHusoGenRegistro()) {
                return false;
            }

            // creamos el array JSON
            $data = self::$json;
            self::$json = [];
            self::$json['RegistroAnulacion'] = $data;

            // DEBUG: Log del JSON completo antes de validar
            Tools::log()->info('DEBUG JsonAnulacion - JSON generado:', [
                'json_completo' => json_encode(self::$json, JSON_PRETTY_PRINT),
                'has_generador' => isset(self::$json['RegistroAnulacion']['Generador']),
                'generador_content' => self::$json['RegistroAnulacion']['Generador'] ?? 'NO_EXISTE'
            ]);

            // Validamos el JSON
            if (!JsonValidate::validate(self::$json)) {
                Tools::log()->error('DEBUG JsonAnulacion - Validación falló');
                return false;
            }

            // creamos el archivo JSON
            if (false === self::createFile(VerifactuRegistroFactura::EVENT_ANULACION)) {
                return false;
            }

            // creamos el evento de registro de factura
            if (false === self::createEvent(VerifactuRegistroFactura::EVENT_ANULACION)) {
                return false;
            }

            // generamos el hash y encadenamiento
            $regInvoice = new VerifactuRegistroFactura();
            $where = [
                new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('idfactura', self::$invoice->idfactura),
                new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('event', VerifactuRegistroFactura::EVENT_ANULACION)
            ];
            if ($regInvoice->loadFromCode('', $where, ['id' => 'DESC'])) {
                Hash::generate($regInvoice);
            }

            return true;
        } catch (Exception $e) {
            Tools::log()->error('xml-annulment-error', [
                '%error%' => $e->getMessage(),
                'model-code' => self::$invoice->primaryColumnValue(),
                'model-class' => self::$invoice->modelClassName(),
            ]);
            return false;
        }
    }

    private static function jsonGenerado(): bool
    {
        $key = 'Generador';
        
        // verificamos si tenemos evento de alta previo
        $tieneEventoAlta = false;
        foreach (self::$invoice->verifactuGetRegistroFactura() as $registroFactura) {
            if ($registroFactura->event === VerifactuRegistroFactura::EVENT_ALTA) {
                $tieneEventoAlta = true;
                break;
            }
        }
        
        // Si tenemos evento de alta, es generado por nosotros (E), si no, por tercero (T)
        self::$json['GeneradoPor'] = $tieneEventoAlta ? 'E' : 'T';
        
        // El Generador debe ser el sistema informático cuando es generado por nosotros (E)
        // para evitar que el NIF del Generador sea igual al del ObligadoEmision
        if (self::$json['GeneradoPor'] === 'E') {
            // Usamos los datos del sistema informático como Generador
            self::$json[$key]['NombreRazon'] = 'MIGUEL ANGEL PEREZ SOLA';
            self::$json[$key]['NIF'] = '76144075E';
        } else {
            // Si es generado por tercero, usamos los datos de la empresa
            self::$json[$key]['NombreRazon'] = Tools::textBreak(self::$company->nombre, 120, '');

            $companyCifNif = FiscalNumberValidator::normaliceCifNif(self::$company->cifnif, '/^[A-Z0-9]{1,9}$/');
            $isValid = FiscalNumberValidator::validate(self::$company->tipoidfiscal, $companyCifNif, true);

            if ($isValid && $companyCifNif) {
                self::$json[$key]['NIF'] = $companyCifNif;
            } else {
                // Si no tenemos NIF válido, usamos IDOtro
                $countryCode = self::$company->codpais ?: 'ESP';
                $idType = self::getIDType(self::$company->tipoidfiscal);
                $recipientId = !empty($companyCifNif) ? $companyCifNif : 
                              (!empty(self::$company->cifnif) ? self::$company->cifnif : 
                               'COMP-' . self::$company->idempresa);

                if (empty($recipientId)) {
                    Tools::log()->error('recipientId está vacío para Generador', [
                        'model-code' => self::$company->primaryColumnValue(),
                        'model-class' => self::$company->modelClassName(),
                    ]);
                    return false;
                }

                self::$json[$key]['IDOtro']['CodigoPais'] = $countryCode;
                self::$json[$key]['IDOtro']['IDType'] = $idType;
                self::$json[$key]['IDOtro']['ID'] = $recipientId;
            }
        }
        
        return true;
    }

    private static function jsonIDFactura(): bool
    {
        self::$json['IDFactura']['IDEmisorFacturaAnulada'] = FiscalNumberValidator::normaliceCifNif(self::$company->cifnif, '/^[A-Z0-9]{1,9}$/');
        self::$json['IDFactura']['NumSerieFacturaAnulada'] = Tools::textBreak(self::$invoice->codserie . self::$invoice->numero, 60, '');
        self::$json['IDFactura']['FechaExpedicionFacturaAnulada'] = date('d-m-Y', strtotime(self::$invoice->fecha));
        return true;
    }

    private static function jsonRechazoPrevio(): bool
    {
        // si el ejercicio de la factura está en modo no-vertifactu, terminamos
        if (self::$exercise->vf_mode === 'no-verifactu') {
            return true;
        }

        self::$json['RechazoPrevio'] = self::$invoice->vf_intents_anulacion > 0 ? 'S' : 'N';
        return true;
    }

    private static function jsonSinRegistroPrevio(): bool
    {
        self::$json['SinRegistroPrevio'] = self::$invoice->verifactuCheckAlta() ? 'N' : 'S';
        return true;
    }
}