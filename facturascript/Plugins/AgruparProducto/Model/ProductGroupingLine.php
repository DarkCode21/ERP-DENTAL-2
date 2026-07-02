<?php
/**
 * This file is part of AgruparProducto plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * AgruparProducto Copyright (C) 2022-2022 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\AgruparProducto\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\ProductGrouping;

/**
 * Product grouping detail for product
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class ProductGroupingLine extends ModelClass
{

    use ModelTrait;

    /**
     * Barcode for product group
     *
     * @var string
     */
    public $barcode;

    /**
     * Indicate if it is the default grouping
     *
     * @var bool
     */
    public $bydefault;

    /**
     * Discount for product group
     *
     * @var double
     */
    public $discount;

    /**
     * Primary key.
     *
     * @var int
     */
    public $id;

    /**
     * Link to the product model.
     *
     * @var int
     */
    public $idproduct;

    /**
     * Link to the product grouping model.
     *
     * @var int
     */
    public $idgroup;

    /**
     * Quantity for product group
     *
     * @var double
     */
    public $quantity;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->quantity = 1.00;
        $this->discount = 0.00;
        $this->bydefault = false;
    }

    /**
     * Get the products for a grouping.
     *
     * @return ProductGroupingLine[]
     */
    public function getProductGrouping()
    {
        $GroupingLine = new ProductGroupingLine();
        $where = [new DataBaseWhere('idproduct', $this->idproduct)];
        return $GroupingLine->all($where, ['id' => 'ASC'], 0, 0);
    }

    /**
     * This function is called when creating the model table. Returns the SQL
     * that will be executed after the creation of the table. Useful to insert values
     * default.
     *
     * @return string
     */
    public function install(): string
    {
        new Producto();
        new ProductGrouping();
        parent::install();

        return '';
    }

    /**
     * Returns the name of the column that is the model's primary key.
     *
     * @return string
     */
    public static function primaryColumn(): string
    {
        return 'id';
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'agruparproducto_grouplines';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        if ($this->quantity <= 0) {
            $this->toolBox()->i18nLog()->warning('quantity-required');
            return false;
        }

        return parent::test();
    }

    /**
     * Insert the model data in the database.
     *
     * @param array $values
     *
     * @return bool
     */
    protected function saveInsert(array $values = array()): bool
    {
        if (parent::saveInsert($values)) {
            return $this->updateByDefault();
        }
        return false;
    }

    /**
     * Update the model data in the database.
     *
     * @param array $values
     *
     * @return bool
     */
    protected function saveUpdate(array $values = array()): bool
    {
        if (parent::saveUpdate($values)) {
            return $this->updateByDefault();
        }
        return false;
    }

    /**
     * Synchronize the default record. There can only be one for each product.
     *
     * @return bool
     */
    private function updateByDefault(): bool
    {
        if (!$this->bydefault) {
            return true;
        }

        $sql = 'UPDATE ' . static::tableName() . ' SET bydefault = false '
            .  ' WHERE idproduct = ' . $this->idproduct
            .    ' AND ' . static::primaryColumn() . ' <> ' . self::$dataBase->var2str($this->primaryColumnValue());

        return self::$dataBase->exec($sql);
    }
}
