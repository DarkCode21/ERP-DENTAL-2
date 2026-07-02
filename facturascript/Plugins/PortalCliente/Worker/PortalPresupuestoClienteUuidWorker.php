<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Worker;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Model\WorkEvent;
use FacturaScripts\Core\Template\WorkerClass;
use FacturaScripts\Dinamic\Model\PresupuestoCliente;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalPresupuestoClienteUuidWorker extends WorkerClass
{
    public function run(WorkEvent $event): bool
    {
        new PresupuestoCliente();

        // buscamos los documentos sin uuid
        $db = new DataBase();
        $sqlSelect = 'SELECT pc_uuid, idpresupuesto FROM presupuestoscli WHERE pc_uuid IS NULL';
        foreach ($db->select($sqlSelect) as $row) {
            $sqlUpdate = 'UPDATE presupuestoscli SET pc_uuid = "' . uniqid() . '" WHERE idpresupuesto = ' . $row['idpresupuesto'];
            $db->exec($sqlUpdate);
        }

        return $this->done();
    }
}