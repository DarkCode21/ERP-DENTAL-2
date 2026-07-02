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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Variante;

trait VariantRelationTrait
{
    /**
     * Link to the variant of the product.
     *
     * @var ?string
     */
    public ?string $referencia;

    /**
     * Gets the product with the indicated reference.
     *
     * @param string $reference
     * @return Producto
     */
    public function getProduct(string $reference = ''): Producto
    {
        if (empty($reference)) {
            $reference = $this->referencia;
        }
        $product = new Producto();
        $where = [new DataBaseWhere('referencia', $reference)];
        $product->loadWhere($where);
        return $product;
    }

    /**
     * Gets the variant with the indicated reference.
     * If the reference is not informed, the variant associated
     * with the recipe is obtained.
     *
     * @param string $reference
     * @return Variante
     */
    public function getVariant(string $reference = ''): Variante
    {
        if (empty($reference)) {
            $reference = $this->referencia;
        }
        $variant = new Variante();
        $where = [new DataBaseWhere('referencia', $reference)];
        $variant->loadWhere($where);
        return $variant;
    }
}
