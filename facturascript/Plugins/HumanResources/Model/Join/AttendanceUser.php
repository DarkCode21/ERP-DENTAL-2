<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Model\Join;

use FacturaScripts\Dinamic\Model\Attendance;
use FacturaScripts\Dinamic\Model\Base\JoinModel;

/**
 * Attendance of the user relation.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AttendanceUser extends JoinModel
{

    public $nick;       // Declare the attribute so that the own data filter works.

    /**
     * Constructor and class initializer.
     *
     * @param array $data
     */
    public function __construct($data = array()) {
        parent::__construct($data);
        $this->setMasterModel( new Attendance() );
    }

    /**
     * List of fields or columns to select clausule
     */
    protected function getFields(): array {
        return [
            'id' => 'attendances.id',
            'authorized' => 'attendances.authorized',
            'origin' => 'attendances.origin',
            'checkdate' => 'attendances.checkdate',
            'checktime' => 'attendances.checktime',
            'idemployee' => 'attendances.idemployee',
            'inputdelay' => 'attendances.inputdelay',
            'kind' => 'attendances.kind',
            'credentialid' => 'attendances.credentialid',
            'idabsenceconcept' => 'attendances.idabsenceconcept',
            'note' => 'attendances.note',
            'nick' => 'employees.nick',
        ];
    }

    /**
     * List of tables related to from clausule
     */
    protected function getSQLFrom(): string {
        return 'rrhh_attendances attendances'
            . ' INNER JOIN rrhh_employees employees ON employees.id = attendances.idemployee'
            . ' LEFT JOIN rrhh_absencesconcepts concepts ON concepts.id = attendances.idabsenceconcept';
    }

    /**
     * List of tables required for the execution of the view.
     */
    protected function getTables(): array {
        return [
            'rrhh_attendances',
            'rrhh_employees',
            'rrhh_absencesconcepts',
        ];
    }
}
