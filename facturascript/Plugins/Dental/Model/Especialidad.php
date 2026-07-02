<?php
/**
 * Especialidad Model
 */

namespace FacturaScripts\Plugins\Dental\Model;

use FacturaScripts\Core\Model\Base\ModelClass;

class Especialidad extends ModelClass
{
    use \FacturaScripts\Core\Model\Base\ModelTrait;

    public $id;
    public $nombre;
    public $descripcion;
    public $estado;
    public $created_at;
    public $updated_at;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'dental_especialidades';
    }

    public function clear()
    {
        parent::clear();
        $this->estado = 'activo';
    }

    public function test(): bool
    {
        $this->nombre = trim($this->nombre);
        if (empty($this->nombre)) {
            return false;
        }
        return parent::test();
    }
}
