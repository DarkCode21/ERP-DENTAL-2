<?php
/**
 * Odontograma Model
 */
namespace FacturaScripts\Plugins\Dental\Model;

use FacturaScripts\Core\Model\Base\ModelClass;

class Odontograma extends ModelClass
{
    use \FacturaScripts\Core\Model\Base\ModelTrait;

    public $id;
    public $idpaciente;
    public $datos;
    public $created_at;
    public $updated_at;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'dental_odontogramas';
    }

    public function clear()
    {
        parent::clear();
        $this->datos = '';
    }

    public function test(): bool
    {
        if (empty($this->idpaciente)) {
            return false;
        }
        if (empty($this->datos)) {
            $this->datos = '{}';
        }
        return parent::test();
    }
}