<?php
/**
 * This file is part of StockAvanzado plugin for FacturaScripts
 * Copyright (C) 2020-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\erp_interiberica\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\ListController;

/**
 * Description of ReportStock
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ListVersion extends ListController
{

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'versions';
        $data['icon'] = 'fas fa-recycle';
        return $data;
    }

    protected function createViews()
    {
        $this->createViewsDefault();
    }

    protected function createViewsDefault(string $viewName = 'ListVersion')
    {
        $this->addView($viewName, 'Version', 'versions', 'fas fa-recycle');
        $this->addOrderBy($viewName, ['fecha'], 'date', 2);
        $this->addSearchFields($viewName, ['id', 'nombresoftware']);

        // Filters
        $this->addFilterPeriod($viewName, 'fecha', 'date', 'fecha');
        #$warehouses = $this->codeModel->all('almacenes', 'codalmacen', 'nombre');
        #$this->addFilterSelect($viewName, 'codalmacen', 'warehouse', 'codalmacen', $warehouses);
        $this->addFilterAutocomplete($viewName, 'nick', 'user', 'nick', 'users', 'nick', 'nick');
    }

}
