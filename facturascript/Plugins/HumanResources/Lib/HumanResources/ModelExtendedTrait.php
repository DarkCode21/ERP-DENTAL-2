<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\HumanResources;

use FacturaScripts\Core\Base\Utils;
use FacturaScripts\Core\Tools;

/**
 * Trait for Models that add:
 *   - common structure for the primary key
 *   - descriptive field
 *   - common processes not included in parent classes
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
trait ModelExtendedTrait
{

    /**
     * Primary key
     *
     * @var integer
     */
    public $id;

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
     * Check a period between two dates.
     * The "notEmptyEnd" parameter indicates whether the end date may be empty.
     *
     * @param string $startdate
     * @param ?string $enddate
     * @param bool $notEmptyEnd
     * @return bool
     */
    protected function errorInPeriod(string $startdate, ?string $enddate, bool $notEmptyEnd = false): bool
    {
        if (empty($this->enddate)) {
            if ($notEmptyEnd) {
                Tools::log()->warning('period-date-out-range');
            }
            return $notEmptyEnd;
        }

        $diff = strtotime($enddate) - strtotime($startdate);
        if ($diff < 0) {
            Tools::log()->warning('period-date-out-range');
            return true;
        }

        return false;
    }

    /**
     * Check that a list of fields do not contain html code
     */
    protected function checkNoHtmlFields()
    {
        foreach ($this->noHtmlFields() as $field) {
            $value = $this->{$field};
            $this->{$field} = Utils::noHtml($value);
        }
    }

    /**
     * Indicates whether the name field exists in the model
     *
     * @return boolean
     */
    protected function hasName(): bool
    {
        $fields = $this->getModelFields();
        return isset($fields['name']);
    }
}
