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
 * Gestión de modificadores/agregados del TPV y su asignación a productos.
 * Tab 1: Catálogo de modificadores (nombre + precio).
 * Tab 2: Asignación modificador → referencia de producto.
 */
class ListRestModificador extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu']       = 'RestauranteTPV';
        $data['title']      = 'modifiers';
        $data['icon']       = 'fa-solid fa-sliders';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function createViews(): void
    {
        // Tab 1: catálogo de modificadores
        $this->addView('ListRestModificador', 'RestModificador', 'modifiers', 'fa-solid fa-sliders');
        $this->addSearchFields('ListRestModificador', ['nombre']);
        $this->addOrderBy('ListRestModificador', ['nombre'], 'name');
        $this->addOrderBy('ListRestModificador', ['precio'], 'price');

        // Tab 2: asignaciones producto → modificador (Join con nombre de producto)
        $this->addView('ListRestProdModificador', 'Join\RestProdModificadorProducto', 'modifier-assignments', 'fa-solid fa-link');
        $this->addSearchFields('ListRestProdModificador', ['rpm.referencia', 'p.descripcion', 'rm.nombre']);
        $this->addOrderBy('ListRestProdModificador', ['rpm.referencia'], 'reference');
    }
}
