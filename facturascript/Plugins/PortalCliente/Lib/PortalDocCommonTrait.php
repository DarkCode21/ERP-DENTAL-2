<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Lib;

use FacturaScripts\Dinamic\Lib\ProductType;
use FacturaScripts\Dinamic\Lib\RegimenIVA;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait PortalDocCommonTrait
{
    public function getLineSubtotal($line, $model): float
    {
        $product = $line->getProducto();

        // si el producto existe
        // y el producto es de segunda mano
        // y la empresa usa el régimen de bienes usados
        if ($product->exists()
            && $model->getCompany()->regimeniva === RegimenIVA::TAX_SYSTEM_USED_GOODS
            && $product->tipo !== ProductType::SECOND_HAND) {
            $profit = $line->pvpunitario - $line->coste;
            $tax = $profit * ($line->iva + $line->recargo - $line->irpf) / 100;
            return ($line->coste + $profit + $tax) * $line->cantidad;
        }

        // calculamos el subtotal basándonos en el precio de venta
        return $line->pvptotal * (100 + $line->iva + $line->recargo - $line->irpf) / 100;
    }

    public function getLineTotalDiscount($line, $model): float
    {
        if ($line->dtopor <= 0) {
            return 0.0;
        }

        return $line->pvpsindto - $line->pvptotal;
    }

    public function getLineTotalTax($line, $model): float
    {
        $product = $line->getProducto();

        // si el producto existe
        // y el producto es de segunda mano
        // y la empresa usa el régimen de bienes usados
        if ($product->exists()
            && $model->getCompany()->regimeniva === RegimenIVA::TAX_SYSTEM_USED_GOODS
            && $product->tipo !== ProductType::SECOND_HAND) {
            // calculamos el iva en base, a la diferencia entre coste y precio
            $diff = $line->pvptotal - ($line->coste * $line->cantidad);
            return $diff * $line->iva / 100;
        }

        // calculamos el iva basándonos en el precio de venta
        return $line->pvptotal * $line->iva / 100;
    }
}