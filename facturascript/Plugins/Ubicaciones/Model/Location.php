<?php
/**
 * This file is part of Ubicaciones plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Ubicaciones    Copyright (C) 2019-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\Ubicaciones\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Almacen;

/**
 * Each of the existing locations within a warehouse
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class Location extends ModelClass
{

    public const STORAGE_TYPE_STORAGE = 0;
    public const STORAGE_TYPE_PICKING = 1;

    use ModelTrait;

    /**
     * Corridor inside the warehose.
     *
     * @var string
     */
    public $aisle;

    /**
     * Compartment inside the closet band.
     *
     * @var string
     */
    public $drawer;

    /**
     * Link to the Warehouse model
     *
     * @var string
     */
    public $codewarehouse;

    /**
     * Primary key.
     *
     * @var int
     */
    public $id;

    /**
     * Cupboard or area within the aisle.
     *
     * @var string
     */
    public $rack;

    /**
     * Closet band into rack.
     *
     * @var string
     */
    public $shelf;

    /**
     * Type of storage.
     *   - 0: Storage
     *   - 1: Picking
     *
     * @var int
     */
    public $storagetype;

    /**
     * Shelf validation code. This is normally used in the preparation of sales orders.
     *
     * @var string
     */
    public $validationcode;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->storagetype = self::STORAGE_TYPE_STORAGE;
    }

    /**
     * Get complete description for location
     *
     * @return string
     */
    public function descriptionComplete(): string
    {
        $i18n = Tools::lang();
        $description = '';
        $this->addToDescription($description, $this->aisle, $i18n->trans('aisle'));
        $this->addToDescription($description, $this->rack, $i18n->trans('rack'));
        $this->addToDescription($description, $this->shelf, $i18n->trans('shelf'));
        $this->addToDescription($description, $this->drawer, $i18n->trans('drawer'));
        return $description;
    }

    /**
     * Get complete description for specified location
     *
     * @return string
     */
    public static function descriptionLocation($idlocation): string
    {
        $location = new self();
        if ($location->loadFromCode($idlocation)) {
            return $location->descriptionComplete();
        }
        return $idlocation;
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
        new Almacen();
        parent::install();

        return '';
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
        return 'locations';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        if (!$this->hasValues()) {
            Tools::log()->warning('one-field-required');
            return false;
        }
        return parent::test();
    }

    /**
     * Returns the url where to see / modify the data.
     *
     * @param string $type
     * @param string $list
     *
     * @return string
     */
    public function url(string $type = 'auto', string $list = 'List'): string
    {
        return parent::url($type, 'ListAlmacen?activetab=List');
    }

    /**
     *
     * @param string $description
     * @param string $value
     * @param string $label
     */
    private function addToDescription(&$description, $value, $label)
    {
        if (($value == '') || ($value == null)) {
            return;
        }

        if (!empty($description)) {
            $description .= ' > ';
        }

        $description .= $label . ': ' . $value;
    }

    /**
     * Check if there are location values informed.
     *
     * @return bool
     */
    private function hasValues(): bool
    {
        return !(empty($this->aisle)
            && empty($this->rack)
            && empty($this->shelf)
            && empty($this->drawer)
        );
    }
}
