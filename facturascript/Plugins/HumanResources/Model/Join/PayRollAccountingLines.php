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

/**
 * Auxiliary model for the payroll data for accounting
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class PayRollAccountingLines extends JoinModel
{

    /**
     * List of fields or columns to select clausule
     */
    protected function getFields(): array
    {
        return [
            'idpayroll' => 'rrhh_payroll.id',
            'idcompany' => 'rrhh_payroll.idcompany',
            'creationdate' => 'rrhh_payroll.creationdate',
            'idemployee' => 'rrhh_employeespayroll.idemployee',
            'idemployeepayrollsalary' => 'rrhh_employeespayrollsalary.id',
            'channel' => 'rrhh_employeespayrollsalary.channel',
            'column_position' => 'rrhh_employeespayrollsalary.position',
            'total' => '(rrhh_employeespayrollsalary.quantity * rrhh_employeespayrollsalary.amount)',
            'codsubaccount' => 'rrhh_employeespayrollsalary.codsubaccount',
            'employee' => 'rrhh_employees.nombre'
        ];
    }

    /**
     * List of tables related to from clausule
     */
    protected function getSQLFrom(): string
    {
        return 'rrhh_payroll'
            . ' LEFT JOIN rrhh_employeespayroll ON rrhh_employeespayroll.idpayroll = rrhh_payroll.id'
            . ' LEFT JOIN rrhh_employeespayrollsalary ON rrhh_employeespayrollsalary.idemployeepayroll = rrhh_employeespayroll.id'
            . ' LEFT JOIN rrhh_employees ON rrhh_employees.id = rrhh_employeespayroll.idemployee';
    }

    /**
     * List of tables required for the execution of the view.
     */
    protected function getTables(): array
    {
        return [
            'rrhh_payroll',
            'rrhh_employeespayroll',
            'rrhh_employeespayrollsalary',
            'rrhh_employees'
        ];
    }
}
