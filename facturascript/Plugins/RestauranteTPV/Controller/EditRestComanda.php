<?php
/**
 * This file is part of RestauranteTPV plugin for FacturaScripts
 * Copyright (C) 2026 Interibérica Informática
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

namespace FacturaScripts\Plugins\RestauranteTPV\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * Formulario de detalle de una comanda.
 * Muestra la cabecera + pestaña de líneas editables.
 */
class EditRestComanda extends EditController
{
    public function getModelClassName(): string
    {
        return 'RestComanda';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu']       = 'RestauranteTPV';
        $data['title']      = 'order';
        $data['icon']       = 'fa-solid fa-receipt';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function createViews(): void
    {
        parent::createViews();
        $this->setTabsPosition('top');
        $this->createViewsLineas();
    }

    /**
     * Pestaña con las líneas de la comanda (productos pedidos).
     */
    protected function createViewsLineas(string $viewName = 'EditRestComandaLinea'): void
    {
        $this->addEditListView($viewName, 'RestComandaLinea', 'lines', 'fa-solid fa-list')
            ->disableColumn('comanda');
    }

    /**
     * Carga los datos de cada vista. Para la pestaña de líneas filtra por idcomanda.
     */
    protected function loadData($viewName, $view): void
    {
        switch ($viewName) {
            case 'EditRestComandaLinea':
                $idcomanda = $this->getViewModelValue($this->getMainViewName(), 'idcomanda');
                $where = [new DataBaseWhere('idcomanda', $idcomanda)];
                $view->loadData('', $where, ['idlinea' => 'ASC']);
                // Valor por defecto para nuevas líneas
                if (false === $view->model->exists()) {
                    /** @var \FacturaScripts\Plugins\RestauranteTPV\Model\RestComandaLinea $linea */
                    $linea = $view->model;
                    $linea->idcomanda = $idcomanda;
                }
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}
