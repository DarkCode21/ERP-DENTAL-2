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

namespace FacturaScripts\Plugins\RestauranteTPV;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Role;
use FacturaScripts\Core\Model\RoleAccess;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestComanda;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestComandaLinea;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestCaja;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestCajaFactura;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestCajaMovimiento;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestEstacion;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestEstacionFamilia;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestMesa;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestModificador;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestProdModificador;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestZona;

final class Init extends InitClass
{
    // Códigos de los tres roles del plugin
    const ROLE_CAMARERO = 'rest_camarero';
    const ROLE_COCINERO = 'rest_cocinero';
    const ROLE_CAJERO   = 'rest_cajero';

    /**
     * Se ejecuta en cada carga del plugin.
     * Aquí se registrarán extensiones y mods en fases posteriores.
     */
    public function init(): void
    {
        $this->loadExtension(new Extension\Controller\EditSettings());
    }

    public function uninstall(): void
    {
    }

    /**
     * Se ejecuta al instalar o actualizar el plugin.
     * Crea/actualiza las tablas y los roles necesarios.
     */
    public function update(): void
    {
        // Fuerza la creación/actualización de las tablas en BD
        new RestZona();
        new RestMesa();
        new RestComanda();
        new RestComandaLinea();
        new RestCaja();
        new RestCajaMovimiento();
        new RestCajaFactura();
        new RestModificador();
        new RestProdModificador();
        new RestEstacion();
        new RestEstacionFamilia();

        // Migrar rest_estacion_familias: renombrar codcategoria → codfamilia si existe la columna antigua
        $db = new DataBase();
        $efCols = $db->getColumns('rest_estacion_familias');
        if (isset($efCols['codcategoria']) && !isset($efCols['codfamilia'])) {
            $db->exec('ALTER TABLE rest_estacion_familias RENAME COLUMN codcategoria TO codfamilia');
        }

        // Añadir columnas tpv_efectivo y tpv_cambio a facturascli si no existen
        $facCols = $db->getColumns('facturascli');
        if (!isset($facCols['tpv_efectivo'])) {
            $db->exec('ALTER TABLE facturascli ADD COLUMN tpv_efectivo double precision DEFAULT NULL');
        }
        if (!isset($facCols['tpv_cambio'])) {
            $db->exec('ALTER TABLE facturascli ADD COLUMN tpv_cambio double precision DEFAULT NULL');
        }

        // Crea los tres roles del plugin con sus permisos
        $this->createRole(self::ROLE_CAMARERO, 'Restaurante: Camarero', [
            'PanelCamarero',
            'AjustesRestauranteTPV',
            'ListRestZona',
            'ListRestMesa',
            'EditRestMesa',
            'ListRestComanda',
            'EditRestComanda',
            'ListRestModificador',
            'EditRestModificador',
            'EditRestProdModificador',
            'ListFamilia',
            'EditFamilia',
        ]);

        $this->createRole(self::ROLE_COCINERO, 'Restaurante: Cocinero', [
            'PanelCocina',
            'AjustesRestauranteTPV',
            'ListRestEstacion',
            'EditRestEstacion',
            'EditRestEstacionFamilia',
            'ListFamilia',
            'EditFamilia',
        ]);

        $this->createRole(self::ROLE_CAJERO, 'Restaurante: Cajero', [
            'PanelMesas',
            'PanelCamarero',
            'AjustesRestauranteTPV',
            'ListRestZona',
            'ListRestMesa',
            'EditRestMesa',
            'ListRestComanda',
            'EditRestComanda',
            'PanelCocina',
            'ListRestEstacion',
            'EditRestEstacion',
            'EditRestEstacionFamilia',
            'ListFamilia',
            'EditFamilia',
            'ListRestModificador',
            'EditRestModificador',
            'EditRestProdModificador',
        ]);
    }

    /**
     * Crea un rol con sus permisos si no existe ya.
     * Si el rol ya existe, añade los permisos que falten sin borrar los existentes.
     */
    private function createRole(string $code, string $description, array $pages): void
    {
        $db = new DataBase();
        $db->beginTransaction();

        // Crea el rol si no existe
        $role = new Role();
        if (false === $role->loadFromCode($code)) {
            $role->codrole    = $code;
            $role->descripcion = $description;
            if (false === $role->save()) {
                $db->rollback();
                return;
            }
        }

        // Añade permisos que falten
        foreach ($pages as $pageName) {
            $roleAccess = new RoleAccess();
            $where = [
                new DataBaseWhere('codrole', $code),
                new DataBaseWhere('pagename', $pageName),
            ];
            if ($roleAccess->loadFromCode('', $where)) {
                continue; // ya existe, nada que hacer
            }

            $roleAccess->allowdelete  = false;
            $roleAccess->allowupdate  = true;
            $roleAccess->codrole      = $code;
            $roleAccess->pagename     = $pageName;
            $roleAccess->onlyownerdata = false;
            if (false === $roleAccess->save()) {
                $db->rollback();
                return;
            }
        }

        $db->commit();
    }
}
