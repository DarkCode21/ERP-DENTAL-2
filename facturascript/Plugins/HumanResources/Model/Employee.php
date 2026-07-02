<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Paises;
use FacturaScripts\Core\Lib\Vies;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Base\Contact;
use FacturaScripts\Dinamic\Model\CodeModel;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\Department;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelExtendedTrait;

/**
 * List of Employee
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class Employee extends Contact
{

    use ModelTrait;
    use ModelExtendedTrait;

    /**
     * Indicates the gender of employee
     */
    const GENDER_MAN = 1;
    const GENDER_WOMEN = 2;
    const GENDER_OTHER = 3;

    /**
     * Indicates the civil status of employee
     */
    const MARITAL_SINGLE = 1;
    const MARITAL_MARRIED = 2;
    const MARITAL_WIDOWER = 3;
    const MARITAL_DIVORCED = 4;

    /**
     * Employee's bank account for payment transactions
     *
     * @var string
     */
    public $bankaccountid;

    /**
     * Employee's day of birth
     *
     * @var string
     */
    public $birthday;

    /**
     * Internal identification. Used for attendances control
     *
     * @var string
     */
    public $credentialid;

    /**
     * Employee's discharge date
     *
     * @var string
     */
    public $dischargedate;

    /**
     * Employee's discharge reason/note
     *
     * @var string
     */
    public $dischargereason;

    /**
     * Gender of the employee
     * (1: man, 2: woman, 3: other)
     *
     * @var integer
     */
    public $gender;

    /**
     * Link to company model
     *
     * @var integer
     */
    public $idcompany;

    /**
     * Lint to contact model.
     * Main address data.
     *
     * @var int
     */
    public $idcontact;

    /**
     * Link to department model
     *
     * @var integer
     */
    public $iddepartment;

    /**
     * Identifier of public or private medical insurance
     *
     * @var string
     */
    public $insuranceid;

    /**
     * Employee's civil status
     * (1: single, 2: married, 3: widower, 4: divorced)
     *
     * @var integer
     */
    public $marital;

    /**
     * Link to user model
     *
     * @var string
     */
    public $nick;

    /**
     * Indicates if the contact is being updated
     *
     * @var bool
     */
    private $updatingContact = false;

    public function checkVies(): bool
    {
        $codiso = Paises::get($this->getContact()->codpais)->codiso ?? '';
        return Vies::check($this->cifnif ?? '', $codiso) === 1;
    }
    
    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->idcompany = Tools::settings('default', 'idempresa');
        $this->gender = self::GENDER_MAN;
        $this->marital = self::MARITAL_SINGLE;
    }

    /**
     * Allows using this model as a source in CodeModel special model.
     *
     * @param string $query
     * @param string $fieldCode
     * @param array  $where
     *
     * @return CodeModel[]
     */
    public function codeModelSearch(string $query, string $fieldCode = '', array $where = []): array
    {
        $field = empty($fieldCode) ? $this->primaryColumn() : $fieldCode;
        $fields = 'id|credentialid|nombre|cifnif|insuranceid';
        $where[] = new DataBaseWhere($fields, mb_strtolower($query, 'UTF8'), 'LIKE');
        return CodeModel::all($this->tableName(), $field, 'nombre', false, $where);
    }

    /**
     * Get the contact associated with this employee.
     *
     * @return Contacto
     */
    public function getContact(): Contacto
    {
        $contact = new Contacto();
        $contact->loadFromCode($this->idcontact);
        return $contact;
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
        new Department();
        new CourseTraining();
        return parent::install();
    }

    /**
     * Search id Employee from credential
     *
     * @param int $idEmployee
     * @return int|null
     */
    public static function getCredentialFromIdEmployee($idEmployee): ?int
    {
        $model = new CodeModel();
        $value = $model->getDescription(self::tableName(), self::primaryColumn(), $idEmployee, 'credentialid');
        return empty($value) ? null : (int) $value;
    }

    /**
     * Search credential from id Employee
     *
     * @param int $credential
     * @return int|null
     */
    public static function getIdEmployeeFromCredential($credential): ?int
    {
        $model = new CodeModel();
        $value = $model->getDescription(self::tableName(), 'credentialid', $credential, self::primaryColumn());
        return empty($value) ? null : (int) $value;
    }

    /**
     * Get an Ids list for employee where list
     *
     * @param DataBaseWhere[] $where
     * @return int[]
     */
    public static function getIdFromDataBaseWhere($where): array
    {
        $result = [];
        $model = new Employee();
        foreach ($model->all($where) as $employee) {
            $result[] = $employee->id;
        }

        return $result;
    }

    /**
     * Returns the name of the column that describes the model, such as name, description...
     *
     * @return string
     */
    public function primaryDescriptionColumn(): string
    {
        return empty($this->nombre) ? $this->primaryColumn() : 'nombre';
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'rrhh_employees';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        $this->checkNoHtmlFields();
        return parent::test();
    }

    public function dischargeEmployee(string $date, string $description):bool
    {
        $this->dischargedate = $date;
        $this->dischargereason = $description;
        if(false === $this->save()) {
            return false;
        }
        if(empty($this->nick)){
            return true;
        }
        $user = new User();
        $user->loadFromCode($this->nick);
        if($user->admin){
            return true;
        }
        $user->enabled = false;
        $user->save();
        return true;

    }

    /**
     * Returns a list of fields to verify that they do not have html code
     *
     * @return array
     */
    protected function noHtmlFields(): array
    {
        return ['nombre', 'observaciones', 'bankaccountid', 'insuranceid'];
    }

    /**
     * Insert the model data in the database.
     *
     * @param array $values
     * @return bool
     */
    protected function saveInsert(array $values = []): bool
    {
        $result = parent::saveInsert($values);
        if ($result && !$this->updatingContact) {
            $this->updateContact();
        }

        return $result;
    }

    /**
     * Update the model data in the database.
     *
     * @param array $values
     * @return bool
     */
    protected function saveUpdate(array $values = array()): bool
    {
        $result = parent::saveUpdate($values);
        if ($result && !$this->updatingContact) {
            $this->updateContact();
        }

        return $result;
    }

    /**
     * Sincronize contact data
     */
    private function updateContact()
    {
        if ($this->updatingContact) {
            return;
        }

        $contact = new Contacto();
        if (!empty($this->idcontact)) {
            $contact->loadFromCode($this->idcontact);
            $this->idcontact = $contact->idcontacto;    // Force for wrong idcontact
        }

        $contact->cifnif = $this->cifnif;
        $contact->tipoidfiscal = $this->tipoidfiscal;
        $contact->descripcion = $this->nombre;
        $contact->email = $this->email;
        $contact->idemployee = $this->id;
        $contact->nombre = $this->nombre;
        $contact->personafisica = true;
        $contact->telefono1 = $this->telefono1;
        $contact->telefono2 = $this->telefono2;
        $this->updatingContact = true;
        if ($contact->save() && empty($this->idcontact)) {
            $this->idcontact = $contact->idcontacto;
            $this->save();
            $this->updatingContact = false;
        }
    }
}
