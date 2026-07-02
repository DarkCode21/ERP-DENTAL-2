<?php

namespace FacturaScripts\Plugins\Nominas\Model;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Dinamic\Model\CodeModel;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

class Empleado extends ModelClass
{
	use ModelTrait;
	
	public $idempleado;
	
	public $nombre;
	
	public $idfiscal;
	
	public $numfiscal;
	
	public $direccion;
	
	public $cccss;
	
	public $naf;
	
	public $grupocotizacion;
	
	public $activo;
	
	public static function primaryColumn(): string
    {
        return 'idempleado';
    }
	
	public static function tableName(): string
    {
        return 'empleados';
    }


	public function codeModelSearch(string $query, string $fieldCode = '', array $where = []): array
    {
        $field = empty($fieldCode) ? $this->primaryColumn() : $fieldCode;
        $fields = 'idempleado|numfiscal|nombre';
        $where[] = new DataBaseWhere($fields, \mb_strtolower($query, 'UTF8'), 'LIKE');
		
        return CodeModel::all($this->tableName(), $field, 'nombre', false, $where);
    }

	public function delete(): bool {
		#parent::delete();
		return self::$dataBase->exec("UPDATE empleados SET activo = '0' WHERE idempleado = " . 
									 self::$dataBase->var2str($this->idempleado) . ";");
	}
}


?>