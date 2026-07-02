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

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestMesa;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestZona;

/**
 * Página de posicionamiento de mesas en el mapa del restaurante.
 * Permite arrastrar las mesas para colocarlas en su posición real.
 */
class MapaMesas extends Controller
{
    /** @var RestMesa[] */
    public $mesas = [];

    /** @var RestZona[] */
    public $zonas = [];

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu']       = 'RestauranteTPV';
        $data['title']      = 'Mapa de mesas';
        $data['icon']       = 'fa-solid fa-map-location-dot';
        $data['showonmenu'] = false;
        return $data;
    }

    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);

        // Acción AJAX: guardar posición de una mesa
        if ($this->request->request->get('action') === 'save-mesa-pos') {
            $this->actionSaveMesaPos();
            return;
        }

        // Cargar todas las mesas
        $mesaModel = new RestMesa();
        $this->mesas = $mesaModel->all([], ['nombre' => 'ASC']);

        // Cargar zonas
        $zonaModel = new RestZona();
        $this->zonas = $zonaModel->all([], ['nombre' => 'ASC']);

        $this->setTemplate('MapaMesas');
    }

    protected function actionSaveMesaPos(): void
    {
        $idmesa = (int)$this->request->request->get('idmesa', 0);
        $posX   = (int)$this->request->request->get('pos_x', 0);
        $posY   = (int)$this->request->request->get('pos_y', 0);
        $mesa   = new RestMesa();
        if ($idmesa <= 0 || false === $mesa->loadFromCode($idmesa)) {
            $this->response->setContent(json_encode(['ok' => false, 'error' => 'Mesa no encontrada']));
            $this->response->headers->set('Content-Type', 'application/json');
            return;
        }
        $mesa->pos_x = max(0, $posX);
        $mesa->pos_y = max(0, $posY);
        $mesa->save();
        $this->response->setContent(json_encode(['ok' => true]));
        $this->response->headers->set('Content-Type', 'application/json');
    }
}
