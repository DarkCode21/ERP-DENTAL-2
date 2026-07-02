<?php

namespace FacturaScripts\Plugins\Nominas;

use FacturaScripts\Core\Base\InitClass;
use FacturaScripts\Dinamic\Lib\ExportManager;

class Init extends InitClass
{
    public function init()
    {
		// export manager
        ExportManager::addOptionModel('PDFnominaExport', 'PDF', 'Nomina');

        // se ejecutará cada vez que carga FacturaScripts (si este plugin está activado).
        #$this->loadExtension(new Extension\Controller\EditDivisa());
        #$this->loadExtension(new Extension\Controller\EditFormaPago());
        #$this->loadExtension(new Extension\Controller\EditAgente());
        #$this->loadExtension(new Extension\Controller\ListTicketPrinter());
        #$this->loadExtension(new Extension\Model\Producto());
        #$this->loadExtension(new Extension\Model\PresupuestoCliente());
    }

    public function update()
    {
		new Model\Empleado();
     	new Model\Nomina();
        // se ejecutará cada vez que se instala o actualiza el plugin.
        #new TpvTerminal();
        #new TpvCaja();
        #new Producto();
        #new AlbaranCliente();
        #new FacturaCliente();
        #new PresupuestoCliente();

        #$this->createAgente();
    }

}

