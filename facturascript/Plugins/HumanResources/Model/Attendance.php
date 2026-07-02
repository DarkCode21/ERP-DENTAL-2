<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Model;

use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\AttendanceTools;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelExtended;
use ParseCsv\Csv;

/**
 * List of attendances of employee
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class Attendance extends ModelExtended
{

    use ModelTrait;

    /**
     * Indicates the origin of the movement
     */
    const ORIGIN_MANUAL = 1;
    const ORIGIN_JUSTIFIED = 2;
    const ORIGIN_EXTERNAL = 3;

    /**
     * Indicates the kind of movement
     */
    const KIND_INPUT = 1;
    const KIND_OUTPUT = 2;

    /**
     * indicates if the record is authorized.
     *
     * @var bool
     */
    public $authorized;

    /**
     * Additional internal identifier for the employee
     *
     * @var string
     */
    public $credentialid;

    /**
     * Date of attendance record
     *
     * @var string
     */
    public $checkdate;

    /**
     * Time of attendance record
     *
     * @var string
     */
    public $checktime;

    /**
     * Link to the table of absences concepts
     *
     * @var integer
     */
    public $idabsenceconcept;

    /**
     * Link to the table of overtime closing.
     *
     * @var integer
     */
    public $idclosing;

    /**
     * Link to the table of employees
     *
     * @var integer
     */
    public $idemployee;

    /**
     * Input Delay in minutes.
     *
     * @var int
     */
    public $inputdelay;

    /**
     * Indicates the kind of movement
     * (1: Input, 2: Output)
     *
     * @var integer
     */
    public $kind;

    /**
     * Geographical location of the record.
     * (latitude, longitude)
     *
     * @var string
     */
    public $location;

    /**
     * Additional description for a justified absence movement
     *
     * @var string
     */
    public $note;

    /**
     * Indicates the origin of the attendance record.
     * (1: manual, 2: justified, 3: auto/external)
     *
     * @var integer
     */
    public $origin;

    /**
     * 
     * @var string
     */
    public $reason;

    /**
     * Indicates if the new record should be adjusted to the nearest word period.
     *
     * @var bool
     */
    private bool $adjustToWordPeriod = false;

    /**
     * Class constructor.
     * Active audit control for this model.
     *
     * @throws Exception
     */
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        $this->setAuditControl(true);
    }

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->authorized = true;
        $this->checkdate = date('d-m-Y');
        $this->checktime = date('H:i:s');
        $this->inputdelay = 0;
        $this->origin = self::ORIGIN_MANUAL;
        $this->kind = self::KIND_INPUT;
    }

    /**
     * Return the number of delays for an employee in a period of time.
     *
     * @param int $idemployee
     * @param string $fromDate
     * @param string|null $toDate
     * @return int
     */
    public static function countDelays(int $idemployee, string $fromDate, ?string $toDate = null): int
    {
        $where = [
            new DataBaseWhere('idemployee', $idemployee),
            new DataBaseWhere('kind', self::KIND_INPUT),
            new DataBaseWhere('inputdelay', 0, '>'),
            new DataBaseWhere('checkdate', $fromDate, '>='),
        ];

        if (false === empty($toDate)) {
            $where[] = new DataBaseWhere('checkdate', $toDate, '<=');
        }

        $attendance = new Attendance();
        return $attendance->count($where);
    }

    /**
     * Remove the model data from the database.
     *
     * @return bool
     */
    public function delete(): bool
    {
        $audit = AttendanceAudit::addDeleteAction($this, $this->reason);
        if (empty($audit->primaryColumnValue())) {
            Tools::log()->error('attendance-audit-error');
            return false;
        }

        if (false === parent::delete()) {
            $audit->delete();
            return false;
        }

        return true;
    }

    /**
     * This function is called when creating the model table. Returns the SQL
     * that will be executed after the creation of the table. Useful to insert values
     * default.
     *
     * @return string
     */
    public function install(): string
    {
        new AbsenceConcept();
        new Employee();
        return parent::install();
    }

    /**
     * Import a CSV File with Attendance structure
     *
     * @param Csv $csv
     */
    public function importFromCSV($csv)
    {
        $attendance = new Attendance();
        self::$dataBase->beginTransaction();
        try {
            foreach ($csv->data as $row) {
                $attendance->clear();
                foreach ($csv->titles as $field) {
                    $attendance->{$field} = $this->getValue($row[$field]);
                }
                $attendance->origin = self::ORIGIN_EXTERNAL;
                if (!$attendance->save()) {
                    Tools::log()->error('import-file-error');
                    return;
                }
            }
            self::$dataBase->commit();
            Tools::log()->notice('import-file-correctly');
        } catch (Exception $exception) {
            self::$dataBase->rollback();
            Tools::log()->error('import-file-error');
            Tools::log()->warning($exception->getMessage());
        } finally {
            if (self::$dataBase->inTransaction()) {
                self::$dataBase->rollback();
            }
        }
    }

    /**
     * Create a new period justified attendance from data array
     *
     * @param array $data
     * @param bool $authorized
     */
    public static function justifiedFromData(array $data, bool $authorized = false)
    {
        $attendance = new Attendance();

        // Set basic data
        $attendance->authorized = $authorized;
        $attendance->origin = self::ORIGIN_JUSTIFIED;
        $attendance->idemployee = $data['employee_id'];
        $attendance->idabsenceconcept = $data['absenceconcept_id'];
        $attendance->idclosing = $data['closing_id'] ?? null;
        $attendance->note = $data['justified_note'] ?? '';
        $attendance->checkdate = $data['justified_date'];

        self::$dataBase->beginTransaction();
        try {
            // Create input attendance
            $attendance->kind = self::KIND_INPUT;
            $attendance->checktime = $data['justified_time1'];
            if (!$attendance->save()) {
                Tools::log()->error('attendance-save-input-error');
                return;
            }

            // Create output attendance
            $attendance->id = NULL;
            $attendance->kind = self::KIND_OUTPUT;
            $attendance->checktime = $data['justified_time2'];
            if (!$attendance->save()) {
                Tools::log()->error('attendance-save-output-error');
                return;
            }

            self::$dataBase->commit();
            Tools::log()->notice('justified-attendance-correctly');
        } catch (Exception $exception) {
            self::$dataBase->rollback();
            Tools::log()->error('justified-attendance-error');
            Tools::log()->warning($exception->getMessage());
        } finally {
            if (self::$dataBase->inTransaction()) {
                self::$dataBase->rollback();
            }
        }
    }

    /**
     * Get the last attendance record of employee
     *
     * @param int $idemployee
     * @param string|null $date
     * @return Attendance
     */
    public static function lastAttendance(int $idemployee, ?string $date = null): Attendance
    {
        $where = [ new DataBaseWhere('idemployee', $idemployee) ];
        if (false === empty($date)) {
            $where[] = new DataBaseWhere('checkdate', $date, '<=');
        }

        $order = [ 'checkdate' => 'DESC', 'checktime' => 'DESC' ];

        $attendance = new Attendance();
        $attendance->loadFromCode('', $where, $order);
        return $attendance;
    }

    /**
     * Set the adjustToWordPeriod property.
     *
     * @param bool $adjust
     * @return void
     */
    public function setAdjustToWordPeriod(bool $adjust): void
    {
        $this->adjustToWordPeriod = $adjust;
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'rrhh_attendances';
    }

    /**
     * Reset the values of all model properties.
     */
    public function test(): bool
    {
        if (false === empty($this->primaryColumnValue())) {
            if (true === empty($this->reason) || strlen($this->reason) < 15) {
                Tools::log()->warning('reason-required');
                return false;
            }
        }

        if (empty($this->idemployee)) {
            $this->idemployee = Employee::getIdEmployeeFromCredential($this->credentialid);
        } else {
            $this->credentialid = Employee::getCredentialFromIdEmployee($this->idemployee);
        }

        if (false === empty($this->idabsenceconcept)) {
            $this->origin = self::ORIGIN_JUSTIFIED;
        }

        if (empty($this->checkdate)) {
            $this->checkdate = date(self::DATE_STYLE);
        }

        if (empty($this->checktime)) {
            $this->checktime = date(self::HOUR_STYLE);
        }

        if (empty($this->id) && $this->adjustToWordPeriod) {
            $this->checktime = $this->getWordPeriodTime();
        }

        if (false === parent::test()) {
            return false;
        }

        // Calculate the minutes delayed for an input attendance.
        if ((int)$this->kind === self::KIND_INPUT
            && $this->origin !== self::ORIGIN_JUSTIFIED
            && $this->checktime !== '00:00:00'
        ) {
            $this->inputdelay = AttendanceTools::minutesInputDelayed($this);
        }
        return true;
    }

    /**
     * Returns a list of fields to verify that they do not have html code
     *
     * @return array
     */
    protected function noHtmlFields(): array
    {
        return array_merge(parent::noHtmlFields(), ['note']);
    }

    /**
     * Insert the model data in the database.
     * Make special checks before inserting the data.
     *   - If the record is an output, check if has been changed the day. (Add auto attendances)
     *   - If the record is an input, check if has been delayed.
     *   - For justified records, nothing to do.
     *
     * @param array $values
     * @return bool
     */
    protected function saveInsert(array $values = []): bool
    {
        $autoAttendance = AttendanceTools::autoAttendance(
            $this->kind,
            $this->idemployee,
            $this->checkdate
        );

        if (false === parent::saveInsert($values)) {
            return false;
        }

        if (false === empty($autoAttendance)) {
            $this->addAutoOutput($autoAttendance);
            $this->addAutoInput();
        }
        return true;
    }

    /**
     * Update the model data in the database.
     * Save the update in the audit log.
     *
     * @param array $values
     * @return bool
     */
    protected function saveUpdate(array $values = []): bool
    {
        $oldAttendance = new Attendance();
        $oldAttendance->loadFromCode($this->primaryColumnValue());
        if (false === parent::saveUpdate()){
            return false;
        }

        $audit = AttendanceAudit::addUpdateAction($oldAttendance, $this);
        if (empty($audit->primaryColumnValue())) {
            Tools::log()->error('attendance-audit-error');
        }
        return true;
    }

    /**
     * @return bool
     */
    private function addAutoInput(): bool
    {
        $attendance = new Attendance();
        $attendance->kind = self::KIND_INPUT;
        $attendance->idemployee = $this->idemployee;
        $attendance->checkdate = $this->checkdate;
        $attendance->checktime = '00:00:00';
        $attendance->origin = self::ORIGIN_EXTERNAL;
        return $attendance->save();
    }

    /**
     * @param string $date
     * @return bool
     */
    private function addAutoOutput(string $date): bool
    {
        $attendance = new Attendance();
        $attendance->kind = self::KIND_OUTPUT;
        $attendance->idemployee = $this->idemployee;
        $attendance->checkdate = $date;
        $attendance->checktime = '23:59:59';
        $attendance->origin = self::ORIGIN_EXTERNAL;
        return $attendance->save();
    }

    /**
     *
     * @param string $value
     * @return string|null
     */
    private function getValue($value): ?string
    {
        return in_array($value, ['\N', 'NULL']) ? null : $value;
    }

    /**
     * Get the time of the nearest word period.
     * If the record is an input attendance, adjust to the start of the work period.
     * If the record is an output attendance, adjust to the end of the work period.
     *
     * @return string
     */
    private function getWordPeriodTime(): string
    {
        $workShift = EmployeeWorkShift::workShiftForEmployee($this->idemployee, $this->checkdate);
        if (empty($workShift->id)) {
            return $this->checktime;
        }

        $day = (int)date('d', strtotime($this->checkdate));
        $workPeriods = $workShift->getPeriod($day);
        if (empty($workPeriods)) {
            return $this->checktime;
        }

        // If entry attendance, adjust to the start of the work period.
        if ($this->kind === self::KIND_INPUT) {
            return (strtotime($this->checktime) < strtotime($workPeriods[0]->starttime))
                ? $workPeriods[0]->starttime
                : $this->checktime;
        }

        // If output attendance, adjust to the end of the work period.
        $workPeriodTime = end($workPeriods)->endtime;
        return (strtotime($this->checktime) > strtotime($workPeriodTime))
            ? $workPeriodTime
            : $this->checktime;
    }
}
