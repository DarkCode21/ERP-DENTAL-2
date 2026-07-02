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
use FacturaScripts\Dinamic\Model\MovimientoStock;
use FacturaScripts\Dinamic\Model\RecetaProducto;
use FacturaScripts\Plugins\Produccion\Model\Join\LineaReceta;
use FacturaScripts\Plugins\Produccion\Model\OrdenProduccion;
use FacturaScripts\Plugins\Produccion\Model\Join\ProductoProduccion;
use FacturaScripts\Plugins\Produccion\Model\Join\LineaProduccion;
use FacturaScripts\Plugins\Produccion\Model\Receta;
use FacturaScripts\Plugins\Produccion\Model\RecetaHistorial;
use FacturaScripts\Plugins\StockAvanzado\Contract\StockMovementModInterface;

/**
 * Add rebuild stock's movements extension.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class StockMovementManager implements StockMovementModInterface
{
    /**
     * Add rebuild stock movements' extension.
     *   - Process historical recipes movements. (RecetaHistorial)
     *   - Process raw materials movements (Remove quantities). (LineaProduccion)
     *   - Process products movements (Add quantities). (ProductoProduccion)
     *
     * @param ?int $idproducto
     */
    public function run(?int $idproducto = null): void
    {
        $this->processRecipes($idproducto);
        $this->processRawMaterials($idproducto);
        $this->processProducts($idproducto);
    }

    /**
     * Create the movements for the raw materials of the orders.
     *
     * @param ?int $idproduct
     * @return void
     */
    protected function processRawMaterials(?int $idproduct = null): void
    {
        $where = [
            Where::eq('COALESCE(productos.nostock, false)', false),
            Where::eq('ordenes.estado', OrdenProduccion::STATUS_FINISHED),
        ];
        if (false === empty($idproduct)) {
            $where[] = Where::eq('productos.idproducto', $idproduct);
        }

        $orderBy = [
            'ordenes.fecha' => 'ASC',
            'ordenes.hora' => 'ASC',
        ];

        $lineaProduccion = new LineaProduccion();
        foreach ($lineaProduccion->all($where, $orderBy) as $material) {
            $this->addMovementFromOrder($material, -1);
        }
    }

    /**
     * Create movements from historical recipes.
     *
     * @param ?int $idproduct
     * @return void
     */
    protected function processRecipes(?int $idproduct = null): void
    {
        $where = [ Where::eq('docmodel', 'Receta') ];
        $orderBy = [
            'idreceta' => 'ASC',
            'fecha' => 'ASC',
            'hora' => 'ASC',
        ];
        $recipe = new Receta();
        $lines = [];
        $products = [];
        foreach (RecetaHistorial::all($where, $orderBy) as $row) {
            if ($recipe->idreceta !== $row->idreceta) {
                if (false === $recipe->load($row->idreceta)) {
                    continue;
                }
                $lines = $recipe->getLines();
                $products = $recipe->getProducts();
            }

            foreach ($lines as $recipeLine) {
                if ($recipeLine->nostock
                    || (false === empty($idproduct) && $recipeLine->idproducto !== $idproduct))
                {
                    continue;
                }

                $this->addMovementRecipeLine($row, $recipeLine);
            }

            foreach ($products as $recipeProduct) {
                if (false === empty($idproduct)
                    && $recipeProduct->getVariant()->idproducto !== $idproduct
                ) {
                    continue;
                }

                $this->addMovementRecipeProduct($row, $recipe, $recipeProduct);
            }
        }
    }

    /**
     * Create the movements for the produced products of the orders.
     *
     * @param ?int $idproduct
     * @return void
     */
    protected function processProducts(?int $idproduct = null): void
    {
        $where = [
            Where::eq('COALESCE(productos.nostock, false)', false),
            Where::eq('ordenes.estado', OrdenProduccion::STATUS_FINISHED),
        ];
        if (false === empty($idproduct)) {
            $where[] = Where::eq('productos.idproducto', $idproduct);
        }

        $orderBy = [
            'ordenes.fecha' => 'ASC',
            'ordenes.hora' => 'ASC',
        ];

        $productoProduccion = new ProductoProduccion();
        foreach ($productoProduccion->all($where, $orderBy) as $product) {
            $this->addMovementFromOrder($product, 1);
        }
    }

    /**
     * Add movement for order.
     *
     * @param ProductoProduccion|LineaProduccion $data
     * @param int $sign
     * @return void
     */
    private function addMovementFromOrder(ProductoProduccion|LineaProduccion $data, int $sign): void
    {
        $movement = new MovimientoStock();
        $movement->fecha = $data->fecha;
        $movement->hora = $data->hora;
        $movement->docid = $data->idorden;
        $movement->documento =  Tools::lang()->trans('production') . ' ' . $data->idorden;
        $movement->docmodel = 'OrdenProduccion';
        $movement->codalmacen = $data->codalmacen;
        $movement->idproducto = $data->idproducto;
        $movement->referencia = $data->referencia;
        $movement->cantidad = $data->cantidad * $sign;
        $movement->save();
    }

    /**
     * Add movement for raw material of the recipe.
     *
     * @param RecetaHistorial $historical
     * @param LineaReceta $line
     * @return void
     */
    private function addMovementRecipeLine(RecetaHistorial $historical, LineaReceta $line): void
    {
        $movement = new MovimientoStock();
        $movement->fecha = $historical->fecha;
        $movement->hora = $historical->hora;
        $movement->docid = $historical->idreceta;
        $movement->docmodel = 'Receta';
        $movement->documento =  Tools::lang()->trans('recipe') . ' ' . $line->codreceta;
        $movement->codalmacen = $line->codalmacen;
        $movement->idproducto = $line->idproducto;
        $movement->referencia = $line->referencia;
        $movement->cantidad = $line->cantidad * $historical->cantidad * -1;
        $movement->save();
    }

    /**
     * Add movement for produced product of the recipe.
     *
     * @param RecetaHistorial $historical
     * @param Receta $recipe
     * @param RecetaProducto $product
     * @return void
     */
    private function addMovementRecipeProduct(RecetaHistorial $historical, Receta $recipe, RecetaProducto $product): void
    {
        $reference = $product->referencia;
        $movement = new MovimientoStock();
        $movement->cantidad = $product->cantidad * $historical->cantidad;
        $movement->codalmacen = $recipe->codalmacen2;
        $movement->docid = $recipe->idreceta;
        $movement->docmodel = $recipe->modelClassName();
        $movement->documento =  Tools::lang()->trans('recipe') . ' ' . $recipe->codreceta;
        $movement->referencia = $reference;
        $movement->idproducto = $recipe->getVariant($reference)->idproducto;
        $movement->save();
    }
}
