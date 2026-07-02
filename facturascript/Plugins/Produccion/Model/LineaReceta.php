<?php
/**
 * This file is part of the Produccion plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 * All Rights Reserved.
 */
namespace FacturaScripts\Plugins\Produccion\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;

/**
 * Class to indicate raw material products.
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Jose Antonio Cuello <yopli2000@gmail.com>
 */
class LineaReceta extends ModelClass
{
    use ModelTrait;

    /**
     * Available quantity.
     * Calculate field.
     *
     * @var int
     */
    public $available;

    /**
     * Amount of raw material required.
     *
     * @var float
     */
    public $cantidad;

    /**
     * Primary key.
     *
     * @var int
     */
    public $idlinea;

    /**
     * Link to recipe model.
     *
     * @var int
     */
    public $idreceta;

    /**
     * Link to the raw material variant.
     *
     * @var string
     */
    public $referencia;

    /**
     * Quantity of stock.
     * Calculate field.
     *
     * @var int
     */
    public $stock;

    /**
     * Indicates if recalculate the recipe cost when saving the raw ingredient.
     * By default, allways recalcualate the cost when save.
     *
     * @var bool
     */
    private bool $recalculateCost = true;

    /**
     * Reset the values of all model properties.
     */
    public function clear(): void
    {
        parent::clear();
        $this->available = 0;
        $this->cantidad = 1.00;
        $this->stock = 0;
    }

    /**
     * Returns the recipe model linked to this line.
     *
     * @return Receta
     */
    public function getRecipe(): Receta
    {
        $recipe = new Receta();
        $recipe->load($this->idreceta);
        return $recipe;
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
        new Receta();
        return parent::install();
    }

    /**
     * Returns the name of the column that is the model's primary key.
     *
     * @return string
     */
    public static function primaryColumn(): string
    {
        return 'idlinea';
    }

    /**
     * Set the recalculate cost property.
     *
     * @param bool $recalculateCost
     */
    public function setRecalculateCost(bool $recalculateCost): void
    {
        $this->recalculateCost = $recalculateCost;
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'produccion_lineasrecetas';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        $this->referencia = Tools::noHtml($this->referencia);
        return parent::test();
    }

    /**
     * Stores the model data in the database.
     * Calculate new recipe cost.
     *
     * @return bool
     */
    public function save(): bool
    {
        if (false === parent::save()) {
            return false;
        }

        if ($this->recalculateCost) {
            $this->getRecipe()->save();
        }
        return true;
    }
}
