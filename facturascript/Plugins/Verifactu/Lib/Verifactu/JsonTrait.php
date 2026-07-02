<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu;

use DateTime;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\Verifactu\Lib\FiscalNumberValidator;

/**
 * Trait padre para generar datos comunes en el JSON.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait JsonTrait
{
    /** @var Empresa */
    private static $company;

    /** @var Ejercicio */
    private static $exercise;

    /** @var string */
    private static $filePath = null;

    /** @var array */
    private static $json = [];

    public static function getFolderVerifactu(): string
    {
        return 'MyFiles/Verifactu/';
    }

    private static function deleteFile(?string $file): void
    {
        if (false === empty($file) && file_exists($file)) {
            unlink($file);
        }
    }

    private static function jsonFechaHoraHusoGenRegistro(string $key1 = 'FechaHoraHusoGenRegistro', string $parentKey = ''): bool
    {
        $date = new DateTime();
        if (!empty($parentKey)) {
            self::$json[$parentKey][$key1] = $date->format('Y-m-d\TH:i:sP');
        } else {
            self::$json[$key1] = $date->format('Y-m-d\TH:i:sP');
        }
        return true;
    }

    private static function jsonIDVersion(): bool
    {
        self::$json['IDVersion'] = '1.0';
        return true;
    }

    private static function jsonObligadoEmision(string $keyParent, Empresa $company): bool
    {
        // validamos el cif de la empresa
        $cifnif = FiscalNumberValidator::normaliceCifNif($company->cifnif, '/^[A-Z0-9]{1,9}$/');
        if (!FiscalNumberValidator::validate($company->tipoidfiscal, $cifnif, true)) {
            return false;
        }

        self::$json[$keyParent]['ObligadoEmision'] = [
            'NombreRazon' => Tools::textBreak($company->nombre, 120, ''),
            'NIF' => $cifnif,
        ];
        return true;
    }

    private static function jsonSistemaInformatico(string $keyParent = ''): bool
    {
        if (false === empty(Tools::config('CITY_UUID'))) {
            $numeroInstalacion = Tools::config('CITY_UUID');
        } elseif (false === empty(Tools::config('SPACE_UUID'))) {
            $numeroInstalacion = Tools::config('SPACE_UUID');
        } elseif (false === empty(Tools::settings('default', 'telemetryinstall'))) {
            $numeroInstalacion = Tools::settings('default', 'telemetryinstall');
        } else {
            Tools::log()->error('erp-not-identified');
            return false;
        }

        $data = [
            'NombreRazon' => 'MIGUEL ANGEL PEREZ SOLA',
            'NIF' => '76144075E',
            'NombreSistemaInformatico' => 'INTERIBERICA-PYME',
            'IdSistemaInformatico' => 'PY',
            'Version' => '1.0',
            'NumeroInstalacion' => $numeroInstalacion,
            'TipoUsoPosibleSoloVerifactu' => 'N',
            'TipoUsoPosibleMultiOT' => 'S',
            'IndicadorMultiplesOT' => 'S',
        ];

        $key = 'SistemaInformatico';
        if (!empty($keyParent)) {
            self::$json[$keyParent][$key] = $data;
        } else {
            self::$json[$key] = $data;
        }

        return true;
    }
}