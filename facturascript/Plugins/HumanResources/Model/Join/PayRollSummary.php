<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Model\Join;

use FacturaScripts\Core\App\AppSettings;
use FacturaScripts\Dinamic\Model\Base\JoinModel;
use FacturaScripts\Dinamic\Model\EmployeePayRoll;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\SalaryBase;

/**
 * Auxiliary model to load a resume of payroll for employee and channel
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class PayRollSummary extends JoinModel
{

    /**
     * Constructor and class initializer.
     *
     * @param array $data
     */
    public function __construct($data = array())
    {
        parent::__construct($data);
        $this->setMasterModel(new EmployeePayRoll());
    }

    /**
     * List of fields or columns to select clausule
     */
    protected function getFields(): array
    {
        return [
            'id' => 'rrhh_employeespayroll.id',
            'idpayroll' => 'rrhh_employeespayroll.idpayroll',
            'idemployee' => 'rrhh_employeespayroll.idemployee',
            'holiday' => 'CASE WHEN COALESCE(rrhh_employeespayrollsalary.channel, 0) = 0 THEN rrhh_employeespayroll.holiday ELSE NULL END',
            'hours' => 'CASE WHEN COALESCE(rrhh_employeespayrollsalary.channel, 0) = 0 THEN rrhh_employeespayroll.hours ELSE NULL END',
            'worked' => 'CASE WHEN COALESCE(rrhh_employeespayrollsalary.channel, 0) = 0 THEN rrhh_employeespayroll.worked ELSE NULL END',
            'difference' => 'CASE WHEN COALESCE(rrhh_employeespayrollsalary.channel, 0) = 0 THEN rrhh_employeespayroll.difference ELSE NULL END',
            'overtime' => 'CASE WHEN COALESCE(rrhh_employeespayrollsalary.channel, 0) = rrhh_employeessalary.channel THEN rrhh_employeespayroll.difference * rrhh_employeessalary.amount ELSE NULL END',
            'amount' => 'rrhh_employeespayrollsalary.amount',
            'channel' => 'rrhh_employeespayrollsalary.channel',
            'idemployeepayroll' => 'rrhh_employeespayrollsalary.idemployeepayroll',
            'employee' => 'rrhh_employees.nombre'
        ];
    }

    /**
     * List of tables related to from clausule
     */
    protected function getSQLFrom(): string
    {
        return 'rrhh_employeespayroll'
            . ' LEFT JOIN rrhh_employees ON rrhh_employees.id = rrhh_employeespayroll.idemployee'
            . ' LEFT JOIN rrhh_employeespayrollsalary ON rrhh_employeespayrollsalary.idemployeepayroll = rrhh_employeespayroll.id'
            .       ' AND rrhh_employeespayrollsalary.calculation = ' . SalaryBase::CALCULATION_BALANCE
            . ' LEFT JOIN rrhh_employeessalary ON rrhh_employeessalary.idemployee = rrhh_employeespayroll.idemployee'
            .       ' AND rrhh_employeessalary.idsalaryconcept = ' . AppSettings::get('rrhh', 'extra-hours')
            .       ' AND rrhh_employeessalary.channel = rrhh_employeespayrollsalary.channel';
    }

    /**
     * List of tables required for the execution of the view.
     */
    protected function getTables(): array
    {
        return [
            'rrhh_employeespayroll',
            'rrhh_employeespayrollsalary',
            'rrhh_employees',
            'rrhh_employeessalary'
        ];
    }
}
