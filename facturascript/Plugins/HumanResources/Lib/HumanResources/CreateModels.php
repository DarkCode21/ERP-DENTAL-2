<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\HumanResources;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Plugins\HumanResources\Model\Attendance;
use FacturaScripts\Plugins\HumanResources\Model\AttendanceAudit;
use FacturaScripts\Plugins\HumanResources\Model\DisciplinaryOffense;
use FacturaScripts\Plugins\HumanResources\Model\DocumentType;
use FacturaScripts\Plugins\HumanResources\Model\Employee;
use FacturaScripts\Plugins\HumanResources\Model\EmployeeOvertimeClosing;
use FacturaScripts\Plugins\HumanResources\Model\EmployeePayRollSalary;
use FacturaScripts\Plugins\HumanResources\Model\EmployeeWorkPeriod;
use FacturaScripts\Plugins\HumanResources\Model\PayRollAccounting;
use FacturaScripts\Plugins\HumanResources\Model\Sanction;

/**
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class CreateModels
{

    public static function checkModels()
    {
        // Base Data
        new DisciplinaryOffense();
        new DocumentType();
        new Sanction();

        // Attendance:
        //   -> AbsenceConcept
        //   -> Employee
        //     -> Departament
        //     -> CourseTraining
        new Attendance();
        new AttendanceAudit();

        // EmployeeWorkPeriod:
        //   -> EmployeeWorkShift
        new EmployeeWorkPeriod();

        // EmployeePayRollSalary:
        //   -> EmployeeSalary
        //     -> SalaryConcept
        //   -> EmployeePayRoll
        //     -> PayRoll
        new EmployeePayRollSalary();
        new PayRollAccounting();

        // EmployeeOvertimeClosing:
        //   -> OvertimeClosing
        new EmployeeOvertimeClosing();
    }

    public static function checkAuditFields(): void
    {
        $model = new Attendance();  // Force to create the table, to add new fields
        $attendances =  $model->tableName();
        $employees = Employee::tableName();
        $sql = 'UPDATE ' . $attendances
            . ' LEFT JOIN ' . $employees . ' ON ' . $employees . '.id = ' . $attendances . '.idemployee'
            . ' SET creation_date = CONCAT(checkdate, \' \', checktime)'
            . ' , last_update = CONCAT(checkdate, \' \', checktime)'
            . ' , last_nick = ' . $employees . '.nick'
            . ' WHERE creation_date IS NULL AND checkdate > \'1990-01-01\'';

        $database = new DataBase();
        $database->connect();
        $database->exec($sql);
    }
}
