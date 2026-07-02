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
 * Listado de zonas del restaurante.
 */
class ListRestZona extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu']       = 'RestauranteTPV';
        $data['title']      = 'zones';
        $data['icon']       = 'fa-solid fa-map';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function createViews(): void
    {
        $this->addView('ListRestZona', 'RestZona', 'zones', 'fa-solid fa-map');
        $this->addSearchFields('ListRestZona', ['nombre', 'descripcion']);
        $this->addOrderBy('ListRestZona', ['nombre'], 'name');
    }
}
