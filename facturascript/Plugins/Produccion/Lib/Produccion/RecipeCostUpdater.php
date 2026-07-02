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

use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\Produccion\Model\LineaReceta;
use FacturaScripts\Plugins\Produccion\Model\Receta;

/**
 * Updates recipe costs and produced product costs when an ingredient variant cost changes.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class RecipeCostUpdater
{
    const POLICY_ALL = 2;

    const POLICY_INGREDIENTS = 1;

    const POLICY_NONE = 0;

    /**
     * Update recipe costs for all recipes that use the given reference as ingredient.
     * Depending on the configured policy, also updates the produced product variant costs.
     *
     * @param string $referencia
     * @return void
     */
    public static function update(string $referencia): void
    {
        $policy = (int)Tools::settings('production', 'costupdatepolicy', self::POLICY_NONE);
        if ($policy === self::POLICY_NONE) {
            return;
        }

        foreach (self::getAffectedRecipes($referencia) as $idreceta) {
            $recipe = new Receta();
            if (false === $recipe->load($idreceta)) {
                continue;
            }

            // Saving the recipe triggers test() → getTotalCost() which recalculates recipe.coste
            $recipe->save();

            if ($policy === self::POLICY_ALL) {
                self::updateProducedCosts($recipe);
            }
        }
    }

    /**
     * Returns the IDs of all recipes that have the given reference as an ingredient line.
     *
     * @param string $referencia
     * @return array
     */
    private static function getAffectedRecipes(string $referencia): array
    {
        $ids = [];
        $where = [Where::eq('referencia', $referencia)];
        foreach (LineaReceta::all($where) as $line) {
            $ids[$line->idreceta] = true;
        }
        return array_keys($ids);
    }

    /**
     * Updates the cost of produced product variants that share the recipe cost.
     * Cascades naturally to chained recipes via Variante::onUpdate().
     *
     * @param Receta $recipe
     * @return void
     */
    private static function updateProducedCosts(Receta $recipe): void
    {
        $products = $recipe->getProductShareCost();
        if (empty($products)) {
            return;
        }

        $cost = $recipe->coste / count($products);
        $variant = new Variante();
        foreach ($products as $product) {
            if (false === $variant->loadWhere([Where::eq('referencia', $product->referencia)])) {
                continue;
            }

            $newCost = empty($product->cantidad) ? $cost : round($cost / $product->cantidad, 4);
            if ($variant->coste === $newCost) {
                continue;
            }

            $variant->coste = $newCost;
            $variant->save();
        }
    }
}
