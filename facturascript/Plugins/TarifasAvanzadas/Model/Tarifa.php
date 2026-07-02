<?php
/**
 * Copyright (C) 2020-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TarifasAvanzadas\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Tarifa as ParentModel;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\TarifaFamilia as DinTarifaFamilia;
use FacturaScripts\Dinamic\Model\TarifaProducto as DinTarifaProducto;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * Description of Tarifa
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class Tarifa extends ParentModel
{
    /**
     * @var int
     */
    public $decimales;

    /**
     * @var TarifaFamilia[]
     */
    private $families = [];

    public function apply(float $cost, float $price)
    {
        $price = parent::apply($cost, $price);
        return round($price, $this->decimales);
    }

    /**
     * @param Variante $variant
     * @param Producto $product
     *
     * @return float
     */
    public function applyTo($variant, $product)
    {
        // find TarifaProducto for this reference
        $tarifaProd = new DinTarifaProducto();
        $where = [
            new DataBaseWhere('codtarifa', $this->codtarifa),
            new DataBaseWhere('referencia', $variant->referencia)
        ];
        if ($tarifaProd->loadFromCode('', $where)) {
            return $tarifaProd->pvp;
        }

        // find TarifaFamilia for this family
        if (!empty($product->codfamilia) && $this->loadFamily($product->codfamilia)) {
            return $this->families[$product->codfamilia]->apply($variant->coste, $variant->precio);
        }

        return parent::applyTo($variant, $product);
    }

    public function clear()
    {
        parent::clear();
        $this->decimales = 5;
    }

    public function explain(): string
    {
        return $this->aplicar === self::APPLY_COST ?
            Tools::lang()->trans('formula-cost-price-alt', ['%x%' => $this->valorx, '%y%' => $this->valory]) :
            Tools::lang()->trans('formula-sale-price-alt', ['%x%' => $this->valorx, '%y%' => $this->valory]);
    }

    /**
     * @param Variante $variant
     * @param Producto $product
     *
     * @return string
     */
    public function explainTo($variant, $product): string
    {
        // find TarifaProducto for this reference
        $tarifaProd = new DinTarifaProducto();
        $where = [
            new DataBaseWhere('codtarifa', $this->codtarifa),
            new DataBaseWhere('referencia', $variant->referencia)
        ];
        if ($tarifaProd->loadFromCode('', $where)) {
            return Tools::lang()->trans('fixed-price');
        }

        // find TarifaFamilia for this family
        if (!empty($product->codfamilia) && $this->loadFamily($product->codfamilia)) {
            return $this->families[$product->codfamilia]->explain();
        }

        return $this->explain();
    }

    /**
     * @param string $codfamilia
     *
     * @return bool
     */
    private function loadFamily($codfamilia): bool
    {
        if (isset($this->families[$codfamilia])) {
            return null !== $this->families[$codfamilia]->primaryColumnValue();
        }

        $this->families[$codfamilia] = new DinTarifaFamilia();
        $where = [
            new DataBaseWhere('codfamilia', $codfamilia),
            new DataBaseWhere('codtarifa', $this->codtarifa)
        ];
        return $this->families[$codfamilia]->loadFromCode('', $where);
    }
}
