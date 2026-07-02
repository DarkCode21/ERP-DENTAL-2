<?php
/**
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TPVneo\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditAgente
{
    public function createViews(): Closure
    {
        return function () {
            $this->createViewsTpv();
        };
    }

    protected function createViewsTpv(): Closure
    {
        return function (string $viewName = 'EditAgenteTpv') {
            $this->addEditListView($viewName, 'TpvAgente', 'pos-terminal', 'fas fa-cash-register');
        };
    }

    protected function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName == 'EditAgenteTpv') {
                $where = [new DataBaseWhere('codagente', $this->request->query->get('code'))];
                $view->loadData('', $where);
            }
        };
    }
}