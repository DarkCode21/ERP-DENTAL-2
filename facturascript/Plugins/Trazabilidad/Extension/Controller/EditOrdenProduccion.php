<?php
/**
 * Copyright (C) 2022-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\ProductoLote;

/**
 * Extension for EditOrdenProduccion controller (Produccion plugin).
 * Handles the 'selectIngredient' action to return available lots
 * for a given ingredient reference.
 */
class EditOrdenProduccion
{
    public function execPreviousAction(): Closure
    {
        return function ($action) {
            if ($action === 'selectIngredient') {
                return $this->selectIngredientLotesAction();
            }
        };
    }

    protected function selectIngredientLotesAction(): Closure
    {
        return function () {
            $this->setTemplate(false);
            $results = [];

            $referencia = $this->request->request->get('referencia', '');
            $codalmacen = $this->request->request->get('codalmacen', '');

            if (empty($referencia)) {
                $this->response->setContent(json_encode($results));
                return false;
            }

            $where = [
                new DataBaseWhere('referencia', $referencia),
            ];
            if (false === empty($codalmacen)) {
                $where[] = new DataBaseWhere('codalmacen', $codalmacen);
            }

            foreach (ProductoLote::all($where, ['fecha' => 'ASC'], 0, 0) as $lote) {
                $label = $lote->numserie . ' (' . (float)$lote->cantidad . ')';
                if (false === empty($lote->fecha_caducidad)) {
                    $label .= ' · cad. ' . $lote->fecha_caducidad;
                }
                $results[] = [
                    'key' => $lote->idlote,
                    'value' => $label,
                ];
            }

            $this->response->setContent(json_encode($results));
            return false;
        };
    }
}
