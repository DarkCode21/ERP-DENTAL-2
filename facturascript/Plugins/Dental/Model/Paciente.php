<?php

/**
 * Paciente Model
 */

namespace FacturaScripts\Plugins\Dental\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Cliente;
use FacturaScripts\Core\Model\Base\ModelClass;

class Paciente extends ModelClass
{
    use \FacturaScripts\Core\Model\Base\ModelTrait;
    use \FacturaScripts\Plugins\Dental\Model\Traits\EncryptedFieldsTrait;

    protected static $encryptedFields = [
        'aseguradora',
        'numero_poliza',
        'alergias',
        'medicacion',
        'antecedentes_medicos',
        'antecedentes_odontologicos',
        'observaciones'
    ];

    public $id;
    public $codcliente;
    public $alergias;
    public $medicacion;
    public $antecedentes_medicos;
    public $antecedentes_odontologicos;
    public $aseguradora;
    public $numero_poliza;
    public $observaciones;
    public $estado;
    public $fecha_alta;
    public $created_by;
    public $created_at;
    public $updated_at;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'dental_pacientes';
    }

    public function clear()
    {
        parent::clear();
        $this->estado = 'activo';
        $this->fecha_alta = date('d-m-Y');
    }

    public function primaryDescriptionColumn(): string
    {
        return 'codcliente';
    }

    public function getCliente(): ?Cliente
    {
        if (empty($this->codcliente)) {
            return null;
        }
        $cliente = new Cliente();
        if ($cliente->loadFromCode($this->codcliente)) {
            return $cliente;
        }
        return null;
    }

    public function test(): bool
    {
        if (empty($this->codcliente)) {
            return false;
        }
        return parent::test();
    }

    public function getCitas(): array
    {
        $model = new Cita();
        $where = [new DataBaseWhere('idpaciente', $this->id)];
        return $model->all($where, ['fecha' => 'DESC', 'hora_inicio' => 'DESC']);
    }

    public function getHistorial(): array
    {
        $model = new Historial();
        $where = [new DataBaseWhere('idpaciente', $this->id)];
        return $model->all($where, ['fecha' => 'DESC']);
    }

    public function getArchivos(): array
    {
        $model = new Archivo();
        $where = [new DataBaseWhere('idpaciente', $this->id)];
        return $model->all($where, ['created_at' => 'DESC']);
    }

    public function getTratamientos(): array
    {
        $model = new TratamientoPaciente();
        $where = [new DataBaseWhere('idpaciente', $this->id)];
        return $model->all($where, ['created_at' => 'DESC']);
    }
}
