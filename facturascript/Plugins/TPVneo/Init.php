<?php
/**
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TPVneo;

use FacturaScripts\Core\Base\InitClass;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\PresupuestoCliente;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Plugins\TPVneo\Model\TpvCaja;
use FacturaScripts\Plugins\TPVneo\Model\TpvCajaMovimiento;
use FacturaScripts\Plugins\TPVneo\Model\TpvTerminal;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Init extends InitClass
{
    public function init()
    {
        // se ejecutará cada vez que carga FacturaScripts (si este plugin está activado).
        $this->loadExtension(new Extension\Controller\EditDivisa());
        $this->loadExtension(new Extension\Controller\EditFormaPago());
        $this->loadExtension(new Extension\Controller\EditAgente());
        $this->loadExtension(new Extension\Controller\EditSettings());
        $this->loadExtension(new Extension\Controller\ListTicketPrinter());
        $this->loadExtension(new Extension\Model\Producto());
        $this->loadExtension(new Extension\Model\PresupuestoCliente());
    }

    public function update()
    {
        // se ejecutará cada vez que se instala o actualiza el plugin.
        new TpvTerminal();
        new TpvCaja();
        new TpvCajaMovimiento();
        new Producto();
        new AlbaranCliente();
        new FacturaCliente();
        new PresupuestoCliente();

        $this->createAgente();
    }

    private function createAgente()
    {
        $agente = new Agente();
        if ($agente->count() > 0) {
            return;
        }

        // creamos un agente, si no hay ninguno
        $agente->nombre = 'TPV';
        $agente->save();
    }
}