<?php
/**
 * Copyright (C) 2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\ConciliacionBancaria\Extension\Controller;

use Closure;
use FacturaScripts\Dinamic\Model\CSVfile;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditCSVfile
{
    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName !== 'EditCSVfile') {
                return;
            }

            // si la columna de cuenta no está vacía, la mostramos
            if (false === empty($view->model->codcuenta)) {
                $this->views[$viewName]->disableColumn('account', false);
            }
        };
    }

    public function afterImport(): Closure
    {
        return function (CSVfile $model, array $result) {
            // si es una importación de movimientos bancarios, redirigimos a la vista de movimientos
            if ($model->codcuenta) {
                $this->redirect('ConciliateBankMovements?codcuenta=' . $model->codcuenta);
            }
        };
    }
}