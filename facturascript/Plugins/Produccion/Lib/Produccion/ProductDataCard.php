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
use FacturaScripts\Dinamic\Model\Receta;
use FacturaScripts\Dinamic\Model\Stock;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * Class to get product data to show into product modal card.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
trait ProductDataCard
{
    /**
     * Get product data for product modal card.
     *
     * @param Variante $variant
     * @param Receta $recipe
     * @return array
     */
    protected function getProductData(Variante $variant, Receta $recipe): array {
        $product = $variant->getProducto();
        $images = $product->getImages();

        $stock = new Stock();
        $stock->loadWhere([
            Where::eq('referencia', $variant->referencia),
            Where::eq('codalmacen', $recipe->codalmacen),
        ]);

        return [
            'ok' => true,
            'data' => [
                'barcode' => $variant->codbarras,
                'reference' => $variant->referencia,
                'name' => $variant->description(),
                'price' => Tools::money($variant->precio),
                'cost' => Tools::money($variant->coste),
                'manufacturer' => $product->getFabricante()->nombre ?? '',
                'family' => $product->getFamilia()->descripcion ?? '',
                'forsell' => $product->sevende,
                'stock' => $stock->cantidad,
                'available' => $stock->disponible,
                'imageUrl' => empty($images) ? '' : $images[0]->url('download'),
            ],
        ];
    }
}
