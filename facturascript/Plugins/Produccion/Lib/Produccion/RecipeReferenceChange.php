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

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\LineaReceta;
use FacturaScripts\Dinamic\Model\Receta;
use FacturaScripts\Dinamic\Model\RecetaProducto;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * Class to manage the change of one reference into recipe list
 *
 * @author Jose Antonio Cuello <yopli2000@gmail.com>
 */
class RecipeReferenceChange
{
    protected int $applyTo;
    protected BaseController $controller;
    protected Variante $refsource;
    protected Variante $reftarget;
    protected int $replaceIn;

    /**
     * Class constructor.
     * Create variants to be used in the reference change.
     */
    public function __construct()
    {
        $this->refsource = new Variante();
        $this->reftarget = new Variante();
    }

    /**
     * Execute the reference change.
     *
     * @param array $data Data from the form
     * @return void
     */
    public function exec(array $data): void
    {
        if (false === $this->checkData($data)) {
            return;
        }

        $recipeList = $this->getRecipes($data['code']);
        if (empty($recipeList)) {
            return;
        }

        $database = new DataBase();
        $database->beginTransaction();
        try {
            foreach ($recipeList as $recipe) {
                if (in_array($this->replaceIn, [1,3]) && false === $this->replaceIngredient($recipe)) {
                    return;
                }
                if (in_array($this->replaceIn, [2,3]) && false === $this->replaceProduced($recipe)) {
                    return;
                }
            }
            $database->commit();
            Tools::log()->notice('recipe-references-changed');
        } finally {
            if ($database->inTransaction()) {
                $database->rollback();
            }
        }
    }

    /**
     * Check the data from the form.
     * Load the variants data of the source and target references.
     *
     * @param array $data Data from the form
     * @return bool
     */
    protected function checkData(array $data): bool
    {
        $confirm = (bool) $data['confirm'] ?? false;
        $this->applyTo = (int) $data['applyto'] ?? 0;
        $this->replaceIn = (int) $data['replacein'] ?? 0;
        if (!$confirm
            || empty($this->applyTo)
            || empty($this->replaceIn)
            || empty($data['refsource'])
            || empty($data['reftarget'])
        ) {
            Tools::log()->warning('data-form-error');
            return false;
        }

        if (empty($data['code']) && $this->applyTo === 1) {
            Tools::log()->warning('no-recipes-selected');
            return false;
        }

        $where1 = [ new DataBaseWhere('referencia', $data['refsource']) ];
        if (false === $this->refsource->loadWhere($where1)) {
            Tools::log()->warning('empty-ref-or-missing');
            return false;
        }

        $where2 = [ new DataBaseWhere('referencia', $data['reftarget']) ];
        if (false === $this->reftarget->loadWhere($where2)) {
            Tools::log()->warning('empty-ref-or-missing');
            return false;
        }
        return true;
    }

    /**
     * Replace the reference in the recipe ingredients.
     *
     * @param Receta $recipe
     * @return bool
     */
    protected function replaceIngredient(Receta $recipe): bool
    {
        $where = [
            new DataBaseWhere('idreceta', $recipe->idreceta),
            new DataBaseWhere('referencia', $this->refsource->referencia),
        ];
        foreach (LineaReceta::all($where) as $ingredient) {
            $ingredient->referencia = $this->reftarget->referencia;
            if (false === $ingredient->save()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Replace the reference in the recipe produced.
     *
     * @param Receta $recipe
     * @return bool
     */
    protected function replaceProduced(Receta $recipe): bool
    {
        $where = [
            new DataBaseWhere('idreceta', $recipe->idreceta),
            new DataBaseWhere('referencia', $this->refsource->referencia),
        ];
        foreach (RecetaProducto::all($where) as $product) {
            $product->referencia = $this->reftarget->referencia;
            if (false === $product->save()) {
                return false;
            }
        }

        // FIXME: Remove where remove reference field from recipe.
        if ($recipe->referencia === $this->refsource->referencia) {
            $recipe->referencia = $this->reftarget->referencia;
            if (false === $recipe->save()) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array|string $codes
     * @return Receta[]
     */
    private function getRecipes($codes): array
    {
        $ids = is_array($codes)
            ? implode(',', $codes)
            : $codes;

        $where = $this->applyTo === 1
            ? [ new DataBaseWhere('idreceta', $ids, 'IN') ]
            : [];
        return Receta::all($where);
    }
}
