<?php
/**
 * This file is part of RestauranteTPV plugin for FacturaScripts
 * Copyright (C) 2026 Interibérica Informática
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 */

namespace FacturaScripts\Plugins\RestauranteTPV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

/**
 * Listado de comandas.
 */
class ListRestComanda extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu']       = 'RestauranteTPV';
        $data['title']      = 'rest-comandas';
        $data['icon']       = 'fa-solid fa-receipt';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function createViews(): void
    {
        $this->addView('ListRestComanda', 'RestComanda', 'orders', 'fa-solid fa-receipt');
        $this->addSearchFields('ListRestComanda', ['codcamarero', 'observaciones', 'tipo']);
        $this->addOrderBy('ListRestComanda', ['fecha', 'hora'], 'date', 2);
        $this->addOrderBy('ListRestComanda', ['idcomanda'], 'code');

        // Filtro por período (fecha)
        $this->addFilterPeriod('ListRestComanda', 'fecha', 'date', 'fecha');

        // Filtro por camarero
        $usuarios = $this->codeModel->all('users', 'nick', 'nick');
        $this->addFilterSelect('ListRestComanda', 'codcamarero', 'waiter', 'codcamarero', $usuarios);

        // Filtro por mesa
        $mesas = $this->codeModel->all('rest_mesas', 'idmesa', 'nombre');
        $this->addFilterSelect('ListRestComanda', 'idmesa', 'table', 'idmesa', $mesas);

        // Filtro por tipo
        $tipos = [
            ['code' => '',            'description' => '------'],
            ['code' => 'in-table',  'description' => 'dine-in'],
            ['code' => 'take-away', 'description' => 'take-away'],
            ['code' => 'delivery',    'description' => 'delivery'],
        ];
        $this->addFilterSelect('ListRestComanda', 'tipo', 'document-type', 'tipo', $tipos);

        // Filtro por estado
        $estados = [
            ['code' => '',          'description' => '------'],
            ['code' => 'abierta',   'description' => 'abierta'],
            ['code' => 'cobrada',   'description' => 'cobrada'],
            ['code' => 'cancelada', 'description' => 'cancelada'],
        ];
        $this->addFilterSelect('ListRestComanda', 'estado', 'state', 'estado', $estados);
    }
}
