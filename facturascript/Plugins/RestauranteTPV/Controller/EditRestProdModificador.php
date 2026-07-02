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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Dinamic\Model\Variante;

class EditRestProdModificador extends EditController
{
    public function getModelClassName(): string
    {
        return 'RestProdModificador';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu']       = 'RestauranteTPV';
        $data['title']      = 'modifier-assignments';
        $data['icon']       = 'fa-solid fa-link';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);

        if ($viewName === $this->getMainViewName() && $view->model->exists()) {
            $variante = new Variante();
            if ($variante->loadFromCode('', [new DataBaseWhere('referencia', $view->model->referencia)])) {
                $column = $view->columnForField('referencia');
                if ($column && $column->widget) {
                    $column->widget->setSelected($variante->description());
                }
            }
        }
    }
}
