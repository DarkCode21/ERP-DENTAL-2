<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Extension\Model;

use Closure;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\LoteMovementManager;
use FacturaScripts\Dinamic\Lib\LoteRebuild;
use FacturaScripts\Dinamic\Model\ConteoStock as ModelConteoStock;
use FacturaScripts\Dinamic\Model\LineaConteoStock;
use FacturaScripts\Dinamic\Model\ProductoLoteMovimiento;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ConteoStock
{
    public function deleteLineCounting(): Closure
    {
        return function (LineaConteoStock $line, ModelConteoStock $conteo) {
            // si no hay líneas de trazabilidad en la línea, terminamos
            $linesTraza = $line->getLinesTraza();
            if (empty($linesTraza)) {
                return;
            }

            // recorremos las líneas de trazabilidad
            foreach ($linesTraza as $lineTraza) {
                $lote = $lineTraza->getLote();
                if (empty($lote->primaryColumnValue())) {
                    Tools::log()->warning('lot-not-found-in-the-destination-warehouse',
                        [
                            '%numserie%' => $line->numserie,
                            '%referencia%' => $line->referencia,
                            '%codalmacen%' => $conteo->codalmacen
                        ]);
                    return false;
                }

                if (false === LoteMovementManager::deleteLineCounting($lote, $line, $conteo)) {
                    Tools::log()->error('error-when-trying-to-delete-the-movement-of-the-destination-lot',
                        [
                            '%numserie%' => $line->numserie,
                            '%referencia%' => $line->referencia,
                            '%codalmacen%' => $conteo->codalmacen
                        ]);
                    return false;
                }
            }

            // reconstruimos los lotes del producto
            $product = $line->getProducto();
            LoteRebuild::run($product);
        };
    }

    public function updateStock(): Closure
    {
        return function (LineaConteoStock $line, ModelConteoStock $conteo, float $stock) {
            // si no hay líneas de trazabilidad en la línea, terminamos
            $linesTraza = $line->getLinesTraza();
            if (empty($linesTraza)) {
                return;
            }

            // recorremos las líneas de trazabilidad
            foreach ($linesTraza as $lineTraza) {
                $lote = $lineTraza->getLote();
                if (empty($lote->primaryColumnValue())) {
                    Tools::log()->warning('lot-not-found-in-the-destination-warehouse',
                        [
                            '%numserie%' => $line->numserie,
                            '%referencia%' => $line->referencia,
                            '%codalmacen%' => $conteo->codalmacen
                        ]);
                    return false;
                }

                if (false === LoteMovementManager::addLineCounting($lote, $line, $conteo, $lineTraza->quantity)) {
                    Tools::log()->error('error-when-creating-the-movement-for-the-destination-lot',
                        [
                            '%numserie%' => $line->numserie,
                            '%referencia%' => $line->referencia,
                            '%codalmacen%' => $conteo->codalmacen
                        ]);
                    return false;
                }
            }

            // reconstruimos los lotes del producto de la línea
            $product = $line->getProducto();
            LoteRebuild::run($product);
        };
    }
}
