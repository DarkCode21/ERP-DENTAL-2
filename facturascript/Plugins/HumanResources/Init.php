<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources;

use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Dinamic\Model\AttachedFileRelation;
use FacturaScripts\Dinamic\Model\EmployeeDocument;
use FacturaScripts\Dinamic\Model\Page;
use FacturaScripts\Dinamic\Model\Role;
use FacturaScripts\Dinamic\Model\RoleAccess;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\CreateModels;

/**
 * Description of Init
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class Init extends InitClass
{

    private const ROLE_RRHH = 'rrhh';
    private const DOCTYPE_OTHERS_ID = 5;

    public function init(): void
    {
        $this->loadExtension(new Extension\Controller\ListUser());
    }

    public function uninstall(): void
    {
    }

    /**
     * Set up plugin when install or update.
     */
    public function update(): void
    {
        CreateModels::checkModels();
        CreateModels::checkAuditFields();
        $this->createAppSettings();
        if ($this->createAdminRol()) {
            $this->assignPagesToRol();
        }

        $this->assignEmployeeDoc();
    }

    /**
     * Move attached files from the employee table
     * to new table employee documents.
     */
    private function assignEmployeeDoc()
    {
        $where = [ new DataBaseWhere('model', 'Employee') ];
        $fileRelationModel = new AttachedFileRelation();
        foreach ($fileRelationModel->all($where, [], 0, 0) as $fileRelation) {
            $employeeDoc = new EmployeeDocument();
            $employeeDoc->idemployee = $fileRelation->modelid;
            $employeeDoc->note = $fileRelation->observations;
            $employeeDoc->iddoctype = self::DOCTYPE_OTHERS_ID;
            if ($employeeDoc->save()) {
                $fileRelation->model = 'EmployeeDocument';
                $fileRelation->modelid = $employeeDoc->id;
                $fileRelation->modelcode = $employeeDoc->id;
                $fileRelation->save();
            }
        }
    }


    /**
     * Assign access to controllers.
     */
    private function assignPagesToRol()
    {
        $roleAccess = new RoleAccess();
        $page = new Page();
        $where1 = [ new DataBaseWhere('menu', self::ROLE_RRHH) ];
        foreach ($page->all($where1) as $item) {
            $where2 = [
                new DataBaseWhere('codrole', self::ROLE_RRHH),
                new DataBaseWhere('pagename', $item->name),
            ];
            if ($roleAccess->loadFromCode('', $where2)) {
                continue;
            }

            $roleAccess->codrole = self::ROLE_RRHH;
            $roleAccess->pagename = $item->name;
            $roleAccess->allowdelete = true;
            $roleAccess->allowupdate = true;
            $roleAccess->onlyownerdata = false;
            $roleAccess->save();
        }
    }

    /**
     * Create a role for access to controllers of the plugin.
     *
     * @return bool
     */
    private function createAdminRol(): bool
    {
        $role = new Role();
        if (false === $role->loadFromCode(self::ROLE_RRHH)) {
            $role->codrole = self::ROLE_RRHH;
            $role->descripcion = Tools::lang()->trans('rrhh-role-description');
            return $role->save();
        }
        return false;
    }

    /**
     * Create initial app settings for plugin.
     */
    private function createAppSettings()
    {
        $lang = \strtoupper(\substr(\FS_LANG, 0, 2));
        $rrhh = [
            'journal' => '1',
            'extra-hours' => ($lang === 'EN') ? '3' : '5',
            'biodevice' => '0',
        ];
        foreach ($rrhh as $key => $value) {
            Tools::settings('rrhh', $key, $value);
        }
        Tools::settingsSave();
    }
}
