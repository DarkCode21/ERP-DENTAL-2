<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Worker;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Model\WorkEvent;
use FacturaScripts\Core\Template\WorkerClass;
use FacturaScripts\Dinamic\Model\FacturaCliente;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalFacturaClienteUuidWorker extends WorkerClass
{
    public function run(WorkEvent $event): bool
    {
        new FacturaCliente();

        // buscamos los documentos sin uuid
        $db = new DataBase();
        $sqlSelect = 'SELECT pc_uuid, idfactura FROM facturascli WHERE pc_uuid IS NULL';
        foreach ($db->select($sqlSelect) as $row) {
            $sqlUpdate = 'UPDATE facturascli SET pc_uuid = "' . uniqid() . '" WHERE idfactura = ' . $row['idfactura'];
            $db->exec($sqlUpdate);
        }

        return $this->done();
    }
}