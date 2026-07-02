<?php
/**
 * Copyright (C) 2020-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TarifasAvanzadas\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

/**
 * Description of EditTarifa
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditTarifa
{
    public function createViews(): Closure
    {
        return function () {
            $this->addEditListView('EditTarifaFamilia', 'TarifaFamilia', 'families', 'fas fa-sitemap');
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName === 'EditTarifaFamilia') {
                $code = $this->getViewModelValue($this->getMainViewName(), 'codtarifa');
                $where = [new DataBaseWhere('codtarifa', $code)];
                $view->loadData('', $where, ['id' => 'DESC']);
            }
        };
    }
}
