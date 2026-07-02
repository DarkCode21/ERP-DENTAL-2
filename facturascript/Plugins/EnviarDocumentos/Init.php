<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\EnviarDocumentos;

use FacturaScripts\Core\Base\InitClass;

/**
 * Description of Init
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class Init extends InitClass
{
    public function init()
    {
        $this->loadExtension(new Extension\Controller\ListPresupuestoCliente());
        $this->loadExtension(new Extension\Controller\ListPedidoCliente());
        $this->loadExtension(new Extension\Controller\ListAlbaranCliente());
        $this->loadExtension(new Extension\Controller\ListFacturaCliente());
        $this->loadExtension(new Extension\Model\Cliente());
    }

    public function update()
    {
    }
}
