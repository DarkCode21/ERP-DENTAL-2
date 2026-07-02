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

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;

/**
 * Controller unificado de ajustes del plugin RestauranteTPV.
 * Agrupa en pestañas: Zonas, Mesas, Estaciones, Modificadores y Comandas.
 */
class AjustesRestauranteTPV extends ListController
{
    /** @var string */
    public $vistaMesas = 'clasico';
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu']  = 'RestauranteTPV';
        $data['title'] = 'settings';
        $data['icon']  = 'fa-solid fa-gear';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);
        $this->vistaMesas = Tools::settings('restaurantetpv', 'vista_mesas', 'clasico') ?: 'clasico';
        $this->setTemplate('AjustesRestauranteTPV');
    }

    protected function createViews(): void
    {
        // Tab: Zonas
        $this->addView('ListRestZona', 'RestZona', 'zones', 'fa-solid fa-map');
        $this->addSearchFields('ListRestZona', ['nombre', 'descripcion']);
        $this->addOrderBy('ListRestZona', ['nombre'], 'name');

        // Tab: Mesas
        $this->addView('ListRestMesa', 'RestMesa', 'tables', 'fa-solid fa-chair');
        $this->addSearchFields('ListRestMesa', ['nombre']);
        $this->addOrderBy('ListRestMesa', ['nombre'], 'name');
        $this->addOrderBy('ListRestMesa', ['estado'], 'state');

        $zonas = $this->codeModel->all('rest_zonas', 'idzona', 'nombre');
        $this->addFilterSelect('ListRestMesa', 'idzona', 'zone', 'idzona', $zonas);

        $estadosMesa = [
            ['code' => '',          'description' => '------'],
            ['code' => 'libre',     'description' => 'libre'],
            ['code' => 'ocupada',   'description' => 'ocupada'],
            ['code' => 'reservada', 'description' => 'reservada'],
        ];
        $this->addFilterSelect('ListRestMesa', 'estado', 'state', 'estado', $estadosMesa);

        // Tab: Estaciones
        $this->addView('ListRestEstacion', 'RestEstacion', 'stations', 'fa-solid fa-fire-burner');
        $this->addSearchFields('ListRestEstacion', ['nombre', 'descripcion']);
        $this->addOrderBy('ListRestEstacion', ['nombre'], 'name');

        // Tab: Modificadores
        $this->addView('ListRestModificador', 'RestModificador', 'modifiers', 'fa-solid fa-sliders');
        $this->addSearchFields('ListRestModificador', ['nombre']);
        $this->addOrderBy('ListRestModificador', ['nombre'], 'name');
        $this->addOrderBy('ListRestModificador', ['precio'], 'price');

        // Tab: Asignaciones modificador → producto
        $this->addView('ListRestProdModificador', 'Join\RestProdModificadorProducto', 'modifier-assignments', 'fa-solid fa-link');
        $this->addSearchFields('ListRestProdModificador', ['rpm.referencia', 'p.descripcion', 'rm.nombre']);
        $this->addOrderBy('ListRestProdModificador', ['rpm.referencia'], 'reference');

        // Tab: Comandas
        $this->addView('ListRestComanda', 'RestComanda', 'orders', 'fa-solid fa-receipt');
        $this->addSearchFields('ListRestComanda', ['codcamarero', 'observaciones', 'tipo']);
        $this->addOrderBy('ListRestComanda', ['fecha', 'hora'], 'date', 2);
        $this->addOrderBy('ListRestComanda', ['idcomanda'], 'code');

        $this->addFilterPeriod('ListRestComanda', 'fecha', 'date', 'fecha');

        $usuarios = $this->codeModel->all('users', 'nick', 'nick');
        $this->addFilterSelect('ListRestComanda', 'codcamarero', 'waiter', 'codcamarero', $usuarios);

        $mesas = $this->codeModel->all('rest_mesas', 'idmesa', 'nombre');
        $this->addFilterSelect('ListRestComanda', 'idmesa', 'table', 'idmesa', $mesas);

        $tipos = [
            ['code' => '',            'description' => '------'],
            ['code' => 'in-table',    'description' => 'dine-in'],
            ['code' => 'take-away',   'description' => 'take-away'],
            ['code' => 'delivery',    'description' => 'delivery'],
        ];
        $this->addFilterSelect('ListRestComanda', 'tipo', 'document-type', 'tipo', $tipos);

        $estadosCom = [
            ['code' => '',          'description' => '------'],
            ['code' => 'abierta',   'description' => 'abierta'],
            ['code' => 'cobrada',   'description' => 'cobrada'],
            ['code' => 'cancelada', 'description' => 'cancelada'],
        ];
        $this->addFilterSelect('ListRestComanda', 'estado', 'state', 'estado', $estadosCom);
    }
}
