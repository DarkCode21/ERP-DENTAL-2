<?php
/**
 * Copyright (C) 2022-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Facturae\Extension\Model;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Facturae\Model\XmlFacturaE;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class FacturaCliente
{
    public function deleteBefore(): Closure
    {
        return function () {
            $facturae = new XmlFacturaE();
            $where = [new DataBaseWhere('idfactura', $this->idfactura)];
            if ($facturae->loadFromCode('', $where)) {
                Tools::log()->warning('facturae-delete-before');
                return false;
            }
        };
    }
}
