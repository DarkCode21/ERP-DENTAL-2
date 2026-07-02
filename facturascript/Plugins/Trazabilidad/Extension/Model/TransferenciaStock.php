<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Extension\Model;

use Closure;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\LoteMovementManager;
use FacturaScripts\Dinamic\Lib\LoteRebuild;
use FacturaScripts\Dinamic\Model\LineaTransferenciaStock;
use FacturaScripts\Dinamic\Model\ProductoLoteMovimiento;
use FacturaScripts\Dinamic\Model\TransferenciaStock as ModelTransferenciaStock;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class TransferenciaStock
{
    public function addLine(): Closure
    {
        return function (LineaTransferenciaStock $line) {
            if (false === $this->group_lines) {
                $line->cantidad = 1;
                $line->idlinea = null;
                return $line;
            }
        };
    }

    public function clear(): Closure
    {
        return function () {
            $this->group_lines = true;
        };
    }

    public function deleteLineTransfer(): Closure
    {
        return function (LineaTransferenciaStock $line, ModelTransferenciaStock $transfer) {
            // si no hay numserie, terminamos
            if (empty($line->numserie)) {
                return;
            }

            // buscamos el lote con el almacén de destino
            $loteDest = $line->getLoteDest();

            // si no lo encontramos, paramos la ejecución
            if (false === $loteDest->exists()) {
                Tools::log()->warning('lot-was-not-found-in-the-destination-warehouse',
                    [
                        '%numserie%' => $line->numserie,
                        '%referencia%' => $line->referencia,
                        '%codalmacen%' => $transfer->codalmacendestino
                    ]);
                return false;
            }

            // si la cantidad a eliminar es mayor a la disponible en el lote, paramos la ejecución
            if ($line->cantidad > $loteDest->cantidad) {
                Tools::log()->warning('amount-to-be-deleted-is-greater-than-the-amount-available-in-the-lot',
                    [
                        '%cantidad%' => $line->cantidad,
                        '%cantidadlote%' => $loteDest->cantidad,
                        '%numserie%' => $line->numserie,
                        '%referencia%' => $line->referencia,
                        '%codalmacen%' => $transfer->codalmacendestino
                    ]);
                return false;
            }

            // buscamos el lote con el almacén de origen
            $loteOrig = $line->getLoteOrig();

            // si no existe el lote, paramos la ejecución
            if (false === $loteOrig->exists()) {
                Tools::log()->warning('lot-was-not-found-in-the-source-warehouse',
                    [
                        '%numserie%' => $line->numserie,
                        '%referencia%' => $line->referencia,
                        '%codalmacen%' => $transfer->codalmacenorigen
                    ]);
                return false;
            }

            if (false === LoteMovementManager::deleteLineTransferStock($loteOrig, $loteDest, $line, $transfer)) {
                Tools::log()->error('error-when-trying-to-delete-the-movement-of-the-source-lot',
                    [
                        '%numserie%' => $line->numserie,
                        '%referencia%' => $line->referencia,
                        '%codalmacen%' => $transfer->codalmacenorigen
                    ]);
                return false;
            }

            // reconstruimos los lotes del producto
            $product = $line->getProducto();
            LoteRebuild::run($product);
        };
    }

    public function transferStock(): Closure
    {
        return function (LineaTransferenciaStock $line, ModelTransferenciaStock $transfer) {
            // si no hay numserie, terminamos
            if (empty($line->numserie)) {
                return;
            }

            // obtenemos el lote con el almacén de origen
            $loteOrig = $line->getLoteOrig();

            // si no existe el lote, paramos la ejecución
            if (false === $loteOrig->exists()) {
                Tools::log()->warning('lot-was-not-found-in-the-source-warehouse',
                    [
                        '%numserie%' => $line->numserie,
                        '%referencia%' => $line->referencia,
                        '%codalmacen%' => $transfer->codalmacenorigen
                    ]);
                return false;
            }

            // si la nueva cantidad que queremos transferir es mayor a la disponible en el lote, paramos la ejecución
            if ($line->cantidad > $loteOrig->cantidad) {
                Tools::log()->warning('amount-to-be-transferred-is-greater-than-the-amount-available-in-the-lot',
                    [
                        '%cantidad%' => $line->cantidad,
                        '%cantidadlote%' => $loteOrig->cantidad,
                        '%numserie%' => $line->numserie,
                        '%referencia%' => $line->referencia,
                        '%codalmacen%' => $transfer->codalmacenorigen,
                    ]);
                return false;
            }

            // buscamos el lote con el almacén de destino
            $loteDest = $line->getLoteDest();

            // si no lo encontramos, lo creamos
            if (false === $loteDest->exists()) {
                $loteDest->idproducto = $loteOrig->idproducto;
                $loteDest->referencia = $loteOrig->referencia;
                $loteDest->numserie = $loteOrig->numserie;
                $loteDest->fecha = $loteOrig->fecha;
                $loteDest->codalmacen = $transfer->codalmacendestino;
                if (false === $loteDest->save()) {
                    Tools::log()->error('error-when-creating-the-destination-lot',
                        [
                            '%numserie%' => $line->numserie,
                            '%referencia%' => $line->referencia,
                            '%codalmacen%' => $transfer->codalmacendestino
                        ]);
                    return false;
                }
            }

            if (false === LoteMovementManager::addLineTransferStock($loteOrig, $loteDest, $line, $transfer)) {
                Tools::log()->error('error-when-creating-the-movement-for-the-source-lot',
                    [
                        '%numserie%' => $line->numserie,
                        '%referencia%' => $line->referencia,
                        '%codalmacen%' => $transfer->codalmacenorigen
                    ]);
                return false;
            }

            // reconstruimos los lotes del producto de la línea
            $product = $line->getProducto();
            LoteRebuild::run($product);
        };
    }
}
