<?php
/**
 * Archivo Model
 */

namespace FacturaScripts\Plugins\Dental\Model;

use FacturaScripts\Core\Model\Base\ModelClass;

class Archivo extends ModelClass
{
    use \FacturaScripts\Core\Model\Base\ModelTrait;
    use \FacturaScripts\Plugins\Dental\Model\Traits\EncryptedFieldsTrait;

    protected static $encryptedFields = [
        'nombre_original',
        'nombre_archivo',
        'descripcion'
    ];

    public $id;
    public $idpaciente;
    public $idespecialista;
    public $idcita;
    public $idtratamiento;
    public $categoria;
    public $nombre_original;
    public $nombre_archivo;
    public $extension;
    public $mime_type;
    public $tamano;
    public $ruta;
    public $hash_archivo;
    public $descripcion;
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
        return 'dental_archivos';
    }

    public function clear()
    {
        parent::clear();
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

    public function test(): bool
    {
        if (empty($this->idpaciente) || empty($this->categoria)) {
            return false;
        }
        return parent::test();
    }
}
