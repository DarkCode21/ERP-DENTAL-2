<?php
/**
 * Cita Model
 */

namespace FacturaScripts\Plugins\Dental\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;

class Cita extends ModelClass
{
    use \FacturaScripts\Core\Model\Base\ModelTrait;

    public $id;
    public $idpaciente;
    public $idespecialista;
    public $idgabinete;
    public $idtratamiento;
    public $fecha;
    public $hora_inicio;
    public $hora_fin;
    public $duracion;
    public $motivo;
    public $estado;
    public $observaciones;
    public $recordatorio_enviado;
    public $confirmada_paciente;
    public $created_by;
    public $created_at;
    public $updated_at;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'dental_citas';
    }

    public function clear()
    {
        parent::clear();
        $this->fecha = date('d-m-Y');
        $this->hora_inicio = '09:00';
        $this->hora_fin = '09:30';
        $this->duracion = 30;
        $this->estado = 'pendiente';
        $this->recordatorio_enviado = false;
        $this->confirmada_paciente = false;
    }

    public function primaryDescriptionColumn(): string
    {
        return 'fecha';
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
        $especialista = new Especialista();
        if ($especialista->loadFromCode($this->idespecialista)) {
            return $especialista;
        }
        return null;
    }

    public function getGabinete(): ?Gabinete
    {
        $gabinete = new Gabinete();
        if ($gabinete->loadFromCode($this->idgabinete)) {
            return $gabinete;
        }
        return null;
    }

    public function pacienteNombre(): string
    {
        $paciente = $this->getPaciente();
        if (!$paciente) {
            return '';
        }
        $cliente = $paciente->getCliente();
        return $cliente ? $cliente->razonsocial : '';
    }

    public function especialistaNombre(): string
    {
        $especialista = $this->getEspecialista();
        return $especialista ? $especialista->nombre : '';
    }

    public function gabineteNombre(): string
    {
        $gabinete = $this->getGabinete();
        return $gabinete ? $gabinete->nombre : '';
    }

    public function test(): bool
    {
        if (empty($this->idpaciente) || empty($this->idespecialista) || empty($this->idgabinete)) {
            return false;
        }
        if (empty($this->fecha) || empty($this->hora_inicio) || empty($this->hora_fin)) {
            return false;
        }
        return parent::test();
    }
}
