<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Model\Join;

use FacturaScripts\Dinamic\Model\EmployeeHoliday;
use FacturaScripts\Dinamic\Model\Base\JoinModel;

/**
 * Attendance of the user relation.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class HolidayUser extends JoinModel {

    /**
     * Constructor and class initializer.
     *
     * @param array $data
     */
    public function __construct($data = array()) {
        parent::__construct($data);
        $this->setMasterModel( new EmployeeHoliday() );
    }

    /**
     * List of fields or columns to select clausule
     */
    protected function getFields(): array {
        return [
            'id' => 'holidays.id',
            'origin' => 'holidays.origin',
            'checkdate' => 'holidays.checkdate',
            'checktime' => 'holidays.checktime',
            'idemployee' => 'holidays.idemployee',
            'kind' => 'holidays.kind',
            'credentialid' => 'holidays.credentialid',
            'idabsenceconcept' => 'holidays.idabsenceconcept',
            'nick' => 'employees.nick',
        ];
    }

    /**
     * List of tables related to from clausule
     */
    protected function getSQLFrom(): string {
        return 'rrhh_employeesholidays holidays'
            . ' INNER JOIN rrhh_employees employees ON employees.id = holidays.idemployee';
    }

    /**
     * List of tables required for the execution of the view.
     */
    protected function getTables(): array {
        return [
            'rrhh_employeesholidays',
            'rrhh_employees',
        ];
    }
}
