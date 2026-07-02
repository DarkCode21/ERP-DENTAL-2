<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Worker;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\WorkEvent;
use FacturaScripts\Core\Template\WorkerClass;
use FacturaScripts\Dinamic\Model\Producto;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalProductPriceWorker extends WorkerClass
{
    public function run(WorkEvent $event): bool
    {
        // obtenemos los productos sin precio mínimo y máximo
        $productModel = new Producto();
        $where = [
            new DataBaseWhere('pc_price_min', null),
            new DataBaseWhere('pc_price_max', null, 'IS', 'OR'),
        ];
        foreach ($productModel->all($where, [], 0, 0) as $product) {
            $product->save();
        }

        return $this->done();
    }
}