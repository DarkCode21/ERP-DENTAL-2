<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\HumanResources;

use FacturaScripts\Core\Model\Base\ModelOnChangeClass;

/**
 * Extended class for OnChange Models that add:
 *   - auto description column from 'name' column
 *   - auto check no html fields
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
abstract class ModelExtendedOnChange extends ModelOnChangeClass
{

    use ModelExtendedTrait;

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
}
