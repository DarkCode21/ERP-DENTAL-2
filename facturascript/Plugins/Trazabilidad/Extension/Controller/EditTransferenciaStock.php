<?php
/**
 * Copyright (C) 2024-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Extension\Controller;

use Closure;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\LineaTransferenciaStock;
use FacturaScripts\Dinamic\Model\ProductoLote;
use FacturaScripts\Dinamic\Model\TransferenciaStock;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditTransferenciaStock
{
    public function renderLinesTableBodyLine(): Closure
    {
        return function (array $dataLine, LineaTransferenciaStock $line, TransferenciaStock $transference) {
            $variant = $line->getVariant();
            $product = $variant->getProducto();
            if (false === $product->trazabilidad) {
                return '<td></td>';
            }

            if ($transference->completed) {
                $newColumn = '<td class="text-center align-middle">'
                    . $line->numserie
                    . '</td>';
            } else {
                $options = $this->selectLotes($line->numserie, $variant->referencia, $transference->codalmacenorigen);
                $newColumn = '<td class="text-center align-middle">'
                    . '<select class="form-select" name="numserie">'
                    . implode('', $options)
                    . '</select>'
                    . '</td>';
            }

            // añadimos la nueva columna en la segunda posición
            array_splice($dataLine, 2, 0, [$newColumn]);

            return $dataLine;
        };
    }

    public function renderLinesTableHead(): Closure
    {
        return function (array $tableHead, TransferenciaStock $transference) {
            $newColumn = '<th class="text-center">'
                . Tools::lang()->trans('batch-serial-number')
                . '<div class="small">'
                . Tools::lang()->trans('trazability-leyend-batch-serial-number')
                . '</div>'
                . '</th>';

            // añadimos la nueva columna en la segunda posición
            array_splice($tableHead, 2, 0, [$newColumn]);

            return $tableHead;
        };
    }

    public function updateLine(): Closure
    {
        return function (LineaTransferenciaStock $line) {
            $line->numserie = $this->request->get('numserie');
            return $line;
        };
    }

    protected function selectLotes(): Closure
    {
        return function (?string $numserie, string $reference, string $codalmacenorigen) {
            $results = [
                '<option value="">------</option>'
            ];

            $where = [
                new DataBaseWhere('referencia', $reference),
                new DataBaseWhere('codalmacen', $codalmacenorigen),
                new DataBaseWhere('cantidad', 0, '>')
            ];

            foreach (ProductoLote::all($where, ['cantidad' => 'DESC'], 0, 0) as $lote) {
                $selected = $numserie == $lote->numserie ? ' selected' : '';
                $results[] = '<option value="' . $lote->numserie . '"' . $selected . '>'
                    . $lote->numserie . ' (' . (float)$lote->cantidad . ') ' . $lote->fecha
                    . '</option>';
            }

            return $results;
        };
    }
}
