<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Worker;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Model\WorkEvent;
use FacturaScripts\Core\Template\WorkerClass;
use FacturaScripts\Dinamic\Model\AlbaranCliente;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalAlbaranClienteUuidWorker extends WorkerClass
{
    public function run(WorkEvent $event): bool
    {
        new AlbaranCliente();

        // buscamos los documentos sin uuid
        $db = new DataBase();
        $sqlSelect = 'SELECT pc_uuid, idalbaran FROM albaranescli WHERE pc_uuid IS NULL';
        foreach ($db->select($sqlSelect) as $row) {
            $sqlUpdate = 'UPDATE albaranescli SET pc_uuid = "' . uniqid() . '" WHERE idalbaran = ' . $row['idalbaran'];
            $db->exec($sqlUpdate);
        }

        return $this->done();
    }
}