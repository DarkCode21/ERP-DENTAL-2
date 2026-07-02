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
use FacturaScripts\Core\Base\Controller;

/**
 * Listado de estaciones (Cocina, Bar, etc.) con botón para abrir el panel de cada una.
 */
class ListRestEstacion extends Controller
{
    /** @var array Estaciones con sus familias cargadas */
    public $estaciones = [];

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu']       = 'RestauranteTPV';
        $data['title']      = 'stations';
        $data['icon']       = 'fa-solid fa-fire-burner';
        $data['showonmenu'] = false;
        return $data;
    }

    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);
        $this->loadEstacionesConFamilias();
        $this->setTemplate('ListRestEstacion');
    }

    protected function loadEstacionesConFamilias(): void
    {
        $db = new DataBase();
        // GROUP_CONCAT funciona en MySQL/MariaDB; en PostgreSQL se usaría STRING_AGG
        $sql = 'SELECT e.idestacion, e.nombre, e.descripcion,'
            . ' GROUP_CONCAT(f.descripcion ORDER BY f.descripcion SEPARATOR \', \') AS familias'
            . ' FROM rest_estaciones e'
            . ' LEFT JOIN rest_estacion_familias ef ON ef.idestacion = e.idestacion'
            . ' LEFT JOIN familias f ON f.codfamilia = ef.codfamilia'
            . ' GROUP BY e.idestacion, e.nombre, e.descripcion'
            . ' ORDER BY e.nombre ASC';
        $this->estaciones = $db->select($sql);
    }
}
