<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Model;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Session;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Empresa;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class VerifactuRequerimiento extends ModelClass
{
    use ModelTrait;

    const STATUS_COMPLETED = 'completed';
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';

    /** @var string */
    public $creation_date;

    /** @var string */
    public $end_codejercicio;

    /** @var int */
    public $id;

    /** @var int */
    public $idempresa;

    /** @var string */
    public $last_nick;

    /** @var string */
    public $last_update;

    /** @var string */
    public $nick;

    /** @var string */
    public $reference;

    /** @var string */
    public $start_codejercicio;

    /** @var string */
    public $status;

    public function addLine(VerifactuRegistroFactura $registroFactura): bool
    {
        $line = new VerifactuRequerimientoLine();
        $line->id_requerimiento = $this->id;
        $line->id_registro_factura = $registroFactura->id;
        return $line->save();
    }

    public function clear(): void
    {
        parent::clear();
        $this->status = self::STATUS_PENDING;
    }

    public function delete(): bool
    {
        // si se está procesando o está completado, no se puede eliminar
        if (in_array($this->status, [self::STATUS_PROCESSING, self::STATUS_COMPLETED], true)) {
            Tools::log()->warning('verifactu-requirement-delete-not-allowed', [
                'id' => $this->id,
                'status' => $this->status,
            ]);
            return false;
        }

        return parent::delete();
    }

    public function primaryDescriptionColumn(): string
    {
        return 'reference';
    }

    public function getCompany(): Empresa
    {
        $company = new Empresa();
        $company->loadFromCode($this->idempresa);
        return $company;
    }

    public function getEndExercise(): Ejercicio
    {
        $exercise = new Ejercicio();
        $exercise->loadFromDate($this->end_codejercicio);
        return $exercise;
    }

    public function getLines(): array
    {
        $where = [new DataBaseWhere('id_requerimiento', $this->id)];
        return VerifactuRequerimientoLine::all($where, ['id' => 'ASC']);
    }

    public function getStartExercise(): Ejercicio
    {
        $exercise = new Ejercicio();
        $exercise->loadFromDate($this->start_codejercicio);
        return $exercise;
    }

    public function install(): string
    {
        new Empresa();
        return parent::install();
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'verifactu_requerimientos';
    }

    public function test(): bool
    {
        $this->creation_date = $this->creation_date ?? Tools::dateTime();
        $this->nick = $this->nick ?? Session::user()->nick;
        $this->reference = Tools::noHtml($this->reference);
        $this->status = Tools::noHtml($this->status);

        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        if ('list' === $type) {
            return empty($this->idempresa)
                ? 'ListEmpresa'
                : $this->getCompany()->url() . '&activetab=List' . $this->modelClassName();
        }

        if ('edit' === $type) {
            return empty($this->id)
                ? 'EditVerifactuRequerimiento?idempresa=' . $this->idempresa
                : 'EditVerifactuRequerimiento?code=' . $this->id;
        }

        return parent::url($type, $list);
    }

    protected function saveInsert(array $values = []): bool
    {
        // si la empresa no está configurada para Verifactu, no se permite crear requerimientos
        $company = $this->getCompany();
        if (!$company->verifactuIsConfigured()) {
            return false;
        }

        // obtenemos todos los ejercicios de la misma empresa
        // entre la fecha de inicio del ejercicio de inicio
        // y la fecha de fin del ejercicio de fin
        $where = [
            new DataBaseWhere('idempresa', $this->idempresa),
            new DataBaseWhere('fechainicio', $this->getStartExercise()->fechainicio, '>='),
            new DataBaseWhere('fechafin', $this->getEndExercise()->fechafin, '<='),
        ];
        $exercises = Ejercicio::all($where, ['fechainicio' => 'ASC']);

        $db = new DataBase();
        $inTransaction = $db->inTransaction();
        if (!$inTransaction) {
            $db->beginTransaction();
        }

        // creamos el requerimiento
        if (false === parent::saveInsert($values)) {
            if (!$inTransaction) {
                $db->rollBack();
            }
            return false;
        }

        // recorremos los ejercicios desde el inicio hasta el fin
        foreach ($exercises as $exercise) {
            if (empty($exercise->vf_mode)) {
                Tools::log()->warning('exercise-not-configured', [
                    'idempresa' => $this->idempresa,
                    '%codejercicio%' => $exercise->codejercicio,
                ]);
                if (!$inTransaction) {
                    $db->rollBack();
                }
                return false;
            }

            // cargamos las líneas del requerimiento
            $where = [
                new DataBaseWhere('idempresa', $this->idempresa),
                new DataBaseWhere('codejercicio', $exercise->codejercicio),
            ];
            foreach (VerifactuRegistroFactura::all($where, ['id' => 'DESC']) as $regFactura) {
                if (!$this->addLine($regFactura)) {
                    Tools::log()->error('verifactu-requirement-line-add-failed', [
                        'id_requerimiento' => $this->id,
                        '%id_registro_factura%' => $regFactura->id,
                        'idfactura' => $regFactura->idfactura,
                        'codejercicio' => $exercise->codejercicio,
                    ]);
                    if (!$inTransaction) {
                        $db->rollBack();
                    }
                    return false;
                }
            }
        }

        if (!$inTransaction) {
            $db->commit();
        }

        return true;
    }

    protected function saveUpdate(array $values = []): bool
    {
        Tools::log()->warning('not-permitted-updated');
        return false;
    }
}
