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
use FacturaScripts\Core\Base\ExtensionsTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\MovimientoStock;
use FacturaScripts\Dinamic\Model\Stock;
use FacturaScripts\Plugins\Produccion\Model\Join\LineaProduccion;
use FacturaScripts\Plugins\Produccion\Model\NumSerieCounter;
use FacturaScripts\Plugins\Produccion\Model\OrdenNumSerie;
use FacturaScripts\Plugins\Produccion\Model\OrdenProduccion;
use FacturaScripts\Plugins\Produccion\Model\OrdenProducto;
use FacturaScripts\Plugins\Produccion\Model\Receta;
use FacturaScripts\Plugins\Produccion\Model\RecetaHistorial;

/**
 * Class to manage order production
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class OrderManager
{
    use ExtensionsTrait;

    /**
     * Detail or lines of the Order to be made.
     * Recipe ingredients.
     *
     * @var LineaProduccion[]
     */
    protected array $lines = [];

    /**
     * Order Production to be made.
     *
     * @var OrdenProduccion
     */
    protected OrdenProduccion $orderProduction;

    /**
     * Detail or lines of products to be produce.
     *
     * @var OrdenProducto[]
     */
    protected array $products = [];

    /**
     * Recipe linked to the production order.
     *
     * @var Receta
     */
    protected Receta $recipe;

    /**
     * Constructor and class initializer.
     */
    public function __construct()
    {
        $this->orderProduction = new OrdenProduccion();
        $this->recipe = new Receta();
    }

    /**
     * Main procedure for making an order of production.
     * <b>When checking the stock of the ingredients, if there is no stock
     * and the ingredient belongs to another recipe, an automatic production
     * of the children recipe is performed.</b>.
     * The transaction is handled first of all because it can subtract stock
     * in the check.
     *
     * @param int $code
     * @return bool
     */
    public function produce(int $code): bool
    {
        $dataBase = new DataBase();
        $newTransaction = false === $dataBase->inTransaction() && $dataBase->beginTransaction();
        try {
            if (false === $this->prepareData($code) || false === $this->checkStock()) {
                return false;
            }
            if ($this->removeStock()
                && $this->addStock()
                && $this->updateRecipe()
                && $this->addHistorical()
                && $this->updateOrder()
            ) {
                if ($newTransaction) {
                    $dataBase->commit();
                }
                return true;
            }
        } catch (Exception $exc) {
            Tools::log()->error($exc->getMessage());
        } finally {
            if ($newTransaction && $dataBase->inTransaction()) {
                $dataBase->rollback();
            }
        }

        return false;
    }

    /**
     * Add the historical record of the production of the recipe.
     *
     * @return bool
     */
    protected function addHistorical(): bool
    {
        $historical = new RecetaHistorial();
        $historical->cantidad = 1;
        $historical->docmodel = $this->orderProduction->modelClassName();
        $historical->idreceta = $this->orderProduction->idreceta;
        return $historical->save();
    }

    /**
     * Records the movement for the put into stock of the manufactured material.
     *
     * @param string $reference
     * @param int|float $units
     * @return bool
     */
    private function addProductMovement(string $reference, $units): bool
    {
        $movement = new MovimientoStock();
        $movement->cantidad = $units;
        $movement->codalmacen = $this->recipe->codalmacen2;
        $movement->docid = $this->orderProduction->id;
        $movement->docmodel = $this->orderProduction->modelClassName();
        $movement->documento = Tools::lang()->trans('production') . ' ' . $this->orderProduction->id;
        $movement->referencia = $reference;
        $movement->idproducto = $this->recipe->getVariant($reference)->idproducto;
        return $movement->save();
    }

    /**
     * Add serial numbers to the manufactured products.
     *
     * @param OrdenProducto $productLine
     * @return bool
     */
    private function addProductSerialNumbers(OrdenProducto $productLine): bool
    {
        if (empty($productLine->numserietype)) {
            return true;
        }
        for ($qty = 1; $qty <= $productLine->cantidad; $qty++) {
            $serialNumber = new OrdenNumSerie();
            $serialNumber->idorden = $this->orderProduction->id;
            $serialNumber->idline = $productLine->id;
            $serialNumber->reference = $productLine->referencia;
            $serialNumber->numserie = NumSerieCounter::getNumSerie($productLine->numserietype, $productLine->referencia);
            if (false === $serialNumber->save()) {
                Tools::log()->error('error-saving-serial-number', ['%reference%' => $productLine->referencia]);
                return false;
            }
        }
        return true;
    }

    /**
     * Add the manufactured material to the stock.
     *
     * @param string $reference
     * @param int|float $units
     * @return bool
     */
    private function addProductStock(string $reference, $units): bool
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
        $stock->cantidad += $units;
        return $stock->save();
    }

    /**
     * Register the movement for the exit of the raw material stock.
     *
     * @param LineaProduccion $orderLine
     * @return bool
     */
    private function addRawMaterialMovement(LineaProduccion $orderLine): bool
    {
        $movement = new MovimientoStock();
        $movement->cantidad = ($orderLine->cantidad * -1);
        $movement->codalmacen = $orderLine->codalmacen;
        $movement->docid = $orderLine->idorden;
        $movement->documento =  Tools::lang()->trans('production') . ' ' . $orderLine->idorden;
        $movement->referencia = $orderLine->referencia;
        $movement->idproducto = $orderLine->idproducto;
        $movement->docmodel = $this->orderProduction->modelClassName();
        if (false === $movement->save()) {
            return false;
        }
        return true;
    }

    /**
     * Add the manufactured products to stock.
     * Add stock movement for product (StockAvanzado Plugin)
     *
     * @return bool
     */
    protected function addStock(): bool
    {
        foreach ($this->products as $productLine) {
            if (false === $this->addProductStock($productLine->referencia, $productLine->cantidad)) {
                Tools::log()->warning('error-saving-stock');
                return false;
            }

            if (false === $this->addProductMovement($productLine->referencia, $productLine->cantidad)) {
                Tools::log()->warning('error-saving-stock');
                return false;
            }

            if (false === $this->addProductSerialNumbers($productLine)) {
                Tools::log()->warning('error-saving-serial-numbers');
                return false;
            }

            if (false === $this->pipeFalse('addStock', $productLine->id)) {
                Tools::log()->warning('error-saving-stock');
                return false;
            }
        }

        return true;
    }

    /**
     * Check if there is enough stock to make the recipe.
     * If there is not enough stock, check if the ingredient is a manufactured
     * product, and we manufacture the quantity necessary to have stock.
     *
     * @return bool
     */
    private function checkStock(): bool
    {
        foreach ($this->lines as $orderLine) {
            if ($orderLine->nostock || $orderLine->ventasinstock) {
                continue;
            }

            $stock = ((int)$orderLine->usarstock === OrdenProduccion::STOCK_REAL)
                ? $orderLine->stock
                : $orderLine->disponible;

            if ($stock < $orderLine->cantidad) {
                $producedIn = ProductionTools::producedIn($orderLine->referencia);
                if (isset($producedIn['idreceta'])) {
                    $neededQuantity = $orderLine->cantidad - $stock;
                    $recipeQuantity = $producedIn['cantidad'] > 0
                        ? ceil($neededQuantity/$producedIn['cantidad'])
                        : $neededQuantity;

                    // Produce the necessary amount of the ingredient
                    $manager = new RecipeManager();
                    if ($manager->produce($producedIn['idreceta'], $recipeQuantity)) {
                        continue;
                    }
                }
                // There are not enough stocks to create the order.
                Tools::log()->notice('not-enough-stock', ['%reference%' => $orderLine->referencia]);
                return false;
            }

            if (false === $this->pipeFalse('checkStock')) {
                Tools::log()->notice('not-enough-stock', ['%reference%' => $orderLine->referencia]);
                return false;
            }
        }
        return true;
    }

    /**
     * Load all data needed for process the order.
     *
     * @param int $code
     * @return bool
     */
    protected function prepareData(int $code): bool
    {
        if (false === $this->orderProduction->load($code)) {
            return false;
        }

        $status = [OrdenProduccion::STATUS_PENDING, OrdenProduccion::STATUS_STARTED];
        if (false === in_array($this->orderProduction->estado, $status)) {
            Tools::log()->warning('error-order-status');
            return false;
        }

        $this->recipe = $this->orderProduction->getRecipe();
        $this->products = $this->orderProduction->getProducts();
        $this->lines = $this->orderProduction->getLines();

        if (false === $this->pipeFalse('prepareData', $code)) {
            return false;
        }
        return count($this->lines) > 0;
    }

    /**
     * Subtract raw material from stock.
     *
     * @param string $warehouse
     * @param string $reference
     * @param int|float $units
     * @return bool
     */
    private function removeRawMaterialStock(string $warehouse, string $reference, $units): bool
    {
        $stock = new Stock();
        $where = [
            new DataBaseWhere('codalmacen', $warehouse),
            new DataBaseWhere('referencia', $reference)
        ];
        $stock->loadWhere($where);
        $stock->cantidad -= $units;
        return $stock->save();
    }

    /**
     * Remove the raw products from stock.
     *
     * @return bool
     */
    protected function removeStock(): bool
    {
        foreach ($this->lines as $orderLine) {
            if ($orderLine->nostock) {
                continue;
            }

            if (false === $this->removeRawMaterialStock($orderLine->codalmacen, $orderLine->referencia, $orderLine->cantidad)) {
                Tools::log()->warning('error-saving-stock');
                return false;
            }

            if (false === $this->addRawMaterialMovement($orderLine)) {
                Tools::log()->warning('error-saving-stock');
                return false;
            }

            if (false === $this->pipeFalse('removeStock', $orderLine->idlinea)) {
                Tools::log()->warning('error-saving-stock');
                return false;
            }
        }

        return true;
    }

    /**
     * Update the order production status.
     * If there are serial numbers, the status is set to verifying, otherwise set to finished.
     *
     * @return bool
     */
    protected function updateOrder(): bool
    {
        $numserie = new OrdenNumSerie();
        $where = [ new DataBaseWhere('idorden', $this->orderProduction->id) ];
        $this->orderProduction->estado = $numserie->loadWhere($where)
            ? OrdenProduccion::STATUS_VERIFYING
            : OrdenProduccion::STATUS_FINISHED;
        if ($this->orderProduction->save()) {
            return true;
        }

        Tools::log()->warning('error-saving-order');
        return false;
    }

    /**
     * Updates the manufacturing date.
     *
     * @return bool
     */
    protected function updateRecipe(): bool
    {
        $this->recipe->ultimaproduccion = date(Tools::DATETIME_STYLE);
        if ($this->recipe->save()) {
            return true;
        }

        Tools::log()->warning('error-saving-recipe');
        return false;
    }
}
