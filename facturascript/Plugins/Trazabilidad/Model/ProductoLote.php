<?php

/**
 * Copyright (C) 2022-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Model\Base\ProductRelationTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Almacen;
use FacturaScripts\Dinamic\Model\LineaTransferenciaStock;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ProductoLote extends ModelClass
{
    use ModelTrait;
    use ProductRelationTrait;

    /** @var float */
    public $cantidad;

    /** @var string */
    public $codalmacen;

    /** @var string */
    public $fecha;

    /** @var string */
    public $fecha_caducidad;

    /** @var int */
    public $idlote;

    /** @var int */
    public $idproducto;

    /** @var string */
    public $numserie;

    /** @var string */
    public $referencia;

    public function clear(): void
    {
        parent::clear();
        $this->cantidad = 0;
        $this->fecha = Tools::date();
    }

    public function delete(): bool
    {
        // antes de borrar comprobamos que el lote no tenga movimientos
        if (false === empty($this->getMovimientos())) {
            Tools::log()->error('cant-delete-lote-with-movs');
            return false;
        }

        return parent::delete();
    }

    public function getMovimientos(): array
    {
        $loteMovimiento = new ProductoLoteMovimiento();
        $where = [new DataBaseWhere('idlote', $this->idlote)];
        $orderBy = ['fecha' => 'ASC', 'id' => 'ASC'];
        return $loteMovimiento->all($where, $orderBy, 0, 0);
    }

    public function getWarehouse(): Almacen
    {
        $model = new Almacen();
        $model->loadFromCode($this->codalmacen);
        return $model;
    }

    public function install(): string
    {
        new Almacen();
        new Producto();
        new Variante();
        return parent::install();
    }

    public static function primaryColumn(): string
    {
        return "idlote";
    }

    public function primaryDescriptionColumn(): string
    {
        return 'numserie';
    }

    public function save(): bool
    {
        $this->updateQuantity();
        return parent::save();
    }

    public static function tableName(): string
    {
        return "productos_lotes";
    }

    public function test(): bool
    {
        // si el producto no tiene trazabilidad, no se puede crear el lote
        if (false === $this->getProducto()->trazabilidad) {
            Tools::log()->error('cant-create-lote-without-trazability');
            return false;
        }

        $this->numserie = Tools::noHtml($this->numserie);
        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        return $type === 'list' ? $this->getProducto()->url() : parent::url($type, $list);
    }

    protected function updateQuantity(): void
    {
        // calculamos la cantidad
        $this->cantidad = 0;
        foreach ($this->getMovimientos() as $mov) {
            if (in_array($mov->docmodel, ['FacturaCliente', 'AlbaranCliente'])) {
                // las ventas restan
                $this->cantidad -= $mov->total;
                continue;
            } elseif (in_array($mov->docmodel, ['FacturaProveedor', 'AlbaranProveedor'])) {
                // las compras suman
                $this->cantidad += $mov->total;
            } elseif ($mov->docmodel === 'TransferenciaStock') {
                $this->cantidad += $mov->cantidad;
            } elseif ($mov->docmodel === 'ConteoStock') {
                $this->cantidad += $mov->cantidad;
            }

            $this->pipe('updateQuantityMovement', $mov);
        }

        $this->pipe('updateQuantity');
    }
}
