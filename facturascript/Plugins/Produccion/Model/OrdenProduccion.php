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

use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Receta;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\ProductionTools;
use FacturaScripts\Plugins\Produccion\Model\Join\LineaProduccion;

/**
 * Class to manage production order.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class OrdenProduccion extends ModelClass
{
    public const STATUS_PENDING = 0;
    public const STATUS_STARTED = 10;
    public const STATUS_VERIFYING = 15;
    public const STATUS_FINISHED = 20;
    public const STATUS_CANCELLED = 99;

    public const STOCK_AVAILABLE = 0;
    public const STOCK_REAL = 1;

    use ModelTrait;

    /**
     * Indicates if the production must confirm the quantities
     * or if the stock can be directly updated when production begins.
     *
     * @var bool
     */
    public $confirmar;

    /**
     * Total cost of the production order.
     *
     * @var float
     */
    public $coste;

    /**
     * Indicate in which phase the document is.
     *
     * @var int
     */
    public $estado;

    /**
     * Document date.
     *
     * @var string
     */
    public $fecha;

    /**
     * Production date.
     *
     * @var string
     */
    public $fechafabricacion;

    /**
     * Document time.
     *
     * @var string
     */
    public $hora;

    /**
     * Production time.
     *
     * @var string
     */
    public $horafabricacion;

    /**
     * Primary key.
     *
     * @var int
     */
    public $id;

    /**
     * Link to recipe model.
     *
     * @var int
     */
    public $idreceta;

    /**
     * User who creates the document.
     * Link to the user model.
     *
     * @var string
     */
    public $nick;

    /**
     * User who is responsible for the production.
     * Link to the user model.
     *
     * @var string
     */
    public $nickfabricacion;

    /**
     * Notes of the document.
     *
     * @var string
     */
    public $observaciones;

    /**
     * Indicates whether available or actual stock should be used when producing
     *
     * @var int
     */
    public $usarstock;

    /**
     * Date for auto generation through scheduled task.
     *
     * @var string
     */
    public $vencimiento;

    /**
     * Used to multiply the quantities of ingredients and final products
     * of the recipe, in the process of copying data from the recipe.
     *
     * @var int
     */
    private $recipeQuantity = 0.00;

    /**
     * Returns true if the order, ingredients or production can be deleted.
     *
     * @return bool
     */
    public function canDelete(): bool
    {
        return in_array($this->estado, [
            OrdenProduccion::STATUS_PENDING,
            OrdenProduccion::STATUS_STARTED,
            OrdenProduccion::STATUS_CANCELLED,
        ]);
    }

    /**
     * Reset the values of all model properties.
     */
    public function clear(): void
    {
        parent::clear();
        $this->confirmar = (bool)Tools::settings('production', 'confirmproductionorder', false) ?? false;
        $this->estado = self::STATUS_PENDING;
        $this->fecha = Tools::date();
        $this->hora = Tools::hour();
        $this->usarstock = self::STOCK_AVAILABLE;
        $this->nick = Session::user()->nick;
    }

    /**
     * Remove the model data from the database.
     * If the order has performed a stock update,
     * we must ensure that the stock is returned to the warehouse.
     *
     * @return bool
     */
    public function delete(): bool
    {
        if (false === $this->canDelete()) {
            Tools::log()->error('error-order-status');
            return false;
        }
        return parent::delete();
    }

    /**
     * Get total ingredients used in production.
     *
     * @return OrdenIngrediente[]
     */
    public function getIngredients(): array
    {
        return OrdenIngrediente::all(
            [new DataBaseWhere('idorden', $this->id)],
            ['id' => 'ASC']
        );
    }

    /**
     * Obtains all the lines that make up the recipe
     * with all the complementary data: Variant, Product and Stock.
     *
     * @return LineaProduccion[]
     */
    public function getLines(): array
    {
        $lines = new LineaProduccion();
        return $lines->all(
            [new DataBaseWhere('lineas.idorden', $this->id)],
            ['lineas.id' => 'ASC']
        );
    }

    /**
     * Get total products to be produced.
     *
     * @return OrdenProducto[]
     */
    public function getProducts(): array
    {
        return OrdenProducto::all(
            [new DataBaseWhere('idorden', $this->id)],
            ['id' => 'ASC']
        );
    }

    /**
     * Obtains the recipe associated with the document.
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
        return 'id';
    }

    /**
     * Sets the quantity multiplier in the process of copying from the recipe.
     *
     * @param float $newQuantity
     */
    public function setRecipeQuantity(float $newQuantity): void
    {
        $this->recipeQuantity = $newQuantity;
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'produccion_ordenes';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * Calculate total cost if the order is not completed.
     *
     * @return bool
     * @throws Exception
     */
    public function test(): bool
    {
        if (is_null($this->usarstock)) {
            $this->usarstock = self::STOCK_AVAILABLE;
        }
        if ($this->estado < self::STATUS_FINISHED) {
            $this->coste = $this->getRecipe()->coste;
        }

        $this->setFabricationDate();
        $this->observaciones = Tools::noHtml($this->observaciones);
        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        return parent::url($type, 'ListReceta?activetab=' . $list);
    }

    /**
     * Sets the fabrication date when the order is completed.
     *
     * @return void
     */
    protected function setFabricationDate(): void
    {
        if ($this->estado !== self::STATUS_FINISHED
            || false === empty($this->fechafabricacion)
        ) {
            return;
        }

        $this->nickfabricacion  = Session::user()->nick;
        $this->fechafabricacion = Tools::date();
        $this->horafabricacion  = Tools::hour();
    }

    /**
     * Insert the model data in the database.
     *
     * @return bool
     */
    protected function saveInsert(array $values = []): bool
    {
        if (parent::saveInsert($values)) {
            if ($this->recipeQuantity > 0
                && false === ProductionTools::cloneChildData($this->idreceta, $this->id, $this->recipeQuantity)
            ) {
                Tools::log()->warning('no-clone-recipe-data');
            }
            return true;
        }
        return false;
    }
}
