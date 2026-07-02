<?php
/**
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TarifasVariantesAvanzadas;

use FacturaScripts\Core\Base\InitClass;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\PresupuestoCliente;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Plugins\TPVneo\Model\TpvCaja;
use FacturaScripts\Plugins\TPVneo\Model\TpvTerminal;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Init extends InitClass
{
    public function init()
    {
        // se ejecutará cada vez que carga FacturaScripts (si este plugin está activado).
        $this->loadExtension(new Extension\Controller\EditVariante());
        $this->loadExtension(new Extension\Model\Variante());
    }

    public function update()
    {
        // se ejecutará cada vez que se instala o actualiza el plugin.
    }

}