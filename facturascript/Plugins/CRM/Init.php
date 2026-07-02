<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CRM;

use FacturaScripts\Core\Base\InitClass;
use FacturaScripts\Plugins\CRM\Model\CrmFuente;
use FacturaScripts\Plugins\CRM\Model\CrmInteres;
use FacturaScripts\Plugins\CRM\Model\CrmOportunidadEstado;

/**
 * Description of Init
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class Init extends InitClass
{

    public function init()
    {
        $this->loadExtension(new Extension\Controller\EditCliente());
        $this->loadExtension(new Extension\Controller\EditPresupuestoCliente());
        $this->loadExtension(new Extension\Model\LineaPresupuestoCliente());
        $this->loadExtension(new Extension\Model\PresupuestoCliente());
    }

    public function update()
    {
        // needed dependencies
        new CrmFuente();
        new CrmInteres();
        new CrmOportunidadEstado();
    }
}
