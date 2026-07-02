<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\CronJob;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Template\CronJobClass;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\JsonTrait;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroEvento\Hash;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroEvento\Signature;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRegistroEvento as ModelVerifactuRegistroEvento;

/**
 * Clase para generar el hash y firma de los registros de eventos de Verifactu.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class VerifactuRegistroEventoHashSignature extends CronJobClass
{
    use JsonTrait;

    const JOB_NAME = 'verifactu-event-hash-signature';

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

            foreach (self::getRegEvents($company) as $regEvent) {
                // generamos la huella del JSON
                if (false === Hash::generate($regEvent)) {
                    self::echo("\n-- Error al generar la huella en el JSON: " . $regEvent->file_json);
                    break;
                } elseif (false === Signature::generate($regEvent)) {
                    // generamos la firma del JSON
                    self::echo("\n-- Error al generar la firma en el JSON: " . $regEvent->file_json);
                    break;
                }
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

    private static function getRegEvents(Empresa $company): array
    {
        $where = [
            new DataBaseWhere('idempresa', $company->idempresa),
            new DataBaseWhere('hash', null),
        ];
        return ModelVerifactuRegistroEvento::all($where, ['id' => 'ASC']);
    }
}