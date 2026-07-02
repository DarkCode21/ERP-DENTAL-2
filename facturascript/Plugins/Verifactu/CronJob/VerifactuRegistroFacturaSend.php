<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\CronJob;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Template\CronJobClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\ApiClient;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\JsonTrait;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRegistroFactura as ModelVerifactuRegistroFactura;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRequerimiento;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRequerimientoLine;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuErrorLog;
use SoapVar;

/**
 * Clase para generar el hash y firma en la cola de registros de factura y enviarlos a Verifactu.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class VerifactuRegistroFacturaSend extends CronJobClass
{
    use JsonTrait;

    const JOB_NAME = 'verifactu-invoice-send';

    /** @var array */
    private static $data = [];

    /** @var DataBase */
    private static $db;

    /** @var array */
    private static $registerSent = [];

    public static function run(): void
    {
        self::$db = new DataBase();
        self::echo("\n\n* JOB: " . self::JOB_NAME . ' ...');

        if (false === self::checkTime()) {
            self::saveEcho();
            return;
        }

        // recorremos las empresas de Verifactu
        foreach (self::getCompanies() as $company) {
            self::echo("\n- Empresa: " . $company->nombrecorto);

            // si la empresa no está configurada, saltamos
            if (false === $company->verifactuIsConfigured(false)) {
                self::echo("\n-- Empresa no configurada");
                continue;
            }

            self::$data[$company->idempresa] = [
                'idempresa' => $company->idempresa,
                'invoices' => [],
            ];

            self::queueRequirements($company);
            self::queueInvoices($company);
            self::sendInvoices($company);
        }

        self::saveEcho();
    }

    private static function checkTime(): bool
    {
        // obtenemos la fecha anterior guardada en settings
        $lastDate = Tools::settings('verifactu', 'send_last_date');

        // si no hay última fecha de envío, permitimos enviar
        if (empty($lastDate)) {
            self::echo("\n-- No hay última fecha de envío, permitimos enviar");
            return true;
        }

        // si la fecha actual es mayor a la última fecha de envío, permitimos enviar
        if (strtotime(Tools::dateTime()) > strtotime($lastDate)) {
            self::echo("\n-- Fecha actual es mayor a la última fecha de envío, permitimos enviar");
            return true;
        }

        // si la fecha actual es menor o igual a la última fecha de envío, no permitimos enviar
        self::echo("\n-- Fecha actual es menor o igual a la última fecha de envío, no permitimos enviar");
        return false;
    }

    private static function getCompanies(): array
    {
        $where = [
            new DataBaseWhere('vf_certificate', null, 'IS NOT'),
            new DataBaseWhere('vf_password', null, 'IS NOT'),
        ];
        return Empresa::all($where);
    }

    private static function queueInvoices(Empresa $company): void
    {
        // si hay requerimiento, terminamos
        if (!empty(self::$data[$company->idempresa]['requirement'])) {
            self::echo("\n-- Requerimiento pendiente, no se procesarán las facturas de la cola");
            return;
        }

        // recorremos la cola de registros de facturas pendientes de la empresa
        $where = [
            new DataBaseWhere('idempresa', $company->idempresa),
            new DataBaseWhere('mode', 'verifactu'),
            new DataBaseWhere('status', null),
            new DataBaseWhere('hash', null, 'IS NOT'),
        ];
        foreach (ModelVerifactuRegistroFactura::all($where, ['id' => 'ASC'], 0, 1000) as $regInvoice) {
            // añadimos el registro de factura a la cola de envíos
            self::$data[$company->idempresa]['invoices'][] = $regInvoice;
        }
    }

    private static function queueRequirements(Empresa $company): void
    {
        // obtenemos los requerimientos de la empresa que no estén completados
        $where = [
            new DataBaseWhere('idempresa', $company->idempresa),
            new DataBaseWhere('status', VerifactuRequerimiento::STATUS_COMPLETED, '!='),
        ];
        $requirements = VerifactuRequerimiento::all($where, ['id' => 'ASC']);

        // si no hay requerimientos, terminamos
        if (empty($requirements)) {
            self::echo("\n-- No hay requerimientos");
            return;
        }

        // recorremos solo el primer requerimiento
        if (count($requirements) > 1) {
            self::echo("\n-- Hay más de un requerimiento, se procesará el primero");
        }

        // guardamos el primer requerimiento
        $requirement = $requirements[0];

        // guardamos el requerimiento en los datos de la empresa
        self::$data[$company->idempresa]['requirement'] = $requirement->reference;
        self::echo("\n-- Requerimiento: " . $requirement->reference);

        // obtenemos las líneas del requerimiento pendientes de enviar
        $where = [
            new DataBaseWhere('id_requerimiento', $requirement->id),
            new DataBaseWhere('status', null),
        ];
        $linesRequirement = VerifactuRequerimientoLine::all($where, ['id' => 'ASC']);
        foreach ($linesRequirement as $line) {
            // si hay más de 1000 facturas, no añadimos más
            if (count(self::$data[$company->idempresa]['invoices']) > 1000) {
                break;
            }

            // si el registro de factura no tiene hash, terminamos
            if (empty($line->getRegistroFactura()->hash)) {
                break;
            }

            // añadimos la línea del requerimiento a las facturas a enviar
            self::$data[$company->idempresa]['invoices'][] = $line;
        }

        // añadimos el fin del requerimiento
        // debemos comprobar si quedan más facturas pendientes de las añadidas
        self::$data[$company->idempresa]['requirement_end'] = count(self::$data[$company->idempresa]['invoices']) >= count($linesRequirement);

        // si hay facturas a enviar, terminamos
        if (!empty(self::$data[$company->idempresa]['invoices'])) {
            return;
        }

        // si no hay facturas a enviar, marcamos el requerimiento como completado
        $db = new DataBase();
        $db->exec('UPDATE ' . VerifactuRequerimiento::tableName()
            . ' SET status = ' . $db->var2str(VerifactuRequerimiento::STATUS_COMPLETED)
            . ' WHERE id = ' . $requirement->id);

        unset(self::$data[$company->idempresa]['requirement']);
        unset(self::$data[$company->idempresa]['requirement_end']);
        self::echo("\n-- No hay facturas pendientes para enviar, marcado el requerimiento como completado");
    }

    private static function responseInvoices(Empresa $company, array $response): void
    {
        // procesamos los errores manuales
        foreach ($response['errors'] as $error) {
            self::echo("\n-- Error manual: " . $error);
            
            // Obtener información de contexto
            $invoicesCount = isset(self::$data[$company->idempresa]['invoices']) ? 
                           count(self::$data[$company->idempresa]['invoices']) : 0;
            $firstInvoiceId = null;
            $invoiceIds = [];
            
            if ($invoicesCount > 0) {
                foreach (self::$data[$company->idempresa]['invoices'] as $invoice) {
                    if ($invoice instanceof ModelVerifactuRegistroFactura) {
                        $invoiceIds[] = $invoice->id;
                        if ($firstInvoiceId === null) {
                            $firstInvoiceId = $invoice->id;
                        }
                    }
                }
            }
            
            // Log error to database
            VerifactuErrorLog::logError(
                $company->idempresa,
                self::JOB_NAME,
                'SEND_MANUAL_ERROR',
                $error,
                [
                    'response_data' => $response,
                    'step' => 'invoice_send_response',
                    'total_invoices_attempted' => $invoicesCount,
                    'invoice_ids_attempted' => $invoiceIds,
                    'company_name' => $company->nombre,
                    'first_invoice_id' => $firstInvoiceId
                ]
            );
        }

        $correcto = 0;
        $aceptadoConErrores = 0;

        // procesamos las facturas
        foreach ($response['invoices'] as $refExterna => $responseInvoice) {
            // comprobamos si la factura está en la cola
            if (!isset(self::$registerSent[$refExterna])) {
                self::echo("\n--- Factura no encontrada en la cola: " . $refExterna);
                continue;
            }

            // obtenemos el RegistroFactura o VerifactuRequerimientoLine del registro enviado
            $item = self::$registerSent[$refExterna];

            // sumamos los contadores según el estado del registro
            switch ($responseInvoice['EstadoRegistro']) {
                case 'Correcto':
                    $correcto++;
                    $item->status = $responseInvoice['EstadoRegistro'];
                    break;

                case 'AceptadoConErrores':
                    $aceptadoConErrores++;
                    $item->status = $responseInvoice['EstadoRegistro'];
                    break;

                case 'Incorrecto':
                    break;

                default:
                    self::echo("\n--- Estado desconocido para la factura: " . $refExterna . ' - ' . $responseInvoice['EstadoRegistro']);
                    break;
            }

            // actualizamos el registro
            if (!$item->save()) {
                self::echo("\n--- Error al actualizar el estado del registro de la factura: " . $refExterna);
            }

            // obtenemos la factura original
            $invoice = $item->getInvoice();
            if (empty($invoice->primaryColumnValue())) {
                self::echo("\n--- Factura original no encontrada: " . $refExterna);
                continue;
            }

            // si la operación es una Alta marcamos la factura como enviada
            if ($responseInvoice['Operacion'] === 'Alta') {
                $sqlInvoice = 'UPDATE ' . $invoice->tableName()
                    . ' SET vf_sent = true'
                    . ' WHERE ' . $invoice->primaryColumn() . ' = ' . $invoice->primaryColumnValue();
                if (!self::$db->exec($sqlInvoice)) {
                    self::echo("\n--- Error al marcar la factura como enviada: " . $refExterna);
                }
            }

            // si no hay error en la respuesta del envío de esta factura, continuamos
            if (empty($responseInvoice['DescripcionErrorRegistro'])) {
                continue;
            }

            // pintamos el mensaje de error
            self::echo("\n--- Error en la factura " . $refExterna . ': ' . $responseInvoice['CodigoErrorRegistro'] . ' - ' . $responseInvoice['DescripcionErrorRegistro']);

            // si hay requerimiento, no actualizamos la factura original
            if (!empty(self::$data[$company->idempresa]['requirement'])) {
                // comprobamos si hay que marcar el requerimiento como completado
                if (self::$data[$company->idempresa]['requirement_end']) {
                    $db = new DataBase();
                    $db->exec('UPDATE ' . VerifactuRequerimiento::tableName()
                        . ' SET status = ' . $db->var2str(VerifactuRequerimiento::STATUS_COMPLETED)
                        . ' WHERE id = ' . self::$data[$company->idempresa]['requirement']);
                    unset(self::$data[$company->idempresa]['requirement']);
                    unset(self::$data[$company->idempresa]['requirement_end']);
                }
                continue;
            }

            // si el error de la factura es el código 3000 - Registro de facturación duplicado
            // entonces marcamos la factura como enviada y el registro como correcto
            if ((int)$responseInvoice['CodigoErrorRegistro'] === 3000) {
                $sqlInvoice = 'UPDATE ' . $invoice->tableName()
                    . ' SET vf_sent = true'
                    . ' WHERE ' . $invoice->primaryColumn() . ' = ' . $invoice->primaryColumnValue();
                if (!self::$db->exec($sqlInvoice)) {
                    self::echo("\n--- Error al marcar la factura como enviada por error 3000: " . $refExterna);
                } else {
                    self::echo("\n--- Marcada la factura como enviada por error 3000: " . $refExterna);
                }

                $correcto++;
                $item->status = 'Correcto';
                if (!$item->save()) {
                    self::echo("\n--- Error al actualizar el estado del registro de la factura por error 3000: " . $refExterna);
                } else {
                    self::echo("\n--- Actualizado el estado del registro de la factura como Correcto por error 3000: " . $refExterna);
                }
            }

            // actualizamos los intentos dependiendo del tipo de envío
            switch ($responseInvoice['Operacion']) {
                case 'Alta':
                    $intents = is_null($invoice->vf_intents_alta) ? 0 : $invoice->vf_intents_alta++;
                    $sql = 'UPDATE ' . $invoice->tableName()
                        . ' SET vf_intents_alta = ' . $intents
                        . ' WHERE ' . $invoice->primaryColumn() . ' = ' . $invoice->primaryColumnValue();
                    break;

                case 'Subsanacion':
                    $intents = is_null($invoice->vf_intents_subsanacion) ? 0 : $invoice->vf_intents_subsanacion++;
                    $sql = 'UPDATE ' . $invoice->tableName()
                        . ' SET vf_intents_subsanacion = ' . $intents
                        . ' WHERE ' . $invoice->primaryColumn() . ' = ' . $invoice->primaryColumnValue();
                    break;

                case 'Anulacion':
                    $intents = is_null($invoice->vf_intents_anulacion) ? 0 : $invoice->vf_intents_anulacion++;
                    $sql = 'UPDATE ' . $invoice->tableName()
                        . ' SET vf_intents_anulacion = ' . $intents
                        . ' WHERE ' . $invoice->primaryColumn() . ' = ' . $invoice->primaryColumnValue();
                    break;

                default:
                    self::echo("\n--- Operación desconocida para la factura: " . $refExterna . ' - ' . $responseInvoice['Operacion']);
                    continue 2; // saltamos al siguiente registro
            }

            // guardamos la factura original
            if (!self::$db->exec($sql)) {
                self::echo("\n--- Error al actualizar los intentos de la factura: " . $refExterna);
            }
        }

        // pintamos los contadores
        self::echo("\n-- Resultados del envío de facturas: Correctas " . $correcto
            . ', Aceptadas con errores ' . $aceptadoConErrores
            . ', Incorrectas ' . (count(self::$data[$company->idempresa]['invoices']) - $correcto - $aceptadoConErrores));
    }

    private static function sendInvoices(Empresa $company): void
    {
        // si no hay facturas a enviar, terminamos
        if (empty(self::$data[$company->idempresa]['invoices'])) {
            self::echo("\n-- No hay facturas pendientes para enviar");
            return;
        }

        if (false === self::jsonCabecera($company)) {
            return;
        } elseif (false === self::jsonRegistroFactura($company)) {
            return;
        }

        self::echo("\n-- Total de facturas a enviar: " . count(self::$data[$company->idempresa]['invoices']));

        // enviamos el JSON a Verifactu
        try {
            if (!empty(self::$data[$company->idempresa]['requirement'])) {
                $respuesta = ApiClient::sendRequirementBatch($company, self::$json);
            } else {
                $respuesta = ApiClient::sendInvoiceBatch($company, self::$json);
            }

            self::responseInvoices($company, $respuesta);
        } catch (\SoapFault $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            
            self::echo("\n-- Error SOAP: Código {$errorCode} - {$errorMessage}");
            
            // Manejar específicamente el error 401 de certificado
            if ($errorCode == 401) {
                VerifactuErrorLog::logError(
                    $company->idempresa,
                    self::JOB_NAME,
                    'CERTIFICATE_ERROR',
                    "Certificado no válido: {$errorMessage}",
                    [
                        'soap_error_code' => $errorCode,
                        'company_nif' => $company->cifnif,
                        'certificate_info' => $company->vf_certificate ?? 'No disponible',
                        'invoices_attempted' => count(self::$data[$company->idempresa]['invoices']),
                        'step' => 'soap_send'
                    ]
                );
            } else {
                // Para otros errores SOAP
                VerifactuErrorLog::logError(
                    $company->idempresa,
                    self::JOB_NAME,
                    'SOAP_ERROR',
                    "Error SOAP {$errorCode}: {$errorMessage}",
                    [
                        'soap_error_code' => $errorCode,
                        'invoices_attempted' => count(self::$data[$company->idempresa]['invoices']),
                        'step' => 'soap_send'
                    ]
                );
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            self::echo("\n-- Error general: {$errorMessage}");
            
            VerifactuErrorLog::logError(
                $company->idempresa,
                self::JOB_NAME,
                'SEND_ERROR',
                "Error en envío: {$errorMessage}",
                [
                    'exception_class' => get_class($e),
                    'invoices_attempted' => count(self::$data[$company->idempresa]['invoices']),
                    'step' => 'general_send'
                ]
            );
        }
    }

    private static function jsonCabecera(Empresa $company): bool
    {
        if (false === self::jsonObligadoEmision('Cabecera', $company)) {
            self::echo("\n-- Error al crear el obligado de emisión");
            return false;
        } elseif (false === self::jsonRemisionVoluntaria($company)) {
            self::echo("\n-- Error al crear la remisión voluntaria");
            return false;
        } elseif (false === self::jsonRemisionRequerimiento($company)) {
            self::echo("\n-- Error al crear la remisión del requerimiento");
            return false;
        }

        return true;
    }

    private static function jsonRegistroFactura(Empresa $company): bool
    {
        foreach (self::$data[$company->idempresa]['invoices'] as $item) {
            // el item puedes ser un RegistroFactura o una VerifactuRequerimientoLine
            // dependiendo de si hay requerimiento o no
            if ($item instanceof ModelVerifactuRegistroFactura) {
                $regInvoice = $item;
            } elseif ($item instanceof VerifactuRequerimientoLine) {
                $regInvoice = $item->getRegistroFactura();
            } else {
                self::echo("\n-- Registro de factura no válido: " . get_class($item));
                break;
            }

            // obtenemos el evento del registro de factura
            $event = $regInvoice->event === ModelVerifactuRegistroFactura::EVENT_ANULACION
                ? 'RegistroAnulacion' : 'RegistroAlta';

            // Leemos y decodificamos el contenido del JSON
            $fileContent = file_get_contents($regInvoice->file_json);
            if ($fileContent === false) {
                self::echo("\n-- Error al leer el archivo JSON de la factura: " . $regInvoice->file_json);
                break;
            }

            $invoiceJson = json_decode($fileContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                self::echo("\n-- Error al decodificar el JSON de la factura: " . json_last_error_msg());
                break;
            }

            // si el IDEmisorFactura o IDEmisorFacturaAnulada es diferente al de la empresa, no podemos enviar
            $invoiceNif = $invoiceJson[$event]['IDFactura']['IDEmisorFactura'] ?? $invoiceJson[$event]['IDFactura']['IDEmisorFacturaAnulada'] ?? '';
            $companyNif = self::$json['Cabecera']['ObligadoEmision']['NIF'] ?? '';
            
            if ((isset($invoiceJson[$event]['IDFactura']['IDEmisorFactura']) && $invoiceJson[$event]['IDFactura']['IDEmisorFactura'] !== $companyNif)
                || (isset($invoiceJson[$event]['IDFactura']['IDEmisorFacturaAnulada']) && $invoiceJson[$event]['IDFactura']['IDEmisorFacturaAnulada'] !== $companyNif)) {
                
                $errorMsg = "NIF del certificado no coincide con el de la factura. Certificado: {$companyNif}, Factura: {$invoiceNif}";
                self::echo("\n-- " . $errorMsg);
                
                // Log error to database
                VerifactuErrorLog::logError(
                    $company->idempresa,
                    self::JOB_NAME,
                    'CERTIFICATE_ERROR',
                    $errorMsg,
                    [
                        'company_nif' => $company->cifnif,
                        'certificate_nif' => $companyNif,
                        'invoice_nif' => $invoiceNif,
                        'invoice_file' => $regInvoice->file_json,
                        'step' => 'nif_validation'
                    ]
                );
                break;
            }

            // si existe el campo Generador, validamos que el NIF sea distinto al del ObligadoEmision
            if (isset($invoiceJson[$event]['Generador']['NIF']) && $invoiceJson[$event]['Generador']['NIF'] === self::$json['Cabecera']['ObligadoEmision']['NIF']) {
                self::echo("\n-- NIF del Generador no puede ser el mismo que el del ObligadoEmision: " . $invoiceJson[$event]['Generador']['NIF']);
                break;
            }

            // convertimos la firma del JSON a un objeto SoapVar
            if (!empty($invoiceJson[$event]['Signature'])) {
                $invoiceJson[$event]['Signature'] = new SoapVar(
                    $invoiceJson[$event]['Signature'],
                    XSD_ANYXML
                );
            }

            // Añadimos el JSON de la factura al JSON general
            self::$json['RegistroFactura'][] = $invoiceJson;

            // nos guardamos la correlación de las facturas que mandamos para luego poder comprobar y actualizar el registro
            // hay que volver a tener en cuenta el tipo de item para guardar el RegistroFactura o VerifactuRequerimientoLine
            if ($item instanceof ModelVerifactuRegistroFactura) {
                self::$registerSent[$invoiceJson[$event]['RefExterna']] = $regInvoice;
            } elseif ($item instanceof VerifactuRequerimientoLine) {
                self::$registerSent[$invoiceJson[$event]['RefExterna']] = $item;
            } else {
                self::echo("\n-- Registro de factura no válido para guardar en la cola: " . get_class($item));
                break;
            }
        }

        // si no se han añadido registros, terminamos
        if (!isset(self::$json['RegistroFactura']) || count(self::$json['RegistroFactura']) === 0) {
            self::echo("\n-- No se han añadido registros de factura al JSON");
            return false;
        }

        self::echo("\n-- Registros de factura añadidos: " . count(self::$json['RegistroFactura']));
        return true;
    }

    private static function jsonRemisionRequerimiento(Empresa $company): bool
    {
        // si no hay requerimiento, terminamos
        if (empty(self::$data[$company->idempresa]['requirement'])) {
            return true;
        }

        self::$json['Cabecera']['RemisionRequerimiento'] = [
            'RefRequerimiento' => Tools::textBreak(self::$data[$company->idempresa]['requirement'], 18, ''),
            'FinRequerimiento' => self::$data[$company->idempresa]['requirement_end'] ? 'S' : 'N',
        ];
        return true;
    }

    private static function jsonRemisionVoluntaria(Empresa $company): bool
    {
        // obtenemos los ejercicios de la empresa que tenga configurado Verifactu
        $where = [
            new DataBaseWhere('idempresa', $company->idempresa),
            new DataBaseWhere('vf_mode', null, 'IS NOT'),
        ];
        $exercises = Ejercicio::all($where, ['fechafin' => 'DESC']);

        // Recorrer todos los ejercicios para guardar el ejercicio del año actual
        $currentYear = (int)date('Y');
        $currentExercise = null;
        $higherExercise = null;
        foreach ($exercises as $exercise) {
            $year = (int)date('Y', strtotime($exercise->fechafin));
            if ($year === $currentYear) {
                $currentExercise = $exercise;
            } elseif ($year > $currentYear) {
                // Guardar el ejercicio con año más alto que el actual (solo el primero más alto)
                if ($higherExercise === null || $year > (int)date('Y', strtotime($higherExercise->fechafin))) {
                    $higherExercise = $exercise;
                }
            }
        }

        // Si no hay ejercicio actual o no hay ejercicio más alto, terminamos sin hacer nada
        if (!$currentExercise || !$higherExercise) {
            return true;
        }

        // Si el ejercicio actual tiene vf_mode = 'verifactu' y el más alto tiene vf_mode = 'no-verifactu', añadimos campo nuevo
        if ($currentExercise->vf_mode === 'verifactu' && $higherExercise->vf_mode === 'no-verifactu') {
            self::$json['Cabecera']['RemisionVoluntaria'] = [
                'FechaFinVerifactu' => date('d-m-Y', strtotime($currentExercise->fechafin)),
            ];
        }
        return true;
    }
}