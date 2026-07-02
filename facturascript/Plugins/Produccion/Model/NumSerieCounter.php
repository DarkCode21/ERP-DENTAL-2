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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Familia;
use FacturaScripts\Dinamic\Model\Producto;

/**
 * Class that manages the numserie counters for the products that are manufactured
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class NumSerieCounter extends ModelClass
{
    public const NS_TYPE_NONE = 0;
    public const NS_TYPE_COUNTER = 1;
    public const NS_TYPE_REFERENCE = 2;
    public const NS_TYPE_FAMILY = 3;

    use ModelTrait;

    /**
     * Primary key.
     *
     * @var int
     */
    public $id;

    /**
     * Link with the product.
     *
     * @var int
     */
    public $idproduct;

    /**
     * Link with the family of the product.
     *
     * @var string
     */
    public $idfamily;

    /**
     * Last number used for the counter.
     *
     * @var int
     */
    public $numserie;

    /**
     * Reference code (product reference).
     * READ-ONLY. Loaded only for information purposes.
     *
     * @var string
     */
    public $reference;

    /**
     * Returns the next numserie for the given type and code.
     * Return an empty string if the numserie could not be generated.
     *
     * @param int $numserietype
     * @param string $reference
     * @return string
     */
    public static function getNumSerie(int $numserietype, string $reference): string
    {
        $model = new self();
        switch ($numserietype) {
            case self::NS_TYPE_COUNTER:
                $model->loadWhere([
                    new DataBaseWhere('idproduct', null),
                    new DataBaseWhere('idfamily', null),
                ]);
                ++$model->numserie;
                if ($model->save()) {
                    return $model->numserie;
                }
                break;

            case self::NS_TYPE_REFERENCE:
                $product = self::getProduct($reference);
                if (empty($product->referencia)) {
                    break;
                }

                $where = [
                    new DataBaseWhere('idproduct', $product->idproducto),
                    new DataBaseWhere('idfamily', null),
                ];
                if (false === $model->loadWhere($where)) {
                    $model->idproduct = $product->idproducto;
                }
                ++$model->numserie;
                if ($model->save()) {
                    return empty($product->nsprefix)
                        ? $product->referencia . self::getSeparator() . $model->numserie
                        : $product->nsprefix . self::getSeparator() . $model->numserie;
                }
                break;

            case self::NS_TYPE_FAMILY:
                $product = self::getProduct($reference);
                if (empty($product->referencia)) {
                    break;
                }

                $family = new Familia();
                if (false === $family->load($product->codfamilia)){
                    break;
                }

                $where = [
                    new DataBaseWhere('idproduct', null),
                    new DataBaseWhere('idfamily', $family->codfamilia),
                ];
                if (false === $model->loadWhere($where)) {
                    $model->idfamily = $family->codfamilia;
                }
                ++$model->numserie;
                if ($model->save()) {
                    return empty($family->nsprefix)
                        ? $family->codfamilia . self::getSeparator() . $model->numserie
                        : $family->nsprefix . self::getSeparator() . $model->numserie;
                }
                break;
        }
        return '';
    }

    /**
     * Returns the separator used in the numserie.
     *
     * @return string
     */
    public static function getSeparator(): string
    {
        return Tools::settings('production', 'numserieseparator', '');
    }

    /**
     * Reset the values of all model properties.
     */
    public function clear(): void
    {
        parent::clear();
        $this->numserie = 0;
        $this->reference = '';
    }

    /**
     * Loads the model from an array of data.
     *
     * @param array $data
     * @param array $exclude
     * @return void
     */
    public function loadFromData(array $data = [], array $exclude = []): void
    {
        parent::loadFromData($data, $exclude);
        $this->reference = '';
        if (false === empty($this->idproduct)) {
            $product = new Producto();
            $product->load($this->idproduct);
            $this->reference = $product->referencia;
        }
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
        return 'produccion_numseries';
    }

    /**
     * Returns the url where to see / modify the data.
     *
     * @param string $type
     * @param string $list
     * @return string
     */
    public function url(string $type = 'auto', string $list = 'AdminProduccion?activetab=List'): string
    {
        return parent::url($type, $list);
    }

    /**
     * @param string $reference
     * @return Producto
     */
    private static function getProduct(string $reference): Producto
    {
        $product = new Producto();
        $product->loadWhere(
            [ new DataBaseWhere('referencia', $reference) ]
        );
        return $product;
    }
}
