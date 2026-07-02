<?php

/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Extension\Model;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroEvento\JsonFinNoVerifactu;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroEvento\JsonInicioNoVerifactu;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRegistroFactura;
use FacturaScripts\Dinamic\Model\Empresa as DinEmpresa;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Ejercicio
{
    public function deleteBefore(): Closure
    {
        return function () {
            // si el ejercicio tiene registros de factura, no se puede eliminar
            if (count($this->verifactuGetRegistroFactura()) > 0) {
                Tools::log()->warning('verifactu-exercise-has-events', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }
        };
    }

    public function getCompany(): Closure
    {
        return function (): DinEmpresa {
            $company = new DinEmpresa();
            $company->loadFromCode($this->idempresa);
            return $company;
        };
    }

    public function saveUpdateBefore(): Closure
    {
        return function () {
            $dataBase = new DataBase();

            // Traemos el valor anterior de la BD
            $row = $dataBase->select(
                "SELECT vf_mode FROM ejercicios WHERE codejercicio = '" . $this->primaryColumnValue() . "'"
            );

            if (empty($row)) {
                return;
            }

            $oldMode = $row[0]['vf_mode'] ?? null;
            $newMode = $this->vf_mode;

            // si se cambió vf_mode y no estaba vacío antes → no se puede cambiar
            if ($oldMode !== $newMode && !empty($oldMode)) {
                Tools::log()->warning('verifactu-exercise-mode-change', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            // si se cambió la columna vf_mode, antes estaba vacía y ahora no, y el modo es no-verifactu, registramos evento
            if ($oldMode !== $newMode && empty($oldMode) && $newMode === 'no-verifactu') {
                JsonInicioNoVerifactu::generate($this);
            }
        };
    }

    public function saveInsertBefore(): Closure
    {
        return function () {
            // si el modo ya está relleno, no hacemos nada
            if (!empty($this->vf_mode)) {
                return;
            }

            // restamos a la fecha de inicio y fin del ejercicio un año
            $yearStart = date(Tools::DATE_STYLE, strtotime('-1 year', strtotime($this->fechainicio)));
            $yearEnd = date(Tools::DATE_STYLE, strtotime('-1 year', strtotime($this->fechafin)));

            $dataBase = new DataBase();

            $sql = "SELECT vf_mode 
                FROM ejercicios 
                WHERE idempresa = " . $dataBase->var2str($this->idempresa) . "
                  AND fechainicio >= " . $dataBase->var2str($yearStart) . "
                  AND fechafin <= " . $dataBase->var2str($yearEnd) . "
                LIMIT 1";

            $row = $dataBase->select($sql);

            // si no existe o el nuevo ejercicio ya tiene modo, no hacemos nada
            if (empty($row) || !empty($this->vf_mode)) {
                return;
            }

            // usamos el mismo modo de verifactu que el ejercicio anterior
            $this->vf_mode = $row[0]['vf_mode'];
        };
    }

    public function saveInsert(): Closure
    {
        return function () {
            $dataBase = new DataBase();

            // restamos a la fecha de inicio y fin del ejercicio un año
            $yearStart = date(Tools::DATE_STYLE, strtotime('-1 year', strtotime($this->fechainicio)));
            $yearEnd = date(Tools::DATE_STYLE, strtotime('-1 year', strtotime($this->fechafin)));

            // buscamos un ejercicio de la misma empresa del año anterior
            $sql = "SELECT id, vf_mode 
                FROM ejercicios 
                WHERE idempresa = " . $dataBase->var2str($this->idempresa) . "
                  AND fechainicio >= " . $dataBase->var2str($yearStart) . "
                  AND fechafin <= " . $dataBase->var2str($yearEnd) . "
                LIMIT 1";

            $row = $dataBase->select($sql);

            // si no existe ejercicio anterior y el modo es no-verifactu, registramos el evento de inicio
            if (empty($row) && $this->vf_mode === 'no-verifactu') {
                JsonInicioNoVerifactu::generate($this);
                return;
            }

            // si existe ejercicio anterior
            if (!empty($row)) {
                $previousId = $row[0]['id'];
                $previousMode = $row[0]['vf_mode'];

                // si anterior = no-verifactu y nuevo = verifactu → registrar fin
                if ($previousMode === 'no-verifactu' && $this->vf_mode === 'verifactu') {
                    // aquí deberías instanciar el ejercicio anterior si lo necesita JsonFin
                    $previousExercise = new self();
                    $previousExercise->loadFromCode($previousId);

                    JsonFinNoVerifactu::generate($previousExercise);
                    return;
                }

                // si anterior = verifactu y nuevo = no-verifactu → registrar fin e inicio
                if ($previousMode === 'verifactu' && $this->vf_mode === 'no-verifactu') {
                    $previousExercise = new self();
                    $previousExercise->loadFromCode($previousId);

                    JsonFinNoVerifactu::generate($previousExercise);
                    JsonInicioNoVerifactu::generate($this);
                }
            }
        };
    }

    public function verifactuGetRegistroFactura(): Closure
    {
        return function (string $mode = ''): array {
            $where = [new DataBaseWhere('codejercicio', $this->codejercicio)];

            if (!empty($mode)) {
                $where[] = new DataBaseWhere('mode', $mode);
            }

            return VerifactuRegistroFactura::all($where, ['id' => 'ASC']);
        };
    }
}
