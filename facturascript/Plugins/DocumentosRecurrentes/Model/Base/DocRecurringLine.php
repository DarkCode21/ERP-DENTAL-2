<?php
/**
 * This file is part of DocumentosRecurrentes plugin for FacturaScripts.
 * FacturaScripts         Copyright (C) 2015-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DocumentosRecurrentes  Copyright (C) 2020-2021 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DocumentosRecurrentes\Model\Base;

use FacturaScripts\Core\Model\Base\ModelClass;

/**
 * Model template for DocRecurring Purchase and Sale line
 *
 * @author Carlos Garcia Gomez  <carlos@facturascripts.com>
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
abstract class DocRecurringLine extends ModelClass
{

    /**
     * Percentage of discount.
     * Optional, if empty the product discount is used.
     *
     * @var float
     */
    public $discount;

    /**
     * Primary Key.
     *
     * @var int
     */
    public $id;

    /**
     * Link to document recurring model.
     *
     * @var int
     */
    public $iddoc;

    /**
     * Link to product model.
     *
     * @var int
     */
    public $idproduct;

    /**
     * Description for product.
     * Optional, if empty the product description is used.
     *
     * @var string
     */
    public $name;

    /**
     * Unit price of the product.
     * Optional, if empty the product price is used.
     *
     * @var float
     */
    public $price;

    /**
     * Quantity of the product.
     *
     * @var float
     */
    public $quantity;

    /**
     * Link to variant product model.
     *
     * @var string
     */
    public $reference;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->quantity = 1;
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
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test()
    {
        $this->name = $this->toolBox()->utils()->noHtml($this->name);
        $this->reference = $this->toolBox()->utils()->noHtml($this->reference);

        if (empty($this->reference) && empty($this->name)) {
            $this->toolBox()->i18nLog()->error('reference-description-empty');
            return false;
        }

        return parent::test();
    }
}
