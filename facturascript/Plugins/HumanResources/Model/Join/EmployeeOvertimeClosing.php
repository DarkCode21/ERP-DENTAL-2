<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Model\Join;

use FacturaScripts\Dinamic\Model\Base\JoinModel;
use FacturaScripts\Plugins\HumanResources\Model\EmployeeOvertimeClosing as ParentModel;

/**
 * List of Employee over time closing.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EmployeeOvertimeClosing extends JoinModel
{

    /**
     * Constructor and class initializer.
     *
     * @param array $data
     */
    public function __construct($data = array())
    {
        parent::__construct($data);
        $this->setMasterModel(new ParentModel());
    }

    /**
     * List of fields or columns to select clausule
     */
    protected function getFields(): array
    {
        return [
            'id' => 'overtime.id',
            'idclosing' => 'overtime.idclosing',
            'idemployee' => 'overtime.idemployee',
            'compensation' => 'overtime.compensation',
            'overtime' => 'overtime.overtime',

            'employee' => 'employee.nombre',
        ];
    }

    /**
     * List of tables related to from clausule
     */
    protected function getSQLFrom(): string
    {
        return 'rrhh_employeesovertimeclosing overtime'
            . ' INNER JOIN rrhh_overtimeclosing closing ON closing.id = overtime.idclosing'
            . ' INNER JOIN rrhh_employees employee ON employee.id = overtime.idemployee';
    }

    /**
     * List of tables required for the execution of the view.
     */
    protected function getTables(): array
    {
        return [];
    }
}
