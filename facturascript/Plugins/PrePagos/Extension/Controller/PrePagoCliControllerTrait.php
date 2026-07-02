<?php
/**
 * Copyright (C) 2022-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PrePagos\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait PrePagoCliControllerTrait
{
    public function createViews(): Closure
    {
        return function () {
            // añadimos la pestaña de pagos
            $viewName = 'EditPrePagoCli';
            $this->addEditListView($viewName, 'PrePagoCli', 'payments', 'fa-solid fa-coins')
                ->disableColumn('customer');

            // si el documento ya no es editable, deshabilitamos los botones y columnas
            if (false === $this->getModel()->editable) {
                $this->tab($viewName)
                    ->setSettings('btnNew', false)
                    ->setSettings('btnSave', false)
                    ->setSettings('btnDelete', false)
                    ->setSettings('btnUndo', false)
                    ->disableColumn('amount', false, 'true')
                    ->disableColumn('creation-date', false, 'true')
                    ->disableColumn('notes', false, 'true')
                    ->disableColumn('method-payment', false, 'true')
                    ->disableColumn('payment-date', false, 'true');
            }
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName != 'EditPrePagoCli') {
                return;
            }

            $model = $this->getModel();
            $where = [
                new DataBaseWhere('modelid', $model->primaryColumnValue()),
                new DataBaseWhere('modelname', $model->modelClassName())
            ];
            $orderBy = ['creationdate' => 'DESC', 'id' => 'DESC'];
            $view->loadData('', $where, $orderBy);

            // establecemos los valores por defecto
            $view->model->modelid = $model->primaryColumnValue();
            $view->model->modelname = $model->modelClassName();
            $view->model->codcliente = $model->codcliente;

            // si el documento ya no es editable y no hay pagos, desactivamos la pestaña
            if (false === $model->editable && $view->count === 0) {
                $this->setSettings($viewName, 'active', false);
            }
        };
    }
}
