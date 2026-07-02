<?php
/**
 * Copyright (C) 2025-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Extension\Controller;

use Closure;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\ConteoStock;
use FacturaScripts\Dinamic\Model\LineaConteoStock;
use FacturaScripts\Dinamic\Model\LineaConteoStockTraza;
use FacturaScripts\Dinamic\Model\ProductoLote;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditConteoStock
{
    public function execPreviousAction(): Closure
    {
        return function ($action) {
            if ((bool)$this->request->get('ajaxTraza', false)) {
                $this->setTemplate(false);

                switch ($action) {
                    case 'deleteLoteModal':
                        $data = $this->deleteLoteModalAction();
                        break;

                    case 'newLineModal':
                        $data = $this->newLineModalAction();
                        break;

                    case 'newLoteModal':
                        $data = $this->newLoteModalAction();
                        break;

                    case 'saveTrazaModal':
                        $data = $this->saveTrazaModalAction();
                        break;

                    case 'showTrazaModal':
                        $data = $this->showTrazaModalAction();
                        break;

                    case 'showTrazaModalCompleted':
                        $data = $this->showTrazaModalCompletedAction();
                        break;
                }

                $content = array_merge(
                    ['messages' => $this->getMessages()],
                    $data ?? []
                );
                $this->response->setContent(json_encode($content));
                return false;
            }
        };
    }

    public function deleteLoteModalAction(): Closure
    {
        return function () {
            // permisos
            if (false === $this->permissions->allowUpdate) {
                Tools::log()->warning('not-allowed-update');
                return ['deleteLoteModal' => false];
            }

            // cargamos el conteo
            $conteo = new ConteoStock();
            if (false === $conteo->loadFromCode($this->request->get('code'))) {
                Tools::log()->warning('record-not-found');
                return ['deleteLoteModal' => false];
            }

            $lineaConteo = new LineaConteoStock();
            $idLinea = $this->request->request->get('idlinea');
            if (false === $lineaConteo->loadFromCode($idLinea)) {
                Tools::log()->notice('record-not-found');
                return ['deleteLoteModal' => false];
            }

            if ($lineaConteo->idconteo !== $conteo->idconteo) {
                Tools::log()->warning('line-not-belong-to-count');
                return ['deleteLoteModal' => false];
            }

            $lineTraza = new LineaConteoStockTraza();
            if (false === $lineTraza->loadFromCode($this->request->request->get('idlinetraza'))) {
                Tools::log()->notice('record-not-found');
                return ['deleteLoteModal' => false];
            }

            if (false === $lineTraza->delete()) {
                Tools::log()->error('record-delete-error');
                return ['deleteLoteModal' => false];
            }

            return [
                'deleteLoteModal' => true,
                'modalBody' => $this->renderTrazaModal($lineaConteo, $conteo),
                'selectLotes' => $this->renderSelectLotes($lineaConteo, $conteo),
            ];
        };
    }

    public function newLineModalAction(): Closure
    {
        return function () {
            // permisos
            if (false === $this->permissions->allowUpdate) {
                Tools::log()->warning('not-allowed-update');
                return ['newLineModal' => false];
            }

            // cargamos el conteo
            $conteo = new ConteoStock();
            if (false === $conteo->loadFromCode($this->request->get('code'))) {
                Tools::log()->warning('record-not-found');
                return ['newLineModal' => false];
            }

            $lineaConteo = new LineaConteoStock();
            $idLinea = $this->request->request->get('idlinea');
            if (false === $lineaConteo->loadFromCode($idLinea)) {
                Tools::log()->notice('record-not-found');
                return ['newLineModal' => false];
            }

            if ($lineaConteo->idconteo !== $conteo->idconteo) {
                Tools::log()->warning('line-not-belong-to-count');
                return ['newLineModal' => false];
            }

            $lineTraza = new LineaConteoStockTraza();
            $lineTraza->idlote = $this->request->request->get('idlote');
            $lineTraza->quantity = $this->request->request->get('qty', 0);
            $lineTraza->idconteo = $conteo->idconteo;
            $lineTraza->idlinea = $lineaConteo->idlinea;
            if (false === $lineTraza->save()) {
                Tools::log()->error('record-save-error');
                return ['newLineModal' => false];
            }

            return [
                'newLineModal' => true,
                'modalBody' => $this->renderTrazaModal($lineaConteo, $conteo),
                'selectLotes' => $this->renderSelectLotes($lineaConteo, $conteo),
            ];
        };
    }

    public function newLoteModalAction(): Closure
    {
        return function () {
            // permisos
            if (false === $this->permissions->allowUpdate) {
                Tools::log()->warning('not-allowed-update');
                return ['newLoteModal' => false];
            }

            // cargamos el conteo
            $conteo = new ConteoStock();
            if (false === $conteo->loadFromCode($this->request->get('code'))) {
                Tools::log()->warning('record-not-found');
                return ['newLoteModal' => false];
            }

            $lineaConteo = new LineaConteoStock();
            $idLinea = $this->request->request->get('idlinea');
            if (false === $lineaConteo->loadFromCode($idLinea)) {
                Tools::log()->notice('record-not-found');
                return ['newLoteModal' => false];
            }

            if ($lineaConteo->idconteo !== $conteo->idconteo) {
                Tools::log()->warning('line-not-belong-to-count');
                return ['newLoteModal' => false];
            }

            // creamos el nuevo lote
            $lote = new ProductoLote();
            $lote->cantidad = 0;
            $lote->codalmacen = $conteo->codalmacen;
            $lote->fecha = $this->request->request->get('date');
            $lote->idproducto = $lineaConteo->idproducto;
            $lote->numserie = $this->request->request->get('numserie');
            $lote->referencia = $lineaConteo->referencia;
            if (false === $lote->save()) {
                Tools::log()->error('record-save-error-1');
                return ['newLoteModal' => false];
            }

            // añadimos el lote a la trazabilidad de la línea
            $lineTraza = new LineaConteoStockTraza();
            $lineTraza->idlote = $lote->idlote;
            $lineTraza->quantity = $this->request->request->get('qty', 0);
            $lineTraza->date = $lote->fecha;
            $lineTraza->idconteo = $conteo->idconteo;
            $lineTraza->idlinea = $lineaConteo->idlinea;
            if (false === $lineTraza->save()) {
                Tools::log()->error('record-save-error-2');
                return ['newLoteModal' => false];
            }

            return [
                'newLoteModal' => true,
                'modalBody' => $this->renderTrazaModal($lineaConteo, $conteo),
                'selectLotes' => $this->renderSelectLotes($lineaConteo, $conteo),
            ];
        };
    }

    public function saveTrazaModalAction(): Closure
    {
        return function () {
            // permisos
            if (false === $this->permissions->allowUpdate) {
                Tools::log()->warning('not-allowed-update');
                return ['saveTrazaModal' => false];
            }

            // cargamos el conteo
            $conteo = new ConteoStock();
            if (false === $conteo->loadFromCode($this->request->get('code'))) {
                Tools::log()->warning('record-not-found');
                return ['saveTrazaModal' => false];
            }

            $lineaConteo = new LineaConteoStock();
            $idLinea = $this->request->request->get('idlinea');
            if (false === $lineaConteo->loadFromCode($idLinea)) {
                Tools::log()->notice('record-not-found');
                return ['saveTrazaModal' => false];
            }

            if ($lineaConteo->idconteo !== $conteo->idconteo) {
                Tools::log()->warning('line-not-belong-to-count');
                return ['saveTrazaModal' => false];
            }

            $lines = $this->request->request->getArray('lines');
            foreach ($lines as $idline => $qty) {
                $lineTraza = new LineaConteoStockTraza();
                if (false === $lineTraza->loadFromCode($idline)) {
                    Tools::log()->notice('record-not-found');
                    continue;
                }

                $lineTraza->quantity = $qty;
                if (false === $lineTraza->save()) {
                    Tools::log()->error('record-save-error');
                    return ['saveTrazaModal' => false];
                }
            }

            return [
                'saveTrazaModal' => true,
                'modalBody' => $this->renderTrazaModal($lineaConteo, $conteo),
                'selectLotes' => $this->renderSelectLotes($lineaConteo, $conteo),
            ];
        };
    }

    public function showTrazaModalAction(): Closure
    {
        return function () {
            // cargamos el conteo
            $conteo = new ConteoStock();
            if (false === $conteo->loadFromCode($this->request->get('code'))) {
                Tools::log()->warning('record-not-found');
                return ['showTrazaModal' => false];
            }

            $lineaConteo = new LineaConteoStock();
            $idLinea = $this->request->request->get('idlinea');
            if (false === $lineaConteo->loadFromCode($idLinea)) {
                Tools::log()->notice('record-not-found');
                return ['showTrazaModal' => false];
            }

            if ($lineaConteo->idconteo !== $conteo->idconteo) {
                Tools::log()->warning('line-not-belong-to-count');
                return ['showTrazaModal' => false];
            }

            return [
                'showTrazaModal' => true,
                'modalTitle' => Tools::lang()->trans('trazability') . ' ' . $lineaConteo->referencia,
                'modalBody' => $this->renderTrazaModal($lineaConteo, $conteo),
                'selectLotes' => $this->renderSelectLotes($lineaConteo, $conteo),
                'idlinea' => $lineaConteo->idlinea,
            ];
        };
    }

    public function showTrazaModalCompletedAction(): Closure
    {
        return function () {
            // cargamos el conteo
            $conteo = new ConteoStock();
            if (false === $conteo->loadFromCode($this->request->get('code'))) {
                Tools::log()->warning('record-not-found');
                return ['showTrazaModalCompleted' => false];
            }

            $lineaConteo = new LineaConteoStock();
            $idLinea = $this->request->request->get('idlinea');
            if (false === $lineaConteo->loadFromCode($idLinea)) {
                Tools::log()->notice('record-not-found');
                return ['showTrazaModalCompleted' => false];
            }

            if ($lineaConteo->idconteo !== $conteo->idconteo) {
                Tools::log()->warning('line-not-belong-to-count');
                return ['showTrazaModalCompleted' => false];
            }

            return [
                'showTrazaModalCompleted' => true,
                'modalTitle' => Tools::lang()->trans('trazability') . ' ' . $lineaConteo->referencia,
                'modalBody' => $this->renderTrazaModal($lineaConteo, $conteo),
                'idlinea' => $lineaConteo->idlinea,
            ];
        };
    }

    public function renderLinesTableBodyLine(): Closure
    {
        return function (array $dataLine, LineaConteoStock $line, ConteoStock $conteo) {
            $variant = $line->getVariant();
            $product = $variant->getProducto();
            if (false === $product->trazabilidad) {
                return '';
            }

            if ($conteo->completed) {
                // eliminamos html innecesario
                $dataLine[1] = str_replace('<td class="text-center align-middle">', '', $dataLine[1]);
                $dataLine[1] = str_replace('</td>', '', $dataLine[1]);

                // reemplazamos el botón de añadir lote por el de trazabilidad
                $dataLine[1] = '<td class="text-center align-middle">'
                    . '<div class="input-group">'
                    . $dataLine[1]
                    . ''
                    . '<button title="' . Tools::lang()->trans('traceability')
                    . '" type="button" class="btn btn-outline-primary traza-modal-completed" data-idlinea="' . $line->idlinea . '">'
                    . '<i class="fa-solid fa-fingerprint"></i>'
                    . '</button>'
                    . '</div></td>';
            } else {
                // eliminamos html innecesario
                $dataLine[1] = str_replace('</div></td>', '', $dataLine[1]);

                // añadimos el nuevo botón al final de la columna 1
                $dataLine[1] .= '<button title="' . Tools::lang()->trans('traceability')
                    . '" type="button" class="btn btn-outline-primary traza-modal" data-idlinea="' . $line->idlinea . '">'
                    . '<i class="fa-solid fa-fingerprint"></i>'
                    . '</button>'
                    . '</div></td>';
            }

            return $dataLine;
        };
    }

    public function renderSelectLotes(): Closure
    {
        return function (LineaConteoStock $lineaConteo, ConteoStock $conteo) {
            $options = '';

            // obtenemos la variante de la línea, si no existe no mostramos nada
            $variant = $lineaConteo->getVariant();
            if (empty($variant->primaryColumnValue())) {
                return $options;
            }

            // recorremos todos los lotes de esta variante con el mismo almacén del conteo
            $where = [
                new DataBaseWhere('idproducto', $variant->idproducto),
                new DataBaseWhere('referencia', $variant->referencia),
                new DataBaseWhere('codalmacen', $conteo->codalmacen),
            ];
            foreach (ProductoLote::all($where, ['numserie' => 'ASC'], 0, 0) as $lote) {
                // si este lote está añadido ya a la trazabilidad de la línea, saltamos
                $where2 = [
                    new DataBaseWhere('idlote', $lote->idlote),
                    new DataBaseWhere('idconteo', $conteo->idconteo),
                    new DataBaseWhere('idlinea', $lineaConteo->idlinea),
                ];
                $lineTraza = new LineaConteoStockTraza();
                if ($lineTraza->loadFromCode('', $where2)) {
                    continue;
                }

                $options .= '<option value="' . $lote->idlote . '">'
                    . $lote->numserie . ' (' . $lote->cantidad . ') ' . Tools::date($lote->fecha)
                    . '</option>';
            }

            return $options;
        };
    }

    public function renderTrazaModal(): Closure
    {
        return function (LineaConteoStock $lineaConteo, ConteoStock $conteo) {
            $trs = '';
            $where = [
                new DataBaseWhere('idlinea', $lineaConteo->idlinea),
                new DataBaseWhere('idconteo', $conteo->idconteo),
            ];
            foreach (LineaConteoStockTraza::all($where, ['id' => 'ASC'], 0, 0) as $lineTraza) {
                $lote = $lineTraza->getLote();

                $trs .= '<tr data-idlinetraza="' . $lineTraza->id . '">'
                    . '<td class="align-middle">' . $lote->numserie . ' (' . $lote->cantidad . ') ' . Tools::date($lote->fecha) . '</td>'
                    . '<td>'
                    . '<div class="input-group">'
                    . '<input type="number" class="form-control form-control-sm text-center" value="' . $lineTraza->quantity . '">';

                if (false === $conteo->completed) {
                    $trs .= '<button class="btn btn-outline-danger btn-sm deleteLineTraza" data-idlinetraza="' . $lineTraza->id
                        . '" type="button" title="' . Tools::lang()->trans("delete") . '">'
                        . '<i class="fa-solid fa-trash"></i></button>';
                }

                $trs .= '</div>'
                    . '</td>'
                    . '</tr>';
            }

            return $trs;
        };
    }
}
