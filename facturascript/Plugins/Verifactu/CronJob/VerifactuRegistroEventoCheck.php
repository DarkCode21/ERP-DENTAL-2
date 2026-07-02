<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\CronJob;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Template\CronJobClass;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroEvento\JsonLanzamientoProcesoDeteccionAnomaliasEventos;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroEvento\JsonLanzamientoProcesoDeteccionAnomaliasFacturas;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroEvento\JsonResumenEventos;

/**
 * Clase para generar los registros de eventos de Verifactu necesarios para comprobaciones periódicas.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class VerifactuRegistroEventoCheck extends CronJobClass
{

    const JOB_NAME = 'verifactu-event-check';

    public static function run(): void
    {
        self::echo("\n\n* JOB: " . self::JOB_NAME . ' ...');

        // recorremos las empresas de Verifactu
        foreach (self::getCompanies() as $company) {
            self::echo("\n- Empresa: " . $company->nombrecorto);

            // si la empresa no está configurada, saltamos
            if (false === $company->verifactuIsConfigured(false)) {
                self::echo("\n-- Empresa no configurada");
                continue;
            }

            if (!JsonLanzamientoProcesoDeteccionAnomaliasFacturas::generate($company)) {
                self::echo("\n-- Error al generar el JSON de lanzamiento del proceso de detección de anomalías en el registro de facturación");
            }

            if (!JsonLanzamientoProcesoDeteccionAnomaliasEventos::generate($company)) {
                self::echo("\n-- Error al generar el JSON de lanzamiento del proceso de detección de anomalías en los eventos");
            }

            if (!JsonResumenEventos::generate($company)) {
                self::echo("\n-- Error al generar el JSON de resumen de eventos");
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
        return Empresa::all($where);
    }
}