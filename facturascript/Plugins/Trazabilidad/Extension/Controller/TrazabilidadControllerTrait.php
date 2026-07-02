<?php
/**
 * Copyright (C) 2022-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\ProductoLote;
use FacturaScripts\Dinamic\Model\ProductoLoteMovimiento;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait TrazabilidadControllerTrait
{
    // desactiva la pestaña de trazabilidad o el botón de añadir cuando corresponda
    public function disableTrazabilidad(): Closure
    {
        return function ($viewName, $linesWithTraza) {
            // si no hay líneas con trazabilidad, desactivamos la pestaña de trazabilidad
            if (empty($linesWithTraza)) {
                $this->setSettings($viewName, 'active', false);
                return;
            }

            // añadimos la cantidad pendiente de vincular con la trazabilidad
            $pendiente = 0.0;
            foreach ($linesWithTraza as $line) {
                $pendiente += $line['cantidad'];
            }

            // descontamos la cantidad ya vinculada a trazabilidad
            $mvn = $this->getMainViewName();
            $where = [
                new DataBaseWhere('docid', $this->tab($mvn)->model->primaryColumnValue()),
                new DataBaseWhere('docmodel', $this->tab($mvn)->model->modelClassName())
            ];
            foreach (ProductoLoteMovimiento::all($where) as $movimiento) {
                $pendiente -= $movimiento->cantidad;
            }

            // redondeamos la cantidad pendiente
            $pendiente = round($pendiente, 5);

            // si no hay cantidad pendiente de vincular, desactivamos el botón de añadir
            if ($pendiente == 0) {
                $this->setSettings($viewName, 'btnNew', false);
                return;
            }

            // si hay cantidad pendiente de vincular y no se ha seleccionado una pestaña, marcamos la pestaña de trazabilidad como activa
            if (empty($this->request->get('activetab', ''))) {
                $this->active = $viewName;
            }
        };
    }

    public function getLinesWithTrazabilidad(): Closure
    {
        return function () {
            $linesWithTraza = [];
            $mvn = $this->getMainViewName();

            // recorremos las líneas del documento
            foreach ($this->tab($mvn)->model->getLines() as $num => $line) {
                // si el producto no tiene trazabilidad activada, lo saltamos
                if (false === $line->getProducto()->trazabilidad) {
                    continue;
                }

                $linesWithTraza[] = [
                    'cantidad' => $line->cantidad,
                    'idlinea' => $line->idlinea,
                    'numlinea' => $num + 1,
                    'referencia' => $line->referencia,
                    'variante' => $line->getVariante()
                ];
            }

            return $linesWithTraza;
        };
    }

    // obtiene la cantidad automáticamente que se debe poner al seleccionar un lote en ventas
    public function getQtyNewLoteCli(): Closure
    {
        return function () {
            $this->setTemplate(false);
            $docid = $this->request->request->get('docid');
            $docmodel = $this->request->request->get('docmodel');
            $idlinea = $this->request->request->get('idlinea');
            $numserie = $this->request->request->get('numserie');

            // obtenemos la línea del documento
            $className = 'FacturaScripts\\Dinamic\\Model\\Linea' . $docmodel;
            $linea = new $className();
            if (false === $linea->loadFromCode($idlinea)) {
                $this->response->setContent(json_encode(['numserie' => '', 'qty' => 1]));
                return false;
            }

            // obtenemos el lote seleccionado
            $loteProduct = new ProductoLote();
            $whereLote = [
                new DataBaseWhere('referencia', $linea->referencia),
                new DataBaseWhere('numserie', $numserie)
            ];
            if (false === $loteProduct->loadFromCode('', $whereLote)) {
                $this->response->setContent(json_encode(['numserie' => '', 'qty' => 1]));
                return false;
            }

            // obtenemos la suma de las cantidades de los lotes de trazabilidad
            $cantidadLotes = 0;
            $whereMovimiento = [
                new DataBaseWhere('docid', $docid),
                new DataBaseWhere('docmodel', $docmodel),
                new DataBaseWhere('idlinea', $idlinea)
            ];
            foreach (ProductoLoteMovimiento::all($whereMovimiento) as $movement) {
                $cantidadLotes += $movement->cantidad;
            }

            // calculamos la cantidad que falta para completar en el nuevo lote
            $resto = $linea->cantidad - $cantidadLotes;

            // obtenemos los lotes del producto
            $numserieRes = '';
            foreach (ProductoLote::all($whereLote, ['fecha' => 'ASC']) as $lote) {
                if ($lote->cantidad >= $resto) {
                    $numserieRes = $lote->numserie;
                    break;
                }
            }

            $this->response->setContent(json_encode(['numserie' => $numserieRes, 'qty' => $resto]));
            return false;
        };
    }

    // obtiene la cantidad automáticamente que se debe poner al añadir un nuevo lote en compras
    public function getQtyNewLoteProv(): Closure
    {
        return function () {
            $this->setTemplate(false);
            $docid = $this->request->request->get('docid');
            $docmodel = $this->request->request->get('docmodel');
            $idlinea = $this->request->request->get('idlinea');

            // obtenemos la línea del documento
            $className = 'FacturaScripts\\Dinamic\\Model\\Linea' . $docmodel;
            $linea = new $className();
            if (false === $linea->loadFromCode($idlinea)) {
                $this->response->setContent(json_encode(['qty' => 1]));
                return false;
            }

            // obtenemos la suma de las cantidades de los lotes de trazabilidad
            $cantidadLotes = 0;
            $where = [
                new DataBaseWhere('docid', $docid),
                new DataBaseWhere('docmodel', $docmodel),
                new DataBaseWhere('idlinea', $idlinea)
            ];
            foreach (ProductoLoteMovimiento::all($where) as $movement) {
                $cantidadLotes += $movement->cantidad;
            }

            // calculamos la cantidad que falta para completar en el nuevo lote
            $resto = $linea->cantidad - $cantidadLotes;
            $this->response->setContent(json_encode(['qty' => $resto]));
            return false;
        };
    }

    // establece las variantes disponibles
    // al añadir trazabilidad en los documentos de compra y venta
    public function loadSelectTraza(): Closure
    {
        return function ($viewName, $lineasWithTraza, $columnName) {
            $mvn = $this->getMainViewName();
            $column = $this->tab($viewName)->columnForName($columnName);
            if ($column && $column->widget->getType() === 'selectTraza') {
                $customValues = [];
                foreach ($lineasWithTraza as $linea) {
                    // preguntamos si la línea ya tiene toda su trazabilidad completa
                    $qtyMov = 0;
                    $where = [
                        new DataBaseWhere('idlinea', $linea['idlinea']),
                        new DataBaseWhere('referencia', $linea['referencia']),
                        new DataBaseWhere('docid', $this->views[$mvn]->model->primaryColumnValue()),
                        new DataBaseWhere('docmodel', $this->views[$mvn]->model->modelClassName()),
                    ];
                    foreach (ProductoLoteMovimiento::all($where) as $mov) {
                        $qtyMov += $mov->cantidad;
                    }

                    $customValues[] = [
                        'value' => $linea['idlinea'],
                        'title' => $linea['numlinea'] . '. ' . $linea['referencia'] . ' (' . $linea['cantidad'] . ')',
                        'disabled' => $qtyMov == $linea['cantidad']
                    ];
                }
                $column->widget->setValuesFromArray($customValues);
            }
        };
    }
}
