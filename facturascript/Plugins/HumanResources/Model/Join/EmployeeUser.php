<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Model\Join;

use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Dinamic\Model\Base\JoinModel;

/**
 * List of Users with Employee data
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EmployeeUser extends JoinModel
{
    /**
     * Constructor and class initializer.
     *
     * @param array $data
     */
    public function __construct(array $data = array())
    {
        parent::__construct($data);
        $this->setMasterModel(new User());
    }

    /**
     * List of fields or columns to select clausule
     */
    protected function getFields(): array
    {
        return [
            'admin' => 'users.admin',
            'codalmacen' => 'users.codalmacen',
            'creationdate' => 'users.creationdate',
            'email' => 'users.email',
            'enabled' => 'users.enabled',
            'idempresa' => 'users.idempresa',
            'langcode' => 'users.langcode',
            'lastip' => 'users.lastip',
            'lastactivity' => 'users.lastactivity',
            'level' => 'users.level',
            'nick' => 'users.nick',

            'nombre' => 'emp.nombre',
            'iddepartamento' => 'emp.iddepartment',
            'departamento' => 'dep.name',
        ];
    }

    /**
     * List of tables related to from clausule
     */
    protected function getSQLFrom(): string
    {
        return 'users'
            . ' LEFT JOIN rrhh_employees emp on emp.nick = users.nick'
            . ' LEFT JOIN rrhh_departments dep on dep.id = emp.iddepartment';
    }

    /**
     * List of tables required for the execution of the view.
     */
    protected function getTables(): array
    {
        return [];
    }
}