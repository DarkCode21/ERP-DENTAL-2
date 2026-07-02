<?php

namespace Facturascripts\Plugins\Cpagos\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Dinamic\Model\FacturaProveedor;

class ContraLinea extends ModelClass
{
	use ModelTrait;

	public $codproveedor;
	public $idfactura;
    public $total;
    public $idcpago;
    
    //save insert
    //llamando los datos de la factura segun el id
    //para traer el total de la factura
    protected function saveInsert(array $values = [])
    {
        $facturaproveedor = new FacturaProveedor();
        if($facturaproveedor->loadFromCode($this->idfactura)){
            $this->total = $facturaproveedor->total;
        }
        return parent::saveInsert($values);
    }

	public function getLines(): array
    {
        $lineaModel = new LineaFactura();
        $where = [new DataBaseWhere('idfactura', $this->idfactura)];
        $order = ['orden' => 'DESC', 'idlinea' => 'ASC'];
        return $lineaModel->all($where, $order, 0, 0);
    }

    public static function primaryColumn(): string
    {
        return "idlc";
    }

    public static function tableName(): string
    {
        return 'contralineas';
    }
}