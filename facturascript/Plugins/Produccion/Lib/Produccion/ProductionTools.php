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
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Produccion\Model\OrdenIngrediente;
use FacturaScripts\Plugins\Produccion\Model\OrdenProduccion;
use FacturaScripts\Plugins\Produccion\Model\OrdenProducto;
use FacturaScripts\Plugins\Produccion\Model\Receta;
use FacturaScripts\Plugins\Produccion\Model\RecetaProducto;

/**
 * Class to manager production order actions.
 *   - new order from recipe
 *   - clone data from recipe to production order
 *   - clone data from production order to another
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class ProductionTools
{
    /**
     * Clone into a production order all ingredients and products of a recipe.
     *
     * @param int $idrecipe
     * @param int $idorder
     * @param float $quantity
     * @return bool
     */
    public static function cloneChildData(int $idrecipe, int $idorder, float $quantity = 1.00): bool
    {
        $recipe = new Receta();
        if (false === $recipe->load($idrecipe)) {
            return false;
        }

        $order = new OrdenProduccion();
        if (false === $order->load($idorder)) {
            return false;
        }

        foreach ($recipe->getLines() as $ingredient) {
            if ($ingredient->numserie) {
                for ($qty = 0; $qty < $ingredient->cantidad * $quantity; $qty++) {
                    $orderIngredient = new OrdenIngrediente();
                    $orderIngredient->idorden = $idorder;
                    $orderIngredient->referencia = $ingredient->referencia;
                    $orderIngredient->cantidad = 1;
                    $orderIngredient->save();
                }
                continue;
            }

            $orderIngredient = new OrdenIngrediente();
            $orderIngredient->idorden = $idorder;
            $orderIngredient->referencia = $ingredient->referencia;
            $orderIngredient->cantidad = $ingredient->cantidad * $quantity;
            $orderIngredient->save();
        }

        foreach ($recipe->getProducts() as $product) {
            $orderProduct = new OrdenProducto();
            $orderProduct->idorden = $idorder;
            $orderProduct->referencia = $product->referencia;
            $orderProduct->cantidad = $product->cantidad * $quantity;
            $orderProduct->numserietype = $product->numserietype;
            $orderProduct->save();
        }

        return true;
    }

    /**
     * Clones the production order.
     *
     * @param OrdenProduccion $production
     * @return int
     */
    public static function cloneProductionOrder(OrdenProduccion $production): int
    {
        $dataBase = new DataBase();
        $dataBase->beginTransaction();
        try {
            $newProduction = new OrdenProduccion();
            if (false === self::cloneProduction($production, $newProduction)
                || false === self::cloneProductionIngredients($newProduction, $production->getIngredients())
                || false === self::cloneProductionProducts($newProduction, $production->getProducts())
            ) {
                return 0;
            }

            $dataBase->commit();
            return $newProduction->id;
        } finally {
            if ($dataBase->inTransaction()) {
                $dataBase->rollback();
            }
        }
    }

    /**
     * List of recipes where this ingredient is produced.
     * This happens when recipes are chained.
     *
     * @param $reference
     * @return array
     */
    public static function producedIn($reference): array
    {
        $where = [ new DataBaseWhere('referencia', $reference) ];
        $recipeProduct = new RecetaProducto();
        if ($recipeProduct->loadWhere($where)) {
            return [
                'idreceta' => $recipeProduct->idreceta,
                'cantidad' => $recipeProduct->cantidad,
            ];
        }

        return [];
    }

    /**
     * Create a new production order from a recipe.
     *
     * @param string $codeRecipe
     * @param float $quantity
     * @return OrdenProduccion
     */
    public static function recipeToProductionOrder(string $codeRecipe, float $quantity = 1.00): OrdenProduccion
    {
        $recipe = new Receta();
        $where = [ new DataBaseWhere('codreceta', $codeRecipe) ];
        if (false === $recipe->loadWhere($where)) {
            return new OrdenProduccion();
        }

        $order = new OrdenProduccion();
        $order->idreceta = $recipe->idreceta;
        $order->setRecipeQuantity($quantity);
        $order->save();
        return $order;
    }

    /**
     * Set the quantity decimals for inputs.
     *
     * @param BaseView $view
     * @return void
     */
    public static function setQuantityDecimals($view):  void
    {
        $quantityDecimal = Tools::settings('production', 'quantitydecimalorder', false);
        $step = $quantityDecimal ? '0.001' : '1';

        $column = $view->columnForName('quantity');
        if ($column && $column->widget->getType() === 'number') {
            $column->widget->step = $step;
        }

        $columnModal = $view->columnModalForName('quantity');
        if ($columnModal && $columnModal->widget->getType() === 'number') {
            $columnModal->widget->step = $step;
        }
    }

    /**
     * Clones the production order data.
     *
     * @param OrdenProduccion $production
     * @param OrdenProduccion $newProduction
     * @return bool
     */
    protected static function cloneProduction(OrdenProduccion $production, OrdenProduccion $newProduction): bool
    {
        $newProduction->loadFromData([
            'confirmar' => $production->confirmar,
            'coste' => $production->coste,
            'idreceta' => $production->idreceta,
            'observaciones' => $production->observaciones,
            'usarstock' => $production->usarstock,
        ]);

        if (false === $newProduction->save()) {
            Tools::log()->error('production-order-clone-error', ['%production%' => $production->id]);
            return false;
        }
        return true;
    }

    /**
     * Clones the production ingredients.
     *
     * @throws Exception
     */
    protected static function cloneProductionIngredients(OrdenProduccion $newProduction, array $items): bool
    {
        foreach ($items as $ingredient) {
            $newIngredient = new OrdenIngrediente([
                'idorden' => $newProduction->id,
                'idproducto' => $ingredient->idproducto,
                'referencia' => $ingredient->referencia,
                'cantidad' => $ingredient->cantidad,
            ]);
            if (false === $newIngredient->save()) {
                Tools::log()->error('ingredient-clone-error', ['%ingredient%' => $ingredient->referencia]);
                return false;
            }
        }
        return true;
    }

    /**
     * Clones the production products.
     *
     * @param OrdenProduccion $newProduction
     * @param array $items
     * @return bool
     * @throws Exception
     */
    protected static function cloneProductionProducts(OrdenProduccion $newProduction, array $items): bool
    {
        foreach ($items as $product) {
            $newProduct = new OrdenProducto([
                'idorden' => $newProduction->id,
                'idproducto' => $product->idproducto,
                'referencia' => $product->referencia,
                'cantidad' => $product->cantidad,
                'numserietype' => $product->numserietype,
            ]);
            if (false === $newProduct->save()) {
                Tools::log()->error('product-clone-error', ['%product%' => $product->referencia]);
                return false;
            }
        }
        return true;
    }
}
