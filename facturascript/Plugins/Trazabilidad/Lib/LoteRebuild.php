<?php
/**
 * Copyright (C) 2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Lib;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\DataSrc\Almacenes;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\LineaTransferenciaStock;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\ProductoLote;
use FacturaScripts\Dinamic\Model\ProductoLoteMovimiento;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class LoteRebuild
{
    /** @var DataBase */
    protected static $db;

    public static function run(Producto $product): void
    {
        // si el producto no tiene trazabilidad, terminamos
        if (false === $product->trazabilidad) {
            return;
        }

        static::checkWarehouseLote($product);
        static::checkQuantities($product);
    }

    protected static function checkWarehouseLote(Producto $product): void
    {
        // obtenemos las referencias de las variantes del producto
        $variantsRef = [];
        foreach ($product->getVariants() as $variant) {
            $variantsRef[] = $variant->referencia;
        }

        // recorremos los movimientos del producto
        $where = [new DataBaseWhere('referencia', implode(',', $variantsRef), 'IN')];
        $order = ['id' => 'ASC'];
        foreach (ProductoLoteMovimiento::all($where, $order, 0, 0) as $movement) {
            // si el movimiento no es de un documento de compra o venta, continuamos
            if (false === $movement->idDocBusinessDocument()) {
                continue;
            }

            // obtenemos el documento del movimiento
            $doc = $movement->getDocument();

            // si el documento no existe, terminamos
            if (empty($doc) || empty($doc->primaryColumnValue())) {
                Tools::log()->info('deleted-movement-no-document', [
                    '%movement%' => $movement->id,
                    '%document%' => $movement->documento . ' (' . $movement->docmodel . ')',
                ]);
                continue;
            }

            // obtenemos el lote del movimiento
            $lote = $movement->getLote();

            // si el lote no tiene almacén, lo asignamos
            if (empty($lote->codalmacen)) {
                self::db()->exec(
                    'UPDATE ' . $lote->tableName()
                    . ' SET codalmacen = ' . self::db()->var2str($doc->codalmacen)
                    . ' WHERE idlote = ' . self::db()->var2str($lote->idlote)
                );

                Tools::log()->info('assigned-warehouse-to-lote', [
                    '%warehouse%' => Almacenes::get($doc->codalmacen)->nombre,
                    '%lote%' => $lote->referencia . '|' . Almacenes::get($doc->codalmacen)->nombre . '|' . $lote->numserie,
                ]);
                continue;
            }

            // si el almacén del documento es igual al del lote, continuamos
            if ($doc->codalmacen === $lote->codalmacen) {
                continue;
            }

            // si el almacén del documento es distinto al del lote
            // comprobamos si existe un lote con el almacén del documento
            $where = [
                new DataBaseWhere('numserie', $movement->numserie),
                new DataBaseWhere('idproducto', $product->idproducto),
                new DataBaseWhere('referencia', $movement->referencia),
                new DataBaseWhere('codalmacen', $doc->codalmacen)
            ];

            // si existe, actualizamos el movimiento
            $newLote = new ProductoLote();
            if ($newLote->loadFromCode('', $where)) {
                self::db()->exec(
                    'UPDATE ' . $movement->tableName()
                    . ' SET idlote = ' . self::db()->var2str($newLote->idlote)
                    . ' WHERE id = ' . self::db()->var2str($movement->id)
                );

                Tools::log()->info('updated-movement-to-lote', [
                    '%movement%' => $movement->documento . ' (' . $movement->docmodel . ')',
                    '%lote%' => $newLote->referencia . '|' . Almacenes::get($newLote->codalmacen)->nombre . '|' . $newLote->numserie,
                ]);
                continue;
            }

            // si no existe, creamos un nuevo lote
            $newLote->fecha = $movement->fecha;
            $newLote->idproducto = $product->idproducto;
            $newLote->numserie = $movement->numserie;
            $newLote->referencia = $movement->referencia;
            $newLote->codalmacen = $doc->codalmacen;

            if ($newLote->save()) {
                // actualizamos el movimiento
                self::db()->exec(
                    'UPDATE ' . $movement->tableName()
                    . ' SET idlote = ' . self::db()->var2str($newLote->idlote)
                    . ' WHERE id = ' . self::db()->var2str($movement->id)
                );

                Tools::log()->info('created-lote-for-movement', [
                    '%movement%' => $movement->documento . ' (' . $movement->docmodel . ')',
                    '%lote%' => $newLote->referencia . '|' . Almacenes::get($newLote->codalmacen)->nombre . '|' . $newLote->numserie,
                ]);
            }
        }
    }

    protected static function checkQuantities(Producto $product): void
    {
        // recorremos los lotes del producto
        $order = ['idlote' => 'ASC'];
        $where = [new DataBaseWhere('idproducto', $product->idproducto)];
        foreach (ProductoLote::all($where, $order, 0, 0) as $lote) {
            $lote->save();
        }
    }

    protected static function db(): DataBase
    {
        if (null === self::$db) {
            self::$db = new DataBase();
        }

        return self::$db;
    }
}
