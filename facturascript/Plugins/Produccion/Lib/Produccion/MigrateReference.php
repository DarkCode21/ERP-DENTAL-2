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
use FacturaScripts\Core\KernelException;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Produccion\Model\NumSerieCounter;
use FacturaScripts\Plugins\Produccion\Model\Receta;
use FacturaScripts\Plugins\Produccion\Model\RecetaProducto;

/**
 * Manages remove reference from recipes.
 * Move the reference from the recipe to the recipe products.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class MigrateReference
{
    /**
     * Link to active database
     *
     * @var DataBase
     */
    private DataBase $database;

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->database = new DataBase();
    }

    /**
     * Remove old version tables and columns definitions.
     */
    public function checkTables(): bool
    {
        $hasReference = false;
        $recipe = new Receta();
        foreach ($recipe->getModelFields() as $field) {
            if ($field['name'] === 'referencia') {
                $hasReference = true;
                break;
            }
        }

        if (false === $hasReference) {
            return -1;
        }

        foreach ($this->database->getConstraints('produccion_recetas') as $constraint) {
            if ($constraint['name'] === 'ca_produccion_recetas_variantes') {
                $this->database->exec('ALTER TABLE produccion_recetas DROP FOREIGN KEY ' . $constraint['name'] . ';');
                break;
            }
        }
        $this->database->exec('ALTER TABLE produccion_recetas MODIFY COLUMN referencia varchar(30);');

        $product = new RecetaProducto();
        foreach ($product->getModelFields() as $field) {
            if ($field['name'] === 'numserietype' || $field['name'] === 'repartircoste') {
                return true;
            }
        }
        return false;
    }

    /**
     * Main process to remove the reference from the recipe.
     * First:
     *   - remove not null to referencia column
     *   - remove foreign key from produccion_recetas_variantes
     *
     * @return int
     */
    public function run(): int
    {
        $check = $this->checkTables();
        if ($check < 1) {
            return $check;
        }

        $recipeWhere = [ new DataBaseWhere('referencia', null, 'IS NOT') ];
        $recipes = Receta::all($recipeWhere);
        $result = count($recipes);
        foreach ($recipes as $recipe) {
            $this->database->beginTransaction();
            try {
                if (false === $this->moveReference($recipe)) {
                    $this->database->rollback();
                    continue;
                }

                $recipe->referencia = null;
                $recipe->cantidad = 0;
                if (false === $recipe->save()) {
                    $this->database->rollback();
                    continue;
                }

                $this->database->commit();
                $result--;
            } catch (Exception $exec) {
                $this->database->rollback();
                Tools::log()->error($exec->getMessage());
            }
        }
        return $result;
    }

    /**
     * Moves the reference from the recipe to the recipe products.
     * Remove the reference from the recipe and set the quantity to 0.
     *
     * @param Receta $recipe
     * @return bool
     * @throws KernelException
     */
    protected function moveReference(Receta $recipe): bool
    {
        $quantity = empty($recipe->cantidad) ? 1 : $recipe->cantidad;
        $sql = 'INSERT INTO ' . RecetaProducto::tableName()
            . ' (cantidad, idreceta, referencia, repartircoste, numserietype)'
            . ' VALUES ('
                . $quantity . ','
                . $recipe->idreceta . ','
                . $this->database->var2str($recipe->referencia) . ','
                . RecetaProducto::SHARE_COST_YES . ','
                . NumSerieCounter::NS_TYPE_NONE
            . ')';
        return $this->database->exec($sql);
    }
}
