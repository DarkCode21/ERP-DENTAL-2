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

use FacturaScripts\Core\KernelException;

/**
 * Class to add special product data treatment.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
trait LoadProductDataTrait
{
    /** @var float */
    public float $productcost = 0.00;

    /** @var string */
    public string $productname = '';

    /** @var bool $loadProductData */
    protected static bool $loadProductData = false;

    /**
     * Activate or deactivate the loading of product data.
     *
     * @param bool $newValue
     * @return void
     */
    public static function setLoadProductData(bool $newValue): void
    {
        self::$loadProductData = $newValue;
    }

    /**
     * Set the product data to order ingredient.
     *
     * @return void
     * @throws KernelException
     */
    protected function loadProductData(): void
    {
        $fieldName = isset($this->reference) ? 'reference' : 'referencia';
        $sql = 'SELECT t1.coste, t2.descripcion'
            . ' FROM variantes t1'
            . ' INNER JOIN productos t2 ON t2.idproducto = t1.idproducto'
            . ' WHERE t1.referencia=' . self::$dataBase->var2str($this->{$fieldName});

        $row = self::$dataBase->selectLimit($sql, 1);
        $this->productcost = empty($row) ? 0.00 : $row[0]['coste'];
        $this->productname = empty($row) ? '' : $row[0]['descripcion'];
    }
}
