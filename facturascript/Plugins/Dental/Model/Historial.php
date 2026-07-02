<?php
/**
 * Historial Model
 */

namespace FacturaScripts\Plugins\Dental\Model;

use FacturaScripts\Core\Model\Base\ModelClass;

class Historial extends ModelClass
{
    use \FacturaScripts\Core\Model\Base\ModelTrait;
    use \FacturaScripts\Plugins\Dental\Model\Traits\EncryptedFieldsTrait;

    protected static $encryptedFields = [
        'motivo_consulta',
        'diagnostico',
        'tratamiento_recomendado',
        'tratamiento_realizado',
        'medicacion_prescrita',
        'observaciones_clinicas'
    ];

    public $id;
    public $idpaciente;
    public $idespecialista;
    public $idcita;
    public $fecha;
    public $tipo;
    public $motivo_consulta;
    public $diagnostico;
    public $tratamiento_recomendado;
    public $tratamiento_realizado;
    public $medicacion_prescrita;
    public $observaciones_clinicas;
    public $proxima_revision;
    public $estado;
    public $created_by;
    public $created_at;
    public $updated_at;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'dental_historial';
    }

    public function clear()
    {
        parent::clear();
        $this->fecha = date('d-m-Y');
        $this->estado = 'activo';
    }

    public function getPaciente(): ?Paciente
    {
        $paciente = new Paciente();
        if ($paciente->loadFromCode($this->idpaciente)) {
            return $paciente;
        }
        return null;
    }

    public function getEspecialista(): ?Especialista
    {
        if (empty($this->idespecialista)) {
            return null;
        }
        $especialista = new Especialista();
        if ($especialista->loadFromCode($this->idespecialista)) {
            return $especialista;
        }
        return null;
    }

    public function test(): bool
    {
        if (empty($this->idpaciente) || empty($this->fecha) || empty($this->tipo)) {
            return false;
        }
        return parent::test();
    }
}
