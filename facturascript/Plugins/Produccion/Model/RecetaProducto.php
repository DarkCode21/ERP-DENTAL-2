<?php
/**
 * This file is part of the Produccion plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 * All Rights Reserved.
 */
namespace FacturaScripts\Plugins\Produccion\Model;

use Exception;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * Class to indicate additional raw material products.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class RecetaProducto extends ModelClass
{
    public const SHARE_COST_NONE = 0;
    public const SHARE_COST_YES = 1;

    use ModelTrait;

    /**
     * The Amount of raw material required.
     *
     * @var float
     */
    public $cantidad;

    /**
     * Primary key.
     *
     * @var int
     */
    public $id;

    /**
     * Link to recipe model.
     *
     * @var int
     */
    public $idreceta;

    /**
     * Indicates the type of serial number calculation.
     *
     * @var int
     */
    public $numserietype;

    /**
     * Indicates if it is the main produce product.
     *
     * @var bool
     */
    public $principal;

    /**
     * Link to the raw material variant.
     *
     * @var string
     */
    public $referencia;

    /**
     * Indicates if the cost of the raw material is distributed among the products.
     *
     * @var int
     */
    public $repartircoste;

    /**
     * Reset the values of all model properties.
     */
    public function clear(): void
    {
        parent::clear();
        $this->cantidad = 1.00;
        $this->numserietype = NumSerieCounter::NS_TYPE_NONE;
        $this->principal = false;
        $this->repartircoste = self::SHARE_COST_YES;
    }

    /**
     * Get the variant product for the recipe product.
     *
     * @return Variante
     */
    public function getVariant(): Variante
    {
        $variant = new Variante();
        $variant->loadWhereEq('referencia', $this->referencia);
        return $variant;
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
        new Receta();
        return parent::install();
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
     * Stores the model data in the database.
     * Only one record can be marked:
     *   - principal
     *
     * @return bool
     */
    public function save(): bool
    {
        $updateMain = ($this->isDirty('principal') && $this->principal);
        if (false === parent::save()) {
            return false;
        }

        if ($updateMain) {
            self::$dataBase->exec(
                'UPDATE ' . $this->tableName() . ' SET principal = false'
                . ' WHERE idreceta = ' . $this->idreceta . ' AND id != ' . $this->id
            );
        }
        return true;
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'produccion_recetasproductos';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     * @throws Exception
     */
    public function test(): bool
    {
        $this->referencia = Tools::noHtml($this->referencia);
        if (is_null($this->numserietype)) {
            $this->numserietype = NumSerieCounter::NS_TYPE_NONE;
        }

        if (is_null($this->repartircoste)) {
            $this->repartircoste = self::SHARE_COST_NONE;
        }

        return parent::test();
    }
}
