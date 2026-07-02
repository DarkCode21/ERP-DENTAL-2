<?php

/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Extension\Model;

use Closure;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\Certificate;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRegistroEvento;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRegistroFactura;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRequerimiento;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Empresa
{
    // TODO: eliminar en la versión 1.0
    public function clear(): Closure
    {
        return function () {
        };
    }

    public function delete(): Closure
    {
        return function () {
            $filePathP12 = Certificate::getCertificateP12($this);
            if (file_exists($filePathP12)) {
                unlink($filePathP12);
            }

            $filePathPem = Certificate::getCertificatePem($this);
            if (file_exists($filePathPem)) {
                unlink($filePathPem);
            }
        };
    }

    public function deleteBefore(): Closure
    {
        return function () {
            // si la empresa tiene registros de verifactu, no se puede eliminar
            if (count($this->verifactuGetRegistroFactura()) > 0) {
                Tools::log()->warning('verifactu-company-has-events', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }
        };
    }

    public function saveUpdateBefore(): Closure
    {
        return function () {
            $dataBase = new DataBase();

            $row = $dataBase->select(
                "SELECT vf_debug_mode FROM empresas WHERE idempresa = " . $this->primaryColumnValue()
            );

            if (empty($row)) {
                return;
            }

            $oldDebugMode = (bool) $row[0]['vf_debug_mode'];
            $newDebugMode = (bool) $this->vf_debug_mode;

            // si está en modo real, y se intenta cambiar al modo pruebas,
            // comprobar si hay registros de verifactu,
            // si los hay, no se puede cambiar
            if ($oldDebugMode && !$newDebugMode) {
                $registrosFactura = $this->verifactuGetRegistroFactura();
                $registrosEvento = $this->verifactuGetRegistroEvento();
                $requerimientos = $this->verifactuGetRequirements();
                if (!empty($registrosFactura) || !empty($registrosEvento) || !empty($requerimientos)) {
                    Tools::log()->warning('verifactu-not-change-to-debug-mode');
                    return false;
                }
            }

            // si está en modo pruebas, y se intenta cambiar al modo real,
            // eliminar todos los registros y datos
            if (!$oldDebugMode && $newDebugMode) {
                self::$dataBase->exec('DELETE FROM verifactu_registros_eventos WHERE idempresa = ' . $this->primaryColumnValue());
                self::$dataBase->exec('DELETE FROM verifactu_registros_facturas WHERE idempresa = ' . $this->primaryColumnValue());
                self::$dataBase->exec('DELETE FROM verifactu_requerimientos WHERE idempresa = ' . $this->primaryColumnValue());
                self::$dataBase->exec('UPDATE facturascli SET vf_sent = 0 WHERE idempresa = ' . $this->primaryColumnValue());
                Tools::folderDelete(Tools::folder('MyFiles', 'Verifactu', $this->primaryColumnValue()));
                Tools::folderDelete(Tools::folder('MyFiles', 'Tmp', 'Verifactu', $this->primaryColumnValue()));
                Tools::log()->info('verifactu-removed-data-changed-to-debug-mode');
            }
        };
    }

    public function test(): Closure
    {
        return function () {
            $this->vf_certificate = Tools::noHtml($this->vf_certificate);
            $this->vf_password = Tools::noHtml($this->vf_password);


            if ($this->vf_certificate && empty($this->vf_password)) {
                $filePath = Tools::folder('MyFiles', $this->vf_certificate);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                Tools::log()->warning('verifactu-certificate-password-empty');
                return false;
            }
        };
    }

    public function verifactuGetExercises(): Closure
    {
        return function () {
            $where = [
                new DataBaseWhere($this->primaryColumn(), $this->primaryColumnValue()),
                new DataBaseWhere('vf_mode', null, 'IS NOT'),
            ];
            return Ejercicio::all($where, ['fechainicio' => 'ASC']);
        };
    }

    public function verifactuGetRegistroEvento(): Closure
    {
        return function (): array {
            $where = [new DataBaseWhere('idempresa', $this->idempresa)];
            return VerifactuRegistroEvento::all($where, ['id' => 'ASC']);
        };
    }

    public function verifactuGetRegistroFactura(): Closure
    {
        return function (string $mode = ''): array {
            $where = [new DataBaseWhere('idempresa', $this->idempresa)];

            if (!empty($mode)) {
                $where[] = new DataBaseWhere('mode', $mode);
            }

            return VerifactuRegistroFactura::all($where, ['id' => 'ASC']);
        };
    }

    public function verifactuGetRequirements(): Closure
    {
        return function (): array {
            $where = [new DataBaseWhere('idempresa', $this->idempresa)];
            return VerifactuRequerimiento::all($where, ['id' => 'ASC']);
        };
    }

    public function verifactuIsConfigured(): Closure
    {
        return function (bool $log = true): bool {
            if ($this->codpais !== 'ESP') {
                if ($log) {
                    Tools::log()->warning('company-not-esp', [
                        '%name%' => $this->nombrecorto,
                    ]);
                }
                return false;
            }

            // comprobar Ticketbai
            if (
                Plugins::isEnabled('Ticketbai')
                && (!empty($this->tbai_signature) || !empty($this->tbai_password))
            ) {
                if ($log) {
                    Tools::log()->warning('company-ticketbai-configured', [
                        '%name%' => $this->nombrecorto,
                    ]);
                }
                return false;
            }

            // comprobar SII
            if (
                Plugins::isEnabled('InformeSII')
                && (!empty($this->sii_signature) || !empty($this->sii_password))
            ) {
                if ($log) {
                    Tools::log()->warning('company-report-sii-configured', [
                        '%name%' => $this->nombrecorto,
                    ]);
                }
                return false;
            }

            if (empty($this->vf_password)) {
                if ($log) {
                    Tools::log()->warning('company-certificate-password-empty', [
                        '%name%' => $this->nombrecorto,
                    ]);
                }
                return false;
            }

            if (empty($this->vf_certificate)) {
                if ($log) {
                    Tools::log()->warning('company-certificate-empty', [
                        '%name%' => $this->nombrecorto,
                    ]);
                }
                return false;
            }

            // obtenemos los ejercicios con verifactu configurado
            $exercises = $this->verifactuGetExercises();

            // si no hay ejercicios, no está configurada
            if (count($exercises) === 0) {
                if ($log) {
                    Tools::log()->warning('company-verifactu-no-exercises', [
                        '%name%' => $this->nombrecorto,
                    ]);
                }
                return false;
            }

            if (
                empty(Tools::settings('default', 'telemetryinstall'))
                && empty(Tools::config('CITY_UUID'))
                && empty(Tools::config('SPACE_UUID'))
            ) {
                if ($log) {
                    Tools::log()->warning('erp-not-identified', [
                        '%name%' => $this->nombrecorto,
                    ]);
                }
                return false;
            }

            if ((int)Tools::settings('default', 'decimals') !== 2) {
                if ($log) {
                    Tools::log()->warning('erp-decimals-not-2', [
                        '%name%' => $this->nombrecorto,
                    ]);
                }
                return false;
            }

            return true;
        };
    }
}
