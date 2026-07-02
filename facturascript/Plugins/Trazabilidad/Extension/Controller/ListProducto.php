<?php
/**
 * Copyright (C) 2022-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Extension\Controller;

use FacturaScripts\Core\Tools;


use Closure;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ListProducto
{
    public function createViews(): Closure
    {
        return function () {
            // añadimos el filtro checkbox a la pestaña de productos
            $this->addFilterCheckbox('ListProducto', 'trazabilidad', 'traceability', 'trazabilidad');

            // añadimos la pestaña de lotes / números de serie
            $this->createViewsLotes();
        };
    }

    public function createViewsLotes(): Closure
    {
        return function ($viewName = 'ListProductoLote') {
            $this->addView($viewName, 'ProductoLote', 'batch-serial-numbers', 'fa-solid fa-fingerprint')
                ->addSearchFields(['referencia', 'numserie', 'codalmacen'])
                ->addOrderBy(['fecha'], 'date', 1)
                ->addOrderBy(['cantidad'], 'quantity')
                ->setSettings('btnNew', false);

            // filtros
            $warehouses = $this->codeModel->all('almacenes', 'codalmacen', 'nombre');
            $this->listview($viewName)
                ->addFilterSelect('codalmacen', 'warehouse', 'codalmacen', $warehouses);
        };
    }
}
