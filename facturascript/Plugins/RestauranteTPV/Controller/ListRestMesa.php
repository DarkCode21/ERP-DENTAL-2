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
 * Listado de mesas del restaurante.
 */
class ListRestMesa extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu']       = 'RestauranteTPV';
        $data['title']      = 'tables';
        $data['icon']       = 'fa-solid fa-chair';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function createViews(): void
    {
        $this->addView('ListRestMesa', 'RestMesa', 'tables', 'fa-solid fa-chair');
        $this->addSearchFields('ListRestMesa', ['nombre']);
        $this->addOrderBy('ListRestMesa', ['nombre'], 'name');
        $this->addOrderBy('ListRestMesa', ['estado'], 'state');

        // Filtro por zona
        $zonas = $this->codeModel->all('rest_zonas', 'idzona', 'nombre');
        $this->addFilterSelect('ListRestMesa', 'idzona', 'zone', 'idzona', $zonas);

        // Filtro por estado
        $estados = [
            ['code' => '',          'description' => '------'],
            ['code' => 'libre',     'description' => 'libre'],
            ['code' => 'ocupada',   'description' => 'ocupada'],
            ['code' => 'reservada', 'description' => 'reservada'],
        ];
        $this->addFilterSelect('ListRestMesa', 'estado', 'state', 'estado', $estados);
    }
}
