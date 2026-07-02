<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Extension\Controller;

use Closure;
use FacturaScripts\Core\DataSrc\Almacenes;
use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Dinamic\Model\CodeModel;
use FacturaScripts\Dinamic\Model\Department;
use FacturaScripts\Dinamic\Model\User;

/**
 * Controller to list the users.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * @property CodeModel codeModel
 * @property User $user
 * @method addView(string $viewName, string $model, string $title, string $icon)
 */
class ListUser
{

    /**
     * Load views
     */
    public function createViews()
    {
        return function() {
            $this->modifyViewsUsers();
        };
    }

    protected function modifyViewsUsers(): Closure
    {
        return function () {
            $view = $this->addView('ListUser', 'Join\EmployeeUser', 'users', 'fa-solid fa-users')
                ->setSettings('btnPrint', false)
                // SEARCH AND ORDER BY
                ->addSearchFields(['users.nick', 'users.email', 'emp.nombre', 'dep.name'])
                ->addOrderBy(['users.nick'], 'nick', 1)
                ->addOrderBy(['users.email'], 'email')
                ->addOrderBy(['emp.nombre'], 'nombre')
                ->addOrderBy(['dep.name'], 'department')
                ->addOrderBy(['creationdate'], 'creation-date')
                ->addOrderBy(['lastactivity'], 'last-activity');

            if ($this->user->admin) {
                $view->addOrderBy(['users.level'], 'level');
            }

            // FILTERS
            if ($this->user->admin) {
                $view->addFilterSelect('level', 'level', 'level',
                    $this->codeModel->all('users', 'level', 'level')
                );
            }

            $view->addFilterSelect('langcode', 'language', 'langcode',
                $this->codeModel->all('users', 'langcode', 'langcode')
            );

            $companies = Empresas::codeModel();
            if (count($companies) > 2) {
                $view->addFilterSelect('idempresa', 'company', 'idempresa', $companies);
            }

            $warehouses = Almacenes::codeModel();
            if (count($warehouses) > 2) {
                $view->addFilterSelect('codalmacen', 'warehouse', 'codalmacen', $warehouses);
            }

            $department = $this->codeModel->all(Department::tableName(), Department::primaryColumn(), 'name');
            $view->addFilterSelect('iddepartment', 'department', 'emp.iddepartment', $department);
        };
    }
}
