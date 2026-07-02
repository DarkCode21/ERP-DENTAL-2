<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Worker;

use FacturaScripts\Core\Model\WorkEvent;
use FacturaScripts\Core\Template\WorkerClass;
use FacturaScripts\Dinamic\Model\FacturaCliente;

class PortalFacturaClientePaidWorker extends WorkerClass
{
    public function run(WorkEvent $event): bool
    {
        // obtenemos la factura
        $invoice = new FacturaCliente();
        if (false === $invoice->loadFromCode($event->value)) {
            return $this->done();
        }

        // si la factura está pagada, entonces marcamos como pagada online y guardamos
        if ($invoice->pagada) {
            $invoice->pc_paid;
            $invoice->save();
        }

        return $this->done();
    }
}