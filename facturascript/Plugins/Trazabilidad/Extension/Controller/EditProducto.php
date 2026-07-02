<?php
/**
 * Copyright (C) 2022-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Extension\Controller;

use Closure;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Lib\LoteRebuild;
use FacturaScripts\Dinamic\Model\ProductoLote;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditProducto
{
    public function createViews(): Closure
    {
        return function () {
            // añadimos la pestaña de lotes / números de serie
            $viewName = 'ListProductoLote';
            $this->addListView($viewName, 'ProductoLote', 'batch-serial-numbers', 'fa-solid fa-fingerprint')
                ->addSearchFields(['referencia', 'numserie'])
                ->addOrderBy(['fecha'], 'date', 1)
                ->addOrderBy(['cantidad'], 'quantity')
                ->setSettings('modalInsert', 'add-lote');

            if ($this->user->admin) {
                $this->addButton($viewName, [
                    'action' => 'rebuild-lotes',
                    'color' => 'warning',
                    'confirm' => true,
                    'icon' => 'fa-solid fa-repeat',
                    'label' => 'rebuild-lotes'
                ]);
            }
        };
    }

    public function execPreviousAction(): Closure
    {
        return function ($action) {
            switch ($action) {
                case 'rebuild-lotes':
                    return $this->rebuildLotes();

                case 'add-lote':
                    return $this->addLote();
            }
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName != 'ListProductoLote') {
                return;
            }

            // si el producto no tiene trazabilidad, desactivamos la pestaña
            $mvn = $this->getMainViewName();
            if (false == $this->tab($mvn)->model->trazabilidad) {
                $this->setSettings($viewName, 'active', false);
                return;
            }

            $where = [new DataBaseWhere('idproducto', $this->tab($mvn)->model->idproducto)];
            $view->loadData('', $where);

            $this->loadCustomReferenceWidget($viewName);
            $this->loadLoteReferenceWidget($viewName);
        };
    }

    protected function addLote(): Closure
    {
        return function () {
            // validamos el token
            if (false === $this->validateFormToken()) {
                return true;
            }

            // añadimos el lote
            $lote = new ProductoLote();
            $lote->idproducto = $this->request->query->get('code');
            $lote->referencia = $this->request->request->get('lote-referencia');
            $lote->numserie = $this->request->request->get('lote-numserie');
            $lote->codalmacen = $this->request->request->get('lote-codalmacen');
            $lote->fecha = $this->request->request->get('lote-fecha');
            $lote->fecha_caducidad = $this->request->request->get('lote-fecha_caducidad') ?: null;
            if (false === $lote->save()) {
                Tools::log()->warning('record-save-error');
                return true;
            }

            Tools::log()->notice('record-saved-correctly');
            return true;
        };
    }

    protected function loadLoteReferenceWidget(): Closure
    {
        return function (string $viewName) {
            $references = [];
            $idproducto = $this->getViewModelValue('EditProducto', 'idproducto');
            $where = [new DataBaseWhere('idproducto', $idproducto)];
            foreach ($this->codeModel->all('variantes', 'referencia', 'referencia', false, $where) as $code) {
                $references[] = ['value' => $code->code, 'title' => $code->description];
            }

            $column = $this->tab($viewName)->columnModalForName('variant');
            if ($column && $column->widget->getType() === 'select') {
                $column->widget->setValuesFromArray($references, false);
            }
        };
    }

    protected function rebuildLotes(): Closure
    {
        return function () {
            if (false === $this->user->admin) {
                Tools::log()->warning('not-allowed-modify');
                return true;
            } elseif (false === $this->validateFormToken()) {
                return true;
            }

            $product = $this->getModel();
            if (false === $product->loadFromCode($this->request->get('code'))) {
                return true;
            }

            LoteRebuild::run($product);
            return true;
        };
    }
}
