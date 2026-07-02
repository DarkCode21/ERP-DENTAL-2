<?php
/**
 * This file is part of PagosMultiples plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * PagosMultiples Copyright (C) 2022-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\PagosMultiples;

use FacturaScripts\Core\Base\InitClass;
use FacturaScripts\Core\Model\ReciboCliente;
use FacturaScripts\Core\Model\ReciboProveedor;
use FacturaScripts\Plugins\PagosMultiples\Model\CustomerReceiptGroup;
use FacturaScripts\Plugins\PagosMultiples\Model\SupplierReceiptGroup;

/**
 * Description of Init
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class Init extends InitClass
{

    public function init()
    {
        $this->loadExtension(new Extension\Model\ReciboCliente());
        $this->loadExtension(new Extension\Model\ReciboProveedor());
        $this->loadExtension(new Extension\Controller\ListFacturaCliente());
        $this->loadExtension(new Extension\Controller\ListFacturaProveedor());
    }

    /**
     * Set up plugin when install or update.
     */
    public function update()
    {
        new CustomerReceiptGroup();
        new ReciboCliente();

        new SupplierReceiptGroup();
        new ReciboProveedor();
    }
}
