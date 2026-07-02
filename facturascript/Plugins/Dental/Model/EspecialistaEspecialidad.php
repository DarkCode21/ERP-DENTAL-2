<?php
/**
 * EspecialistaEspecialidad
 */
namespace FacturaScripts\Plugins\Dental\Model;

use FacturaScripts\Core\Model\Base\ModelClass;

class EspecialistaEspecialidad extends ModelClass
{
    use \FacturaScripts\Core\Model\Base\ModelTrait;

    public $id;
    public $idespecialista;
    public $idespecialidad;
    public $es_principal;
    public $created_at;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'dental_especialista_especialidad';
    }

    public function clear(): void
    {
        parent::clear();
        $this->es_principal = false;
    }
}
