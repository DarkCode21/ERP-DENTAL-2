<?php

namespace FacturaScripts\Plugins\Cpagos\Model;

use FacturaScripts\Core\Model\Base;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\ContraLinea;

class Cpago extends Base\ModelClass{
    use Base\ModelTrait;
    
    public $codproveedor;
    public $creationdate;
    public $idcpago;
    public $linetotal;
    public $user;
    public $supplier;
    public $paymentdate;
    public $totalcontra;
    
    public function clear() {
        parent::clear();
        $this->creationdate = date('d-m-y');
        $this->paymentdate = date("Y-m-d", strtotime($this->creationdate . "+ 1 month"));
        $this->verified=false;
    }
   //devuelve un array con las lineas de la contraseña
    public function getLines(): array
    {
    $line = new ContraLinea();
    $where = [new DataBaseWhere("idfacturaprov", $this->id)];
    return $line->all($where);
    }
   //total de la contraseña de pago
    public function getlinestotal(): array
    {
        $line = new ContraLinea();
        $where = [new DataBaseWhere("idcpago", $this->idcpago)];
        $this->totalcontra = $line->count($where);
    }
    public function loadFromData(array $data = [], array $exclude = [])
    {
        parent::loadFromData($data, $exclude);
        $lineas = new ContraLinea();
        $where = [new DataBaseWhere("idcpago", $this->idcpago)];
        $total = 0;
        foreach ($lineas->all($where) as $linea) {
            $total += $linea->total;
        }
        $this->linetotal = $total;
    }
    public static function primaryColumn(): string {
        return 'idcpago';
    }
    public static function tableName(): string {
        return 'cpagos';
    }
}