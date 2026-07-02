<?php
/**
 * Copyright (C) 2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Ticketbai\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ListFacturaCliente
{
    public function createViews(): Closure
    {
        return function () {
            $this->addFilterSelectWhere('ListFacturaCliente', 'ticketbai', [
                ['label' => Tools::lang()->trans('ticketbai'), 'where' => []],
                ['label' => Tools::lang()->trans('signed'), 'where' => [new DataBaseWhere('tbaicodbar', null, 'IS NOT')]],
                ['label' => Tools::lang()->trans('not-signed'), 'where' => [new DataBaseWhere('tbaicodbar', null)]]
            ]);
        };
    }
}
