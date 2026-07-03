<?php
/**
 * TratamientoPaciente Model
 */

namespace FacturaScripts\Plugins\Dental\Model;

use FacturaScripts\Core\Model\Base\ModelClass;

class TratamientoPaciente extends ModelClass
{
    use \FacturaScripts\Core\Model\Base\ModelTrait;
    use \FacturaScripts\Plugins\Dental\Model\Traits\EncryptedFieldsTrait;

    protected static $encryptedFields = [
        'observaciones'
    ];

    public $id;
    public $idpaciente;
    public $referencia_servicio;
    public $salon_service_id;
    public $idespecialista;
    public $idpresupuesto;
    public $idfactura;
    public $fecha_inicio;
    public $fecha_fin;
    public $estado_clinico;
    public $estado_economico;
    public $precio;
    public $descuento;
    public $observaciones;
    public $created_at;
    public $updated_at;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'dental_tratamientos_paciente';
    }

    public function clear()
    {
        parent::clear();
        $this->estado_clinico = 'propuesto';
        $this->estado_economico = 'pendiente';
        $this->descuento = 0;
        $this->idfactura = null;
    }

    public function getPaciente(): ?Paciente
    {
        $paciente = new Paciente();
        if ($paciente->loadFromCode($this->idpaciente)) {
            return $paciente;
        }
        return null;
    }

    public function test(): bool
    {
        if (empty($this->idpaciente)) {
            return false;
        }
        return parent::test();
    }
}
