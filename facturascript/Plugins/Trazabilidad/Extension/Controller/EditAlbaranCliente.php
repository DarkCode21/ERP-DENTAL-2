<?php
/**
 * Copyright (C) 2022-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Extension\Controller;

use Closure;
use FacturaScripts\Core\Lib\AssetManager;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\LineaAlbaranCliente;
use FacturaScripts\Dinamic\Model\ProductoLote;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditAlbaranCliente
{
    use TrazabilidadControllerTrait;

    public function createViews(): Closure
    {
        return function () {
            $viewName = 'EditAlbaranClienteTrazabilidad';
            $this->addEditListView($viewName, 'ProductoLoteMovimiento', 'traceability', 'fa-solid fa-fingerprint')
                ->setInLine(true);

            // el getModel() de SalesController devuelve el modelo incluso aunque no se haya ejecutado el loadData()
            if (false === $this->getModel()->editable) {
                $this->tab($viewName)
                    ->setSettings('btnNew', false)
                    ->setSettings('btnSave', false)
                    ->setSettings('btnUndo', false)
                    ->setSettings('btnDelete', false)
                    ->disableColumn('quantity', false, 'true');
            }

            AssetManager::addJs(FS_ROUTE . '/Dinamic/Assets/JS/RemoveDateTraza.js');
        };
    }

    public function execPreviousAction(): Closure
    {
        return function ($action) {
            switch ($action) {
                case 'selectTraza':
                    return $this->selectLotesAction();

                case 'getQtyNewLoteCli':
                    return $this->getQtyNewLoteCli();
            }
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName != 'EditAlbaranClienteTrazabilidad') {
                return;
            }

            $lineasWithTraza = $this->getLinesWithTrazabilidad();
            $this->disableTrazabilidad($viewName, $lineasWithTraza);
            $this->loadSelectTraza($viewName, $lineasWithTraza, 'product');

            $mvn = $this->getMainViewName();
            $where = [
                new DataBaseWhere('docid', $this->tab($mvn)->model->primaryColumnValue()),
                new DataBaseWhere('docmodel', $this->tab($mvn)->model->modelClassName())
            ];
            $orderBy = ['id' => 'DESC'];
            $view->loadData('', $where, $orderBy);
        };
    }

    protected function selectLotesAction(): Closure
    {
        return function () {
            if ($this->request->request->get('activetab') != 'EditAlbaranClienteTrazabilidad') {
                return true;
            }

            $mvn = $this->getMainViewName();
            $this->setTemplate(false);
            $results = [];

            $lineDoc = new LineaAlbaranCliente();
            $term = $this->request->request->get('term');
            if (false === $lineDoc->loadFromCode($term)) {
                $this->response->setContent(json_encode($results));
                return false;
            }

            $where = [
                new DataBaseWhere('referencia', $lineDoc->referencia),
                new DataBaseWhere('idproducto', $lineDoc->idproducto),
                new DataBaseWhere('codalmacen', $this->getViewModelValue($mvn, 'codalmacen')),
            ];
            foreach (ProductoLote::all($where, ['fecha' => 'ASC'], 0, 0) as $lote) {
                $results[] = [
                    'key' => $lote->numserie,
                    'value' => $lote->numserie . ' (' . (float)$lote->cantidad . ') ' . $lote->fecha
                ];
            }
            $this->response->setContent(json_encode($results));
            return false;
        };
    }
}
