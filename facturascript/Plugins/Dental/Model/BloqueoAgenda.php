<?php
/**
 * BloqueoAgenda Model
 */

namespace FacturaScripts\Plugins\Dental\Model;

use FacturaScripts\Core\Model\Base\ModelClass;

class BloqueoAgenda extends ModelClass
{
    use \FacturaScripts\Core\Model\Base\ModelTrait;

    public $id;
    public $idespecialista;
    public $idgabinete;
    public $fecha;
    public $hora_inicio;
    public $hora_fin;
    public $motivo;
    public $tipo;
    public $created_by;
    public $created_at;
    public $updated_at;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'dental_bloqueos_agenda';
    }

    public function clear()
    {
        parent::clear();
        $this->fecha = date('d-m-Y');
        $this->hora_inicio = '09:00';
        $this->hora_fin = '09:30';
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

    public function getGabinete(): ?Gabinete
    {
        if (empty($this->idgabinete)) {
            return null;
        }
        $gabinete = new Gabinete();
        if ($gabinete->loadFromCode($this->idgabinete)) {
            return $gabinete;
        }
        return null;
    }

    public function test(): bool
    {
        if (empty($this->fecha) || empty($this->hora_inicio) || empty($this->hora_fin) || empty($this->motivo) || empty($this->tipo)) {
            return false;
        }
        return parent::test();
    }
}
