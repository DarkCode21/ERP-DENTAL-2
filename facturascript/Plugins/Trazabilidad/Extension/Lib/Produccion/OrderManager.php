<?php
/**
 * Copyright (C) 2022-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Extension\Lib\Produccion;

use Closure;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\ProductoLote;
use FacturaScripts\Dinamic\Model\ProductoLoteMovimiento;
use FacturaScripts\Plugins\Produccion\Model\OrdenIngrediente;

/**
 * Extension for Produccion's OrderManager.
 * When a production order is confirmed, registers a negative lot movement
 * for each raw material ingredient that has an assigned lot (idlote).
 */
class OrderManager
{
    /**
     * Hook called by pipeFalse('removeStock', $orderLine->idlinea) in OrderManager.
     * Creates a ProductoLoteMovimiento (negative = consumption) for the lot.
     */
    public function removeStock(): Closure
    {
        return function (int $idlinea): bool {
            $ingrediente = new OrdenIngrediente();
            if (false === $ingrediente->loadFromCode($idlinea)) {
                return true;
            }

            // si el ingrediente no tiene lote asignado, no hay nada que registrar
            if (empty($ingrediente->idlote)) {
                return true;
            }

            $lote = new ProductoLote();
            if (false === $lote->loadFromCode($ingrediente->idlote)) {
                Tools::log()->warning('lote-not-found', ['%idlote%' => $ingrediente->idlote]);
                return true;
            }

            // comprobamos si ya existe el movimiento para evitar duplicados
            $loteMov = new ProductoLoteMovimiento();
            $loteMov->cantidad = $ingrediente->cantidad * -1;
            $loteMov->docid = $ingrediente->idorden;
            $loteMov->docmodel = 'OrdenProduccion';
            $loteMov->docfecha = Tools::date();
            $loteMov->documento = Tools::lang()->trans('production') . ' ' . $ingrediente->idorden;
            $loteMov->fecha = Tools::date();
            $loteMov->idlinea = $ingrediente->id;
            $loteMov->idlote = $lote->idlote;
            $loteMov->numserie = $lote->numserie;
            $loteMov->referencia = $ingrediente->referencia;

            if (false === $loteMov->save()) {
                Tools::log()->warning('error-saving-lot-movement');
            }

            return true;
        };
    }
}
