<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Lib;

use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\ConteoStock;
use FacturaScripts\Dinamic\Model\LineaConteoStock;
use FacturaScripts\Dinamic\Model\LineaTransferenciaStock;
use FacturaScripts\Dinamic\Model\ProductoLote;
use FacturaScripts\Dinamic\Model\ProductoLoteMovimiento;
use FacturaScripts\Dinamic\Model\TransferenciaStock;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class LoteMovementManager
{
    public static function addLineCounting(ProductoLote $lote, LineaConteoStock $line, ConteoStock $conteo, float $stock): bool
    {
        $docid = $conteo->primaryColumnValue();
        $docmodel = $conteo->modelClassName();
        $cantidad = $stock - $lote->cantidad;

        $loteMov = new ProductoLoteMovimiento();
        $where = [
            new DataBaseWhere('idlote', $lote->idlote),
            new DataBaseWhere('idlinea', $line->idlinea),
            new DataBaseWhere('docid', $conteo->idconteo),
            new DataBaseWhere('docmodel', $conteo->modelClassName()),
            new DataBaseWhere('numserie', $lote->numserie),
            new DataBaseWhere('referencia', $line->referencia),
        ];
        if (false === $loteMov->loadFromCode('', $where)) {
            $loteMov->fecha = $conteo->fechafin;
            $loteMov->numserie = $lote->numserie;
            $loteMov->docid = $docid;
            $loteMov->docmodel = $docmodel;
            $loteMov->referencia = $line->referencia;
            $loteMov->docfecha = $conteo->fechafin;
            $loteMov->documento = Tools::textBreak($conteo->observaciones, 20);
            $loteMov->idlinea = $line->idlinea;
            $loteMov->idlote = $lote->idlote;
            if (empty($cantidad)) {
                return true;
            }
        }

        $loteMov->cantidad = $cantidad;
        return empty($loteMov->cantidad) ? $loteMov->delete() : $loteMov->save();
    }

    public static function addLineTransferStock(ProductoLote $loteOrig, ProductoLote $loteDest, LineaTransferenciaStock $line, TransferenciaStock $transfer): void
    {
        static::addLineTransferStockMovement($loteOrig, $line->cantidad * -1, $transfer, $line);
        static::addLineTransferStockMovement($loteDest, $line->cantidad, $transfer, $line);
    }

    public static function deleteLineCounting(ProductoLote $lote, LineaConteoStock $line, ConteoStock $conteo): bool
    {
        $loteMov = new ProductoLoteMovimiento();
        $where = [
            new DataBaseWhere('idlote', $lote->idlote),
            new DataBaseWhere('idlinea', $line->idlinea),
            new DataBaseWhere('docid', $conteo->idconteo),
            new DataBaseWhere('docmodel', $conteo->modelClassName()),
            new DataBaseWhere('numserie', $lote->numserie),
            new DataBaseWhere('referencia', $line->referencia),
        ];

        if (false === $loteMov->loadFromCode('', $where)) {
            return true;
        }

        return $loteMov->delete();
    }

    public static function deleteLineTransferStock(ProductoLote $loteOrig, ProductoLote $loteDest, LineaTransferenciaStock $line, TransferenciaStock $transfer): bool
    {
        if (false === static::deleteLineTransferStockMovement($loteOrig, $line, $transfer)) {
            return false;
        }

        return static::deleteLineTransferStockMovement($loteDest, $line, $transfer);
    }

    protected static function addLineTransferStockMovement(ProductoLote $lote, float $cantidad, TransferenciaStock $transfer, LineaTransferenciaStock $line): bool
    {
        $loteMov = new ProductoLoteMovimiento();
        $where = [
            new DataBaseWhere('idlote', $lote->idlote),
            new DataBaseWhere('idlinea', $line->idlinea),
            new DataBaseWhere('docid', $transfer->primaryColumnValue()),
            new DataBaseWhere('docmodel', $transfer->modelClassName()),
            new DataBaseWhere('referencia', $line->referencia)
        ];
        if (false === $loteMov->loadFromCode('', $where)) {
            $loteMov->docfecha = $transfer->fecha_completed;
            $loteMov->docid = $transfer->idtrans;
            $loteMov->docmodel = $transfer->modelClassName();
            $loteMov->documento = Tools::textBreak($transfer->observaciones, 20);
            $loteMov->idlinea = $line->idlinea;
            $loteMov->idlote = $lote->idlote;
            $loteMov->numserie = $line->numserie;
            $loteMov->referencia = $line->referencia;
            if (empty($cantidad)) {
                return true;
            }
        }

        $loteMov->cantidad = $cantidad;
        return empty($loteMov->cantidad) ? $loteMov->delete() : $loteMov->save();
    }

    protected static function deleteLineTransferStockMovement(ProductoLote $lote, LineaTransferenciaStock $line, TransferenciaStock $transfer): bool
    {
        $loteMov = new ProductoLoteMovimiento();
        $where = [
            new DataBaseWhere('idlote', $lote->idlote),
            new DataBaseWhere('idlinea', $line->idlinea),
            new DataBaseWhere('docid', $transfer->idtrans),
            new DataBaseWhere('docmodel', $transfer->modelClassName()),
            new DataBaseWhere('numserie', $line->numserie),
            new DataBaseWhere('referencia', $line->referencia),
        ];

        if ($loteMov->loadFromCode('', $where) && false === $loteMov->delete()) {
            return false;
        }

        return true;
    }
}
