<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\HumanResources;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelExtendedTrait;

/**
 * Extended class for Models that add:
 *   - auto description column from 'name' column
 *   - auto check no html fields
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
abstract class ModelExtended extends ModelClass
{

    use ModelExtendedTrait;

    /**
     * Audit fields. Only for internal use.
     * They are always defined but are only used if audit control is enabled.
     */
    public ?string $creation_date = null;
    public ?string $creation_ip = null;
    public ?string $last_nick = null;
    public ?string $last_update = null;

    /**
     * Indicates if the record has been audit.
     *
     * @var bool
     */
    private bool $audit = false;

    /**
     * Returns the name of the column that describes the model, such as name, description...
     *
     * @return string
     */
    public function primaryDescriptionColumn(): string
    {
        $fields = $this->getModelFields();
        return isset($fields['name']) ? 'name' : parent::primaryDescriptionColumn();
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

    /**
     * Returns a list of fields to verify that they do not have html code
     *
     * @return array
     */
    protected function noHtmlFields(): array
    {
        return $this->hasName() ? ['name'] : [];
    }

    /**
     * Insert the model data in the database.
     *
     * @param array $values
     * @return bool
     */
    protected function saveInsert(array $values = []): bool
    {
        if ($this->audit) {
            $this->creation_date = Tools::dateTime();
            $this->creation_ip = Session::getClientIp();;
            $this->last_nick = Session::user()->nick;
            $this->last_update = $this->creation_date;
        }

        return parent::saveInsert($values);
    }

    /**
     * Update the model data in the database.
     *
     * @param array $values
     * @return bool
     */
    protected function saveUpdate(array $values = []): bool
    {
        if ($this->audit) {
            $this->last_nick = Session::user()->nick;
            $this->last_update = Tools::dateTime();
        }
        return parent::saveUpdate($values);
    }

    /**
     * Set the audit control for record.
     *
     * @param bool $audit
     * @return void
     */
    protected function setAuditControl(bool $audit): void
    {
        $this->audit = $audit;
    }
}
