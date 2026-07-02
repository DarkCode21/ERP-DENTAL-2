<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Extension\Controller;

use Closure;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditEstadoDocumento
{
    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            $mvn = $this->getMainViewName();
            if ($mvn !== $viewName) {
                return;
            }

            if (empty($view->model->primaryColumnValue()) || $view->model->tipodoc !== 'FacturaCliente') {
                $this->tab($viewName)->disableColumn('send-verifactu', true);
            }
        };
    }
}
