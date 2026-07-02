<?php
/**
 * Copyright (C) 2023 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\Traducciones\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditPais
{
    public function createViews(): Closure
    {
        return function() {
            $viewName = 'ListLanguage';
            $this->addListView($viewName, 'Language', 'languages', 'fa fa-language');
            $this->views[$viewName]->addSearchFields(['name', 'codicu']);
            $this->views[$viewName]->addOrderBy(['name'], 'name', 1);
            $this->views[$viewName]->addOrderBy(['codicu'], 'codicu', 1);

            // ocultar columnas
            $this->views[$viewName]->disableColumn('country', true);
        };
    }

    protected function loadData(): Closure
    {
        return function($viewName, $view) {
            if ($viewName === 'ListLanguage') {
                $where = [new DataBaseWhere('codpais', $this->request->get('code'))];
                $view->loadData('', $where);
            }
        };
    }
}