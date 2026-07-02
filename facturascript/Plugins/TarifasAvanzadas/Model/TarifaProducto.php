<?php
/**
 * Copyright (C) 2020-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TarifasAvanzadas\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base;
use FacturaScripts\Dinamic\Lib\ProductType;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * Description of TarifaProducto
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class TarifaProducto extends Base\ModelClass
{

    use Base\ModelTrait;

    /** @var string */
    public $codtarifa;

    /** @var float */
    public $pvp;

    /** @var int */
    public $id;

    /** @var string */
    public $referencia;

    public function clear()
    {
        parent::clear();
        $this->pvp = 0.0;
    }

    public function getVariant(): Variante
    {
        $variant = new Variante();
        $where = [new DataBaseWhere('referencia', $this->referencia)];
        $variant->loadFromCode('', $where);
        return $variant;
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public function setPriceWithTax(float $price)
    {
        $variant = $this->getVariant();
        $product = $variant->getProducto();

        if ($product->tipo !== ProductType::SECOND_HAND) {
            $newPrice = (100 * $price) / (100 + $product->getTax()->iva);
            $this->pvp = round($newPrice, Producto::ROUND_DECIMALS);
            return;
        }

        $price -= $product->coste;
        $newPrice = $product->coste + (100 * $price) / (100 + $product->getTax()->iva);
        $this->pvp = round($newPrice, Producto::ROUND_DECIMALS);
    }

    public static function tableName(): string
    {
        return 'articulostarifas';
    }
}
