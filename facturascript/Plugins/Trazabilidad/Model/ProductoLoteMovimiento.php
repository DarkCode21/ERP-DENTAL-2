<?php
/**
 * Copyright (C) 2022-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ProductoLoteMovimiento extends ModelClass
{
    use ModelTrait;

    /** @var float */
    public $cantidad;

    /** @var string */
    public $creationdate;

    /** @var float */
    public $devuelto;

    /** @var string */
    public $docfecha;

    /** @var int */
    public $docid;

    /** @var string */
    public $docmodel;

    /** @var string */
    public $documento;

    /** @var float */
    public $facturado;

    /** @var string */
    public $fecha;

    /** @var int */
    public $id;

    /** @var int */
    public $idclone;

    /** @var int */
    public $idlinea;

    /** @var int */
    public $idlote;

    /** @var string */
    public $lastnick;

    /** @var string */
    public $lastupdate;

    /** @var string */
    public $nick;

    /** @var string */
    public $numserie;

    /** @var string */
    public $referencia;

    /** @var float */
    public $total;

    public function __get(string $name)
    {
        if ($name === 'movement') {
            // devolvemos lo contrario de la cantidad
            // si es positiva devolvemos negativa y viceversa
            // solo en documentos de venta
            if (in_array($this->docmodel, ['AlbaranCliente', 'FacturaCliente'])) {
                return $this->cantidad * -1;
            } else {
                return $this->cantidad;
            }
        }

        return null;
    }

    public function clear(): void
    {
        parent::clear();
        $this->cantidad = 1;
        $this->devuelto = 0;
        $this->facturado = 0;
        $this->fecha = Tools::date();
        $this->total = 0;
    }

    public function delete(): bool
    {
        if (false === parent::delete()) {
            return false;
        }

        // revisamos el clon solo si es un documento de compra o venta
        if ($this->idDocBusinessDocument()) {
            $clone = $this->getClone();
            // si tiene un clon, restamos del facturado del clon
            if ($clone->exists()) {
                $clone->facturado -= $this->cantidad;
                $clone->save();
            }
        }

        $this->getLote()->save();
        return true;
    }

    public function getClone(): self
    {
        $movimiento = new self();
        $movimiento->loadFromCode($this->idclone);
        return $movimiento;
    }

    public function getDocument()
    {
        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $this->docmodel;
        if (!class_exists($modelClass)) {
            return null;
        }

        $doc = new $modelClass();
        if (!$doc->loadFromCode($this->docid)) {
            return null;
        }

        return $doc;
    }

    public function getLote(): ProductoLote
    {
        // si tenemos idlote, buscamos el lote en la base de datos
        $lote = new ProductoLote();
        if ($this->idlote && $lote->loadFromCode($this->idlote)) {
            return $lote;
        }

        // obtenemos el documento
        $doc = $this->getDocument();
        if (empty($doc) || empty($doc->primaryColumnValue())) {
            return $lote;
        }

        // si tenemos numserie, referencia y almacén, buscamos el lote en la base de datos
        $where = [
            new DataBaseWhere('numserie', $this->numserie),
            new DataBaseWhere('referencia', $this->referencia),
            new DataBaseWhere('codalmacen', $doc->codalmacen),
        ];
        $lote->loadFromCode('', $where);
        return $lote;
    }

    public function getLine()
    {
        if (in_array($this->docmodel, [
            'AlbaranProveedor', 'FacturaProveedor', 'AlbaranCliente', 'FacturaCliente',
            'ConteoStock', 'TransferenciaStock'
        ])) {
            $modelClass = '\\FacturaScripts\\Dinamic\\Model\\Linea' . $this->docmodel;
        } else {
            $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $this->docmodel;
        }

        $resultPipe = $this->pipe('getLine');
        if (false === is_null($resultPipe)) {
            $modelClass = $resultPipe;
        }

        if (false === class_exists($modelClass)) {
            return null;
        }

        $line = new $modelClass();
        $line->loadFromCode($this->idlinea);
        return $line;
    }

    public function install(): string
    {
        new Variante();
        new ProductoLote();
        new User();
        return parent::install();
    }

    public function idDocBusinessDocument(): bool
    {
        return in_array($this->docmodel, ['AlbaranCliente', 'FacturaCliente', 'AlbaranProveedor', 'FacturaProveedor']);
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public function save(): bool
    {
        if (false === parent::save()) {
            return false;
        }

        // actualizamos el lote
        $this->getLote()->save();

        return true;
    }

    public static function tableName(): string
    {
        return 'productos_lotes_movs';
    }

    public function test(): bool
    {
        // comprobamos que existe el documento
        $doc = $this->getDocument();
        if (empty($doc) || empty($doc->primaryColumnValue())) {
            return false;
        }

        // comprobamos que existe la línea del documento
        $lineDoc = $this->getLine();
        if (empty($lineDoc) || empty($lineDoc->primaryColumnValue())) {
            return false;
        }

        return $this->testBusinessDocument($doc, $lineDoc) && parent::test();
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        if (empty($this->primaryColumnValue())) {
            return parent::url($type, $list);
        }

        $doc = $this->getDocument();
        if (empty($doc) || empty($doc->primaryColumnValue())) {
            return parent::url($type, $list);
        }

        return $doc->url();
    }

    protected function saveInsert(array $values = []): bool
    {
        $this->creationdate = Tools::dateTime();
        $this->lastnick = null;
        $this->lastupdate = null;
        $this->nick = Session::user()->nick;

        if (false === parent::saveInsert($values)) {
            return false;
        }

        // si no es un documento de compra o venta, terminamos
        if (false === $this->idDocBusinessDocument()) {
            return true;
        }

        // si tenemos un clon, actualizamos el facturado del clon
        $clone = $this->getClone();
        if ($clone->exists()) {
            $clone->facturado += $this->cantidad;
            $clone->save();
        }

        return true;
    }

    protected function saveUpdate(array $values = []): bool
    {
        $this->lastnick = Session::user()->nick;
        $this->lastupdate = Tools::dateTime();
        return parent::saveUpdate($values);
    }

    protected function testBusinessDocument($doc, $line): bool
    {
        // si no es un documento de compra o venta, no hacemos nada
        if (false === $this->idDocBusinessDocument()) {
            return true;
        }

        // actualizamos los datos del movimiento
        $this->docfecha = Tools::dateTime($doc->fecha . ' ' . $doc->hora);
        $this->documento = $doc->codigo;
        $this->referencia = $line->referencia;

        // si tenemos facturado y devuelto, ponemos devuelto a 0
        if ($this->facturado > 0 && $this->devuelto > 0) {
            $this->devuelto = 0;
        }
        $this->total = $this->cantidad - $this->facturado - $this->devuelto;

        // si no tenemos lote, lo buscamos o creamos
        $lote = $this->getLote();
        if (false === $lote->exists()) {
            // no lo hemos encontrado, lo creamos
            $lote->fecha = $this->fecha;
            $lote->idproducto = $line->idproducto;
            $lote->numserie = $this->numserie;
            $lote->referencia = $this->referencia;
            $lote->codalmacen = $doc->codalmacen;
            if (false === $lote->save()) {
                return false;
            }
        }

        if ($this->idlote === null) {
            // guardamos el identificador del lote
            $this->idlote = $lote->idlote;
        }

        if (in_array($this->docmodel, ['AlbaranCliente', 'FacturaCliente'])) {
            $this->fecha = $lote->fecha;
        }

        // la cantidad no puede ser 0
        if ($this->cantidad == 0) {
            Tools::log()->warning('trazabilidad-quantity-zero');
            return false;
        }

        // si la cantidad de la línea es positiva, la del movimiento no puede ser negativa
        if ($line->cantidad > 0 && $this->cantidad < 0) {
            Tools::log()->warning('trazabilidad-quantity-zero');
            return false;
        }

        // si la cantidad de la línea es negativa, la del movimiento no puede ser menor
        if ($line->cantidad < 0 && $this->cantidad < $line->cantidad) {
            Tools::log()->warning('trazabilidad-quantity-exceeded');
            return false;
        }

        // la cantidad no puede ser mayor que la cantidad de la línea del documento
        if ($this->cantidad > $line->cantidad) {
            Tools::log()->warning('trazabilidad-quantity-exceeded');
            return false;
        }

        return true;
    }
}
