<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\CronJob;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Template\CronJobClass;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\JsonTrait;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroFactura\Hash;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroFactura\Signature;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRegistroFactura as ModelVerifactuRegistroFactura;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuErrorLog;

/**
 * Clase para generar el hash y firma de los registros de facturas de Verifactu.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class VerifactuRegistroFacturaHashSignature extends CronJobClass
{
    use JsonTrait;

    const JOB_NAME = 'verifactu-invoice-hash-signature';

    public static function run(): void
    {
        self::echo("\n\n* JOB: " . self::JOB_NAME . ' ...');

        // recorremos las empresas de Verifactu
        foreach (self::getCompanies() as $company) {
            self::echo("\n- Empresa: " . $company->nombrecorto . " (ID: " . $company->idempresa . ")");

            // si la empresa no está configurada, saltamos
            if (false === $company->verifactuIsConfigured(false)) {
                self::echo("\n-- Empresa no configurada");
                continue;
            }

            $regInvoices = self::getRegInvoices($company);
            self::echo("\n-- Facturas sin hash encontradas: " . count($regInvoices));
            
            foreach ($regInvoices as $regInvoice) {
                self::echo("\n--- Procesando factura ID: " . $regInvoice->id . " - JSON: " . $regInvoice->file_json);
                
                // generamos la huella del JSON
                try {
                    self::echo("\n--- Intentando generar hash...");
                    $hashResult = Hash::generate($regInvoice);
                    self::echo("\n--- Hash::generate retornó: " . ($hashResult ? 'TRUE' : 'FALSE'));
                    
                    if (false === $hashResult) {
                        $errorMessage = "Error al generar la huella en el JSON: " . $regInvoice->file_json;
                        self::echo("\n-- " . $errorMessage);
                        
                        // Log error to database
                        VerifactuErrorLog::logError(
                            $company->idempresa,
                            self::JOB_NAME,
                            'HASH_GENERATION_FAILED',
                            $errorMessage,
                            [
                                'factura_id' => $regInvoice->id,
                                'json_file' => $regInvoice->file_json,
                                'step' => 'hash_generation'
                            ]
                        );
                        break;
                    } else {
                        self::echo("\n--- Hash generado OK para factura: " . $regInvoice->id);
                    }
                } catch (\Exception $e) {
                    $errorMessage = "EXCEPCIÓN en Hash::generate: " . $e->getMessage();
                    self::echo("\n-- " . $errorMessage);
                    
                    // Log exception to database
                    VerifactuErrorLog::logError(
                        $company->idempresa,
                        self::JOB_NAME,
                        'HASH_GENERATION_EXCEPTION',
                        $errorMessage,
                        [
                            'factura_id' => $regInvoice->id,
                            'json_file' => $regInvoice->file_json,
                            'exception_class' => get_class($e),
                            'exception_file' => $e->getFile(),
                            'exception_line' => $e->getLine(),
                            'stack_trace' => $e->getTraceAsString()
                        ]
                    );
                    break;
                }
                
                if (false === Signature::generate($regInvoice)) {
                    // generamos la firma del JSON
                    $errorMessage = "Error al generar la firma en el JSON: " . $regInvoice->file_json;
                    self::echo("\n-- " . $errorMessage);
                    
                    // Log signature error to database
                    VerifactuErrorLog::logError(
                        $company->idempresa,
                        self::JOB_NAME,
                        'SIGNATURE_GENERATION_FAILED',
                        $errorMessage,
                        [
                            'factura_id' => $regInvoice->id,
                            'json_file' => $regInvoice->file_json,
                            'step' => 'signature_generation'
                        ]
                    );
                    break;
                } else {
                    self::echo("\n--- Firma generada OK para factura: " . $regInvoice->id);
                }
                
                self::echo("\n--- ✅ Factura " . $regInvoice->id . " completada");
            }
        }

        self::saveEcho();
    }

    private static function getCompanies(): array
    {
        $where = [
            new DataBaseWhere('vf_certificate', null, 'IS NOT'),
            new DataBaseWhere('vf_password', null, 'IS NOT'),
        ];
        $companies = Empresa::all($where);
        return $companies;
    }

    private static function getRegInvoices(Empresa $company): array
    {
        $where = [
            new DataBaseWhere('idempresa', $company->idempresa),
            new DataBaseWhere('hash', null),
        ];
        $invoices = ModelVerifactuRegistroFactura::all($where, ['id' => 'ASC']);
        return $invoices;
    }
}