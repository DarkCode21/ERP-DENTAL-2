<?php
/**
 * Gabinete Model
 */

namespace FacturaScripts\Plugins\Dental\Model;

use FacturaScripts\Core\Model\Base\ModelClass;

class Gabinete extends ModelClass
{
    use \FacturaScripts\Core\Model\Base\ModelTrait;

    public $id;
    public $codigo;
    public $nombre;
    public $descripcion;
    public $ubicacion;
    public $equipamiento;
    public $estado;
    public $observaciones;
    public $created_at;
    public $updated_at;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'dental_gabinetes';
    }

    public function clear()
    {
        parent::clear();
        $this->estado = 'activo';
    }

    public function test(): bool
    {
        $this->codigo = trim($this->codigo);
        $this->nombre = trim($this->nombre);
        if (empty($this->codigo) || empty($this->nombre)) {
            return false;
        }
        return parent::test();
    }
}
