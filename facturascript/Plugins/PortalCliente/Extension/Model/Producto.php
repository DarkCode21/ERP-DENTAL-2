<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Extension\Model;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Producto
{
    public function test(): Closure
    {
        return function () {
            $this->pc_price_min = $this->getPriceMin();
            $this->pc_price_max = $this->getPriceMax();
        };
    }

    public function getPriceMax(): Closure
    {
        return function () {
            // obtenemos las variantes ordenadas por precio
            $variantModel = new Variante();
            $where = [new DataBaseWhere('idproducto', $this->idproducto)];
            $orderBy = ['precio' => 'ASC'];
            $variants = $variantModel->all($where, $orderBy, 0, 0);

            // obtenemos el precio máximo
            return empty($variants) ? 0 : $variants[count($variants) - 1]->precio;
        };
    }

    public function getPriceMin(): Closure
    {
        return function () {
            // obtenemos las variantes ordenadas por precio
            $variantModel = new Variante();
            $where = [new DataBaseWhere('idproducto', $this->idproducto)];
            $orderBy = ['precio' => 'ASC'];
            $variants = $variantModel->all($where, $orderBy, 0, 0);

            // obtenemos el precio mínimo
            return empty($variants) ? 0 : $variants[0]->precio;
        };
    }
}