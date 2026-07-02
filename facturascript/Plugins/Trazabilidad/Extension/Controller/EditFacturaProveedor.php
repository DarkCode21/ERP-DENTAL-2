<?php
/**
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditFacturaProveedor
{
    use TrazabilidadControllerTrait;

    public function createViews(): Closure
    {
        return function () {
            $viewName = 'EditFacturaProveedorTrazabilidad';
            $this->addEditListView($viewName, 'ProductoLoteMovimiento', 'traceability', 'fa-solid fa-fingerprint')
                ->setInLine(true);

            // el getModel() de PurchaseController devuelve el modelo incluso aunque no se haya ejecutado el loadData()
            if (false === $this->getModel()->editable) {
                $this->tab($viewName)
                    ->setSettings('btnNew', false)
                    ->setSettings('btnSave', false)
                    ->setSettings('btnUndo', false)
                    ->setSettings('btnDelete', false)
                    ->disableColumn('quantity', false, 'true');
            }
        };
    }

    public function execPreviousAction(): Closure
    {
        return function ($action) {
            if ($action === 'getQtyNewLoteProv') {
                return $this->getQtyNewLoteProv();
            }
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName != 'EditFacturaProveedorTrazabilidad') {
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
}
