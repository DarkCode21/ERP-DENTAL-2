<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Model;

use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\AttendanceTools;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelExtended;

/**
 * List of attendances of employee
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AttendanceAudit extends ModelExtended
{
    public const AUDIT_ACTION_UPDATE = 1;
    public const AUDIT_ACTION_DELETE = 2;

    use ModelTrait;

    /**
     * Primary Key of the model
     *
     * @var int
     */
    public $id;

    /**
     * Link to the attendance record.
     *
     * @var int
     */
    public $idattendance;

    /**
     * Indicates the type of action performed on the attendance record.
     *
     * @var int
     */
    public $action;

    /**
     * JSON string with the old data of the attendance record.
     *
     * @var string
     */
    public $olddata;

    /** 
     * JSON string with the new data of the attendance record.
     *
     * @var string
     */
    public $newdata;

    /**
     * Reason for the action performed on the attendance record.
     *
     * @var string
     */
    public $reason;

    /**
     * Date and time when the action was performed.
     *
     * @var string
     */
    public $dateaction;

    /**
     * Nickname of the user who performed the action.
     *
     * @var string
     */
    public $nick;

    /**
     * Register a new delete action in the audit log.
     *
     * @param Attendance $attendance
     * @param ?string $reason
     * @return AttendanceAudit
     */
    public static function addDeleteAction(Attendance $attendance, ?string $reason): AttendanceAudit
    {
        $model = new self();
        $model->idattendance = $attendance->primaryColumnValue();
        $model->action = self::AUDIT_ACTION_DELETE;
        $model->olddata = json_encode($attendance->toArray());
        $model->reason = $reason;
        $model->save();
        return $model;
    }

    /**
     * Register a new update action in the audit log.
     *
     * @param Attendance $oldAttendance
     * @param Attendance $newAttendance
     * @return AttendanceAudit
     */
    public static function addUpdateAction(Attendance $oldAttendance, Attendance $newAttendance): AttendanceAudit
    {
        $model = new self();
        $model->idattendance = $oldAttendance->primaryColumnValue();
        $model->action = self::AUDIT_ACTION_UPDATE;
        $model->olddata = json_encode($oldAttendance->toArray());
        $model->newdata = json_encode($newAttendance->toArray());
        $model->reason = $newAttendance->reason;
        $model->save();
        return $model;
    }

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->action = self::AUDIT_ACTION_UPDATE;
        $this->dateaction = date(self::DATETIME_STYLE);
        $this->nick = Session::user()->nick;
    }

    /**
     * Returns the old and new data of the attendance record as arrays.
     * Params field is the name of the property that contains the JSON data.
     *   -Valid values are 'olddata' and 'newdata'.
     *
     * @param string $field
     * @return array
     */
    public function getData(string $field): array
    {
        $result = [];
        $values = json_decode($this->{$field}, true) ?? [];
        foreach ($values as $key => $value) {
            switch ($key) {
                case 'authorized':
                case 'creation_ip':
                case 'credentialid':
                case 'id':
                case 'idclosing':
                case 'location':
                    break;

                case 'checkdate':
                    $result[Tools::lang()->trans('date')] = date(self::DATE_STYLE, strtotime($value));
                    break;

                case 'checktime':
                    $result[Tools::lang()->trans('date')] .= ' ' . date(self::HOUR_STYLE, strtotime($value));
                    break;

                case 'creation_date':
                    $result[Tools::lang()->trans('created')] = date(self::DATETIME_STYLE, strtotime($value));
                    break;

                case 'idabsenceconcept':
                    if (empty($value)) {
                        $result[Tools::lang()->trans('absence-concept')] = '';
                    }
                    $concept = new AbsenceConcept();
                    $concept->loadFromCode($value);
                    $result[Tools::lang()->trans('absence-concept')] = $concept->name;
                    break;

                case 'idemployee':
                    $employee = new Employee();
                    $employee->loadFromCode($value);
                    $result[Tools::lang()->trans('employee')] = $employee->nombre;
                    break;

                case 'inputdelay':
                    $result[Tools::lang()->trans('delay')] = $value . ' ' . Tools::lang()->trans('minutes');
                    break;

                case 'kind':
                    $result[Tools::lang()->trans('type')] = AttendanceTools::kindText($value);
                    break;

                case 'last_nick':
                    $result[Tools::lang()->trans('nick')] = $value;
                    break;

                case 'last_update':
                    $result[Tools::lang()->trans('updated')] = date(self::DATETIME_STYLE, strtotime($value));
                    break;

                case 'origin':
                    $result[Tools::lang()->trans($key)] = AttendanceTools::originText($value);
                    break;

                default:
                    $result[Tools::lang()->trans($key)] = $value;
                    break;
            }
        }
        return $result;
    }

    /**
     * Returns an array with the comparative data between old and new attendance records.
     *
     * @return array
     */
    public function getComparativeData(): array
    {
        $oldData = $this->getData('olddata');
        $newData = $this->getData('newdata');

        if (empty($oldData) || empty($newData)) {
            return [];
        }

        $comparative = [];
        foreach ($newData as $key => $value) {
            if ($oldData[$key] != $value) {
                $comparative[$key] = [
                    'old' => $oldData[$key],
                    'new' => $value,
                ];
            }
        }
        return $comparative;
    }

    /**
     * Returns the name of the column that is the model's primary key.
     *
     * @return string
     */
    public static function primaryColumn(): string
    {
        return 'id';
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'rrhh_attendancesaudit';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        $this->reason = Tools::noHtml($this->reason);
        if (empty($this->reason)) {
            return false;
        }
        return parent::test();
    }

    /**
     * Returns the url where to see / modify the data.
     *
     * @param string $type
     * @param string $list
     * @return string
     */
    public function url(string $type = 'auto', string $list = 'ListAttendance?activetab=List'): string
    {
        return parent::url($type, $list);
    }
}
