<?php
/**
 * This file is part of Proyectos plugin for FacturaScripts
 * Copyright (C) 2020-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\ImpFactCompras;

use FacturaScripts\Core\Base\AjaxForms\PurchasesHeaderHTML;
use FacturaScripts\Core\Base\AjaxForms\SalesHeaderHTML;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\InitClass;
use FacturaScripts\Core\Model\Role;
use FacturaScripts\Core\Model\RoleAccess;
use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\AlbaranProveedor;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\PedidoCliente;
use FacturaScripts\Dinamic\Model\PedidoProveedor;
use FacturaScripts\Dinamic\Model\PresupuestoCliente;
use FacturaScripts\Dinamic\Model\PresupuestoProveedor;

/**
 * Description of Init
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class Init extends InitClass
{
    const ROLE_NAME = 'ImpFactCompras';

    public function init()
    {
    }

    public function update()
    {
		#die("OK");
        #$this->setupSettings();
        $this->createRoleForPlugin();
    }

    private function createRoleForPlugin()
    {
	    $dataBase = new DataBase();
        $dataBase->beginTransaction();

        // creates the role if not exists
        $role = new Role();
        if (false === $role->loadFromCode(self::ROLE_NAME)) {
            $role->codrole = $role->descripcion = self::ROLE_NAME;
            if (false === $role->save()) {
                // exit and rollback on fail
                $dataBase->rollback();
                return;
            }
        }

        // check the role permissions
        $controllerNames = ['ImpFactCompras'];
        foreach ($controllerNames as $controllerName) {
            $roleAccess = new RoleAccess();
            $where = [
                new DataBaseWhere('codrole', self::ROLE_NAME),
                new DataBaseWhere('pagename', $controllerName)
            ];
            if ($roleAccess->loadFromCode('', $where)) {
                // permission exists? the skip
                continue;
            }

            // creates the permission if not exists
            $roleAccess->allowdelete = true;
            $roleAccess->allowupdate = true;
            $roleAccess->codrole = self::ROLE_NAME;
            $roleAccess->pagename = $controllerName;
            $roleAccess->onlyownerdata = false;
            if (false === $roleAccess->save()) {
                // exit and rollback on fail
                $dataBase->rollback();
                return;
            }
        }

        // without problems = Commit
        $dataBase->commit();
    }

}
