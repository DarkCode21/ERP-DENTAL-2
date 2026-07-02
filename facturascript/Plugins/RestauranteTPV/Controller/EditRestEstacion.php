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

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestEstacionFamilia;

/**
 * Edición de una estación. Incluye subvista de familias asignadas.
 */
class EditRestEstacion extends EditController
{
    public function getModelClassName(): string
    {
        return 'RestEstacion';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu']       = 'RestauranteTPV';
        $data['title']      = 'station';
        $data['icon']       = 'fa-solid fa-fire-burner';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function createViews(): void
    {
        parent::createViews();
        $this->createViewFamilias();
    }

    protected function createViewFamilias(string $viewName = 'ListRestEstacionFamilia'): void
    {
        $this->addListView($viewName, 'RestEstacionFamilia', 'families', 'fa-solid fa-tags');
        $this->views[$viewName]->addOrderBy(['codfamilia'], 'family');
        $this->views[$viewName]->searchFields = ['codfamilia'];
    }

    protected function loadData($viewName, $view): void
    {
        $mvn = $this->getMainViewName();
        switch ($viewName) {
            case $mvn:
                parent::loadData($viewName, $view);
                break;

            case 'ListRestEstacionFamilia':
                $idestacion = $this->getViewModelValue($mvn, 'idestacion');
                if (!empty($idestacion)) {
                    $view->loadData('', [new DataBaseWhere('idestacion', $idestacion)]);
                }
                break;
        }
    }
}
