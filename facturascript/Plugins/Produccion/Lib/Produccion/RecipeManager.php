<?php
/**
 * This file is part of the Produccion plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 * All Rights Reserved.
 */
namespace FacturaScripts\Plugins\Produccion\Lib\Produccion;

use Exception;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\MovimientoStock;
use FacturaScripts\Dinamic\Model\RecetaProducto;
use FacturaScripts\Dinamic\Model\Stock;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\Produccion\Model\Join\LineaReceta;
use FacturaScripts\Plugins\Produccion\Model\Receta;
use FacturaScripts\Plugins\Produccion\Model\RecetaHistorial;

/**
 * Class to manage recipe production
 *
 * @author Carlos Garcia Gomez  <carlos@facturascripts.com>
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class RecipeManager
{
    /**
     * Detail or lines of products to be produce.
     *
     * @var RecetaProducto[]
     */
    protected array $products = [];

    /**
     * Detail or lines of the Recipe to be made.
     * Recipe ingredients.
     *
     * @var LineaReceta[]
     */
    protected array $lines = [];

    /**
     * Recipe to be made.
     *
     * @var Receta
     */
    protected Receta $recipe;

    /**
     * Constructor and class initializer.
     */
    public function __construct()
    {
        $this->recipe = new Receta();
    }

    /**
     * Main procedure for making a recipe.
     * <b>When checking the stock of the ingredients, if there is no stock
     * and the ingredient belongs to another recipe, an automatic production
     * of the children's recipe is performed.</b>.
     * <b>This is a recursive procedure</b>.
     * The transaction is handled first of all because it can come
     * from another call or subtract stock in the check.
     *
     * @param int|string $code
     * @param int|float $quantity
     * @return bool
     */
    public function produce(int|string $code, int|float $quantity): bool
    {
        $dataBase = new DataBase();
        $inTransaction = $dataBase->inTransaction();
        if (false === $inTransaction) {
            $dataBase->beginTransaction();
        }
        try {
            // It is controlled within the process because it is a recursive procedure.
            if (false === $this->prepareData($code) || false === $this->checkStock($quantity)) {
                return false;
            }

            if ($this->removeStock($quantity) &&
                $this->addStock($quantity) &&
                $this->addHistorical($quantity) &&
                $this->updateRecipe())
            {
                if (false === $inTransaction) {
                    $dataBase->commit();
                }
                return true;
            }
        } catch (Exception $exc) {
            Tools::log()->error($exc->getMessage());
        } finally {
            if (false === $inTransaction && $dataBase->inTransaction()) {
                $dataBase->rollback();
            }
        }

        return false;
    }

    /**
     * Add the production to the history of the recipe.
     *
     * @param $quantity
     * @return bool
     */
    protected function addHistorical($quantity): bool
    {
        $historical = new RecetaHistorial();
        $historical->cantidad = $quantity;
        $historical->docmodel = $this->recipe->modelClassName();
        $historical->idreceta = $this->recipe->idreceta;
        return $historical->save();
    }

    /**
     * Add the manufactured products to stock.
     *
     * @param int|float $quantity
     * @return bool
     */
    protected function addStock($quantity): bool
    {
        foreach ($this->products as $product) {
            if (false === $this->addRecipeStock($product->referencia, $product->cantidad, $quantity)) {
                Tools::log()->warning('error-saving-stock');
                return false;
            }

            if (false === $this->addRecipeMovement($product->referencia, $product->cantidad, $quantity)) {
                Tools::log()->warning('error-saving-stock');
                return false;
            }
        }

        return true;
    }

    /**
     * Remove the raw products from stock.
     *
     * @param int|float $quantity
     * @return bool
     */
    protected function removeStock($quantity): bool
    {
        if ($this->removeRawMaterialStock($quantity) && $this->addRawMaterialMovement($quantity)) {
            return true;
        }

        Tools::log()->warning('error-saving-stock');
        return false;
    }

    /**
     * Updates the cost price and manufacturing date.
     *
     * @return bool
     */
    protected function updateRecipe(): bool
    {
        // Update the recipe last production date.
        $this->recipe->coste = $this->getProductCost();
        $this->recipe->ultimaproduccion = date(Tools::DATETIME_STYLE);
        if (false === $this->recipe->save()) {
            Tools::log()->warning('error-saving-recipe');
            return false;
        }

        // update cost into product variant with the products marked as share cost.
        $products = $this->recipe->getProductShareCost();
        if (empty($products)) {
            return true;
        }

        $cost = $this->recipe->coste / count($products);
        $variant = new Variante();
        foreach ($products as $product) {
            $varWhere = [ new DataBaseWhere('referencia', $product->referencia)];
            if (false === $variant->loadWhere($varWhere)) {
                continue;
            }
            $variant->coste = empty($product->cantidad) ? $cost : round($cost / $product->cantidad, 4);
            $variant->save();
        }
        return true;
    }

    /**
     * Register the movement for the exit of the raw material stock.
     *
     * @param int|float $quantity
     * @return bool
     */
    private function addRawMaterialMovement($quantity): bool
    {
        foreach ($this->lines as $recipeLine) {
            if ($recipeLine->nostock) {
                continue;
            }

            $movement = new MovimientoStock();
            $movement->cantidad = $recipeLine->cantidad * $quantity * -1;
            $movement->codalmacen = $recipeLine->codalmacen;
            $movement->docid = $recipeLine->idreceta;
            $movement->documento = $recipeLine->codreceta;
            $movement->referencia = $recipeLine->referencia;
            $movement->idproducto = $recipeLine->idproducto;
            $movement->docmodel = $this->recipe->modelClassName();
            if (false === $movement->save()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Records the movement for the put into stock of the manufactured material.
     *
     * @param string $reference
     * @param int|float $units
     * @param int|float $quantity
     * @return bool
     */
    private function addRecipeMovement(string $reference, $units, $quantity): bool
    {
        $movement = new MovimientoStock();
        $movement->cantidad = ($units * $quantity);
        $movement->codalmacen = $this->recipe->codalmacen2;
        $movement->docid = $this->recipe->idreceta;
        $movement->docmodel = $this->recipe->modelClassName();
        $movement->documento = $this->recipe->codreceta;
        $movement->referencia = $reference;
        $movement->idproducto = $this->recipe->getVariant($reference)->idproducto;
        return $movement->save();
    }

    /**
     * Add the manufactured material to the stock.
     *
     * @param string $reference
     * @param int|float $units
     * @param int|float $quantity
     * @return bool
     */
    private function addRecipeStock(string $reference, $units, $quantity): bool
    {
        $stock = new Stock();
        $where = [
            new DataBaseWhere('codalmacen', $this->recipe->codalmacen2),
            new DataBaseWhere('referencia', $reference)
        ];
        if (false === $stock->loadWhere($where)) {
            $stock->codalmacen = $this->recipe->codalmacen2;
            $stock->idproducto = $this->recipe->getVariant($reference)->idproducto;
            $stock->referencia = $reference;
        }
        $stock->cantidad += $units * $quantity;
        return $stock->save();
    }

    /**
     * Check if there is enough stock to make the recipe.
     * If there is not enough stock, check if the ingredient is a manufactured
     * product, and we manufacture the quantity necessary to have stock.
     *
     * @param int|float $quantity
     * @return bool
     */
    private function checkStock($quantity): bool
    {
        foreach ($this->lines as $recipeLine) {
            if ($recipeLine->nostock || $recipeLine->ventasinstock) {
                continue;
            }

            if ($recipeLine->disponible < ($recipeLine->cantidad * $quantity)) {
                // Checks if the ingredient is part of another recipe as a manufactured product.
                $producedIn = ProductionTools::producedIn($recipeLine->referencia);
                if (isset($producedIn['idreceta'])) {
                    $neededQuantity = ($recipeLine->cantidad * $quantity) - $recipeLine->disponible;
                    $recipeQuantity = $producedIn['cantidad'] > 0
                        ? ceil($neededQuantity/$producedIn['cantidad'])
                        : $neededQuantity;

                    // Produce the necessary amount of the ingredient
                    $manager = new RecipeManager();
                    if ($manager->produce($producedIn['idreceta'], $recipeQuantity)) {
                        continue;
                    }
                }
                // There is not enough stock to create the recipe.
                Tools::log()->notice('not-enough-stock', ['%reference%' => $recipeLine->referencia]);
                return false;
            }
        }
        return true;
    }

    /**
     * Obtains the unit cost of the product to be manufactured.
     *
     * @return float
     */
    private function getProductCost(): float
    {
        $result = 0.00;
        foreach ($this->lines as $recipeLine) {
            $result += $recipeLine->getCost();
        }
        return $result;
    }

    /**
     *
     * @param int|string $code
     * @return bool
     */
    private function prepareData($code): bool
    {
        if (false === $this->recipe->load($code)) {
            return false;
        }

        $this->products = $this->recipe->getProducts();
        $this->lines = $this->recipe->getLines();
        return count($this->lines) > 0;
    }

    /**
     * Subtract raw material from stock.
     *
     * @param int|float $quantity
     * @return bool
     */
    private function removeRawMaterialStock($quantity): bool
    {
        foreach ($this->lines as $recipeLine) {
            if ($recipeLine->nostock) {
                continue;
            }

            $stock = new Stock();
            $where = [
                new DataBaseWhere('codalmacen', $recipeLine->codalmacen),
                new DataBaseWhere('referencia', $recipeLine->referencia)
            ];
            $stock->loadWhere($where);
            $stock->cantidad -= $recipeLine->cantidad * $quantity;
            if (false === $stock->save()) {
                return false;
            }
        }
        return true;
    }
}
