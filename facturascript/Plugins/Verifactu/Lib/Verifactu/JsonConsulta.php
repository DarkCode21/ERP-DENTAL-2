<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu;

use Exception;
use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\Verifactu\Lib\FiscalNumberValidator;

/**
 * Clase para generar el JSON de consulta de facturas en Verifactu.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class JsonConsulta
{
    /** @var Empresa */
    private static $company;

    /** @var Cliente */
    private static $customer;

    /** @var Ejercicio */
    private static $exercise;

    /** @var array */
    private static $json = [];

    /** @var string */
    private static $startDate;

    /** @var string */
    private static $endDate;

    public static function generate(Ejercicio $exercise, string $startDate, string $endDate, Cliente $customer = null): array
    {
        try {
            // comprobamos que la empresa está configurada
            $company = Empresas::get($exercise->idempresa);
            if (false === $company->verifactuIsConfigured()) {
                return [];
            }

            self::$exercise = $exercise;
            self::$company = $company;
            self::$startDate = $startDate;
            self::$endDate = $endDate;
            self::$customer = $customer;

            if (false === self::jsonCabecera()) {
                return [];
            } elseif (false === self::jsonFiltroConsulta()) {
                return [];
            }

            return self::$json;
        } catch (Exception $e) {
            Tools::log()->error('verifactu-xml-generate-error', [
                '%error%' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private static function jsonCabecera(): bool
    {
        if (false === self::jsonIDVersion()) {
            return false;
        } elseif (false === self::jsonObligadoEmision()) {
            return false;
        }

        return true;
    }

    private static function jsonFiltroConsulta(): bool
    {
        $filters = [];
        if (false === self::jsonPeriodoImputacion($filters)) {
            return false;
        } elseif (false === self::jsonContraparte($filters)) {
            return false;
        } elseif (false === self::jsonFechaExpedicionFactura($filters)) {
            return false;
        }

        self::$json['FiltroConsulta'] = $filters;
        return true;
    }

    private static function jsonContraparte(array &$filters): bool
    {
        if (empty(self::$customer->primaryColumnValue())) {
            return true;
        } elseif (empty(self::$customer->razonsocial)) {
            Tools::log()->error('required-empty-field', [
                '%field%' => 'razonsocial',
            ]);
            return false;
        } elseif (empty(self::$customer->cifnif)) {
            Tools::log()->error('required-empty-field', [
                '%field%' => 'cifnif',
            ]);
            return false;
        }

        $filters['Contraparte'] = [
            'NombreRazon' => Tools::textBreak(self::$customer->razonsocial, 120, ''),
            'NIF' => FiscalNumberValidator::normaliceCifNif(self::$customer->cifnif, '/^[A-Z0-9]{1,9}$/'),
        ];
        return true;
    }

    private static function jsonIDVersion(): bool
    {
        self::$json['Cabecera']['IDVersion'] = '1.0';
        return true;
    }

    private static function jsonFechaExpedicionFactura(array &$filters): bool
    {
        // si el año y mes del ejercicio no es igual a la fecha desde y hasta, no se puede hacer la consulta
        if (date('Y', strtotime(self::$startDate)) !== date('Y', strtotime(self::$exercise->fechainicio))
            || date('Y', strtotime(self::$endDate)) !== date('Y', strtotime(self::$exercise->fechainicio))
            || date('m', strtotime(self::$startDate)) !== date('m', strtotime(self::$startDate))
            || date('m', strtotime(self::$endDate)) !== date('m', strtotime(self::$startDate))) {
            Tools::log()->error('verifactu-invalid-exercise-period', [
                '%ejercicio%' => date('Y', strtotime(self::$exercise->fechainicio)),
                '%periodo%' => date('m', strtotime(self::$startDate)),
            ]);
            return false;
        }

        // si el año y mes de la fecha de inicio y fin no son iguales, no se puede hacer la consulta
        if (date('Y-m', strtotime(self::$startDate)) !== date('Y-m', strtotime(self::$endDate))) {
            Tools::log()->error('verifactu-invalid-date-range', [
                '%desde%' => self::$startDate,
                '%hasta%' => self::$endDate,
            ]);
            return false;
        }

        // la fecha de fin no puede ser anterior a la de inicio
        if (strtotime(self::$startDate) > strtotime(self::$endDate)) {
            Tools::log()->error('verifactu-invalid-date-range', [
                '%desde%' => self::$startDate,
                '%hasta%' => self::$endDate,
            ]);
            return false;
        }

        $filters['FechaExpedicionFactura'] = [
            'RangoFechaExpedicion' => [
                'Desde' => date('d-m-Y', strtotime(self::$startDate)),
                'Hasta' => date('d-m-Y', strtotime(self::$endDate)),
            ],
        ];
        return true;
    }

    private static function jsonObligadoEmision(): bool
    {
        self::$json['Cabecera']['ObligadoEmision'] = [
            'NombreRazon' => Tools::textBreak(self::$company->nombre, 120, ''),
            'NIF' => FiscalNumberValidator::normaliceCifNif(self::$company->cifnif, '/^[A-Z0-9]{1,9}$/'),
        ];
        return true;
    }

    private static function jsonPeriodoImputacion(array &$filters): bool
    {
        $filters['PeriodoImputacion'] = [
            'Ejercicio' => date('Y', strtotime(self::$exercise->fechainicio)),
            'Periodo' => date('m', strtotime(self::$startDate)),
        ];
        return true;
    }
}