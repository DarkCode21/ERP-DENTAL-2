<?php
/**
 * Copyright (C) 2022-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Extension\Model;

use Closure;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\ProductoLote;
use FacturaScripts\Dinamic\Model\ProductoLoteMovimiento;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait TrazabilidadLineaModelTrait
{
    // elimina todos los movimientos de trazabilidad de la línea del documento
    // tanto en compras como en ventas
    public function deleteMovimientosLinea(): Closure
    {
        return function () {
            foreach ($this->getMovimientosLinea() as $movimiento) {
                if (false === $movimiento->delete()) {
                    return;
                }
            }
        };
    }

    public function getMovimientosLinea(): Closure
    {
        return function () {
            $doc = $this->getDocument();
            if (empty($doc) || empty($doc->primaryColumnValue())) {
                return [];
            }

            $where = [
                new DataBaseWhere('idlinea', $this->idlinea),
                new DataBaseWhere('docid', $doc->primaryColumnValue()),
                new DataBaseWhere('docmodel', $doc->modelClassName())
            ];
            $orderBy = ['id' => 'ASC'];
            return ProductoLoteMovimiento::all($where, $orderBy, 0, 0);
        };
    }

    // asocia los lotes disponibles del producto con los movimientos de trazabilidad
    // solo disponible para las líneas de venta
    public function insertBatchSerial(): Closure
    {
        return function () {
            // si no tiene activada la asignación automática de lotes terminamos
            if (false === (bool)Tools::settings('default', 'autobatchserialsales', false)) {
                return;
            }

            // obtenemos el producto de la línea y preguntamos si el producto tiene trazabilidad
            $product = $this->getProducto();
            if (false === $product->trazabilidad) {
                return;
            }

            // guardamos la cantidad de la línea en una variable temporal
            $tempQty = $this->cantidad;

            // obtenemos el documento
            $doc = $this->getDocument();
            if (empty($doc) || empty($doc->primaryColumnValue())) {
                return;
            }

            // si la clase del documento no es de venta, terminamos
            if (false === in_array($doc->modelClassName(), ['AlbaranCliente', 'FacturaCliente'])) {
                return;
            }

            // buscamos y recorremos todos los lotes de la variante que tengan cantidad > 0
            $where = [
                new DataBaseWhere('referencia', $this->referencia),
                new DataBaseWhere('cantidad', 0, '>'),
                new DataBaseWhere('codalmacen', $doc->codalmacen),
            ];
            $orderBy = ['fecha' => 'ASC', 'idlote' => 'ASC'];
            foreach (ProductoLote::all($where, $orderBy, 0, 0) as $lote) {

                // si la cantidad temporal es igual a 0 o inferior terminamos
                if ($tempQty <= 0) {
                    break;
                }

                // creamos el objeto del movimiento del lote-variante
                $loteMovimiento = new ProductoLoteMovimiento();
                $loteMovimiento->cantidad = min($tempQty, $lote->cantidad);
                $loteMovimiento->docfecha = Tools::dateTime($doc->fecha . ' ' . $doc->hora);
                $loteMovimiento->docid = $doc->primaryColumnValue();
                $loteMovimiento->docmodel = $doc->modelClassName();
                $loteMovimiento->documento = $doc->codigo;
                $loteMovimiento->fecha = $lote->fecha;
                $loteMovimiento->idlinea = $this->idlinea;
                $loteMovimiento->idlote = $lote->idlote;
                $loteMovimiento->numserie = $lote->numserie;
                $loteMovimiento->referencia = $this->referencia;
                if (false === $loteMovimiento->save()) {
                    return;
                }

                // actualizamos la cantidad temporal
                $tempQty -= $loteMovimiento->cantidad;
            }
        };
    }

    // actualiza el stock del lote cuando un documento se cancela y/o se vuelve a abrir
    public function updateStockLoteMovimiento(): Closure
    {
        return function (string $field) {
            $movimientos = $this->getMovimientosLinea();
            if (empty($movimientos)) {
                return;
            }

            foreach ($movimientos as $mov) {
                $mov->devuelto = $this->actualizastock == 0 && $this->servido == 0 && $this->cantidad > 0 ? $mov->cantidad : 0;
                if (false === $mov->save()) {
                    return;
                }
            }
        };
    }
}
