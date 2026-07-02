<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Worker;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Model\WorkEvent;
use FacturaScripts\Core\Template\WorkerClass;
use FacturaScripts\Dinamic\Model\PedidoCliente;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalPedidoClienteUuidWorker extends WorkerClass
{
    public function run(WorkEvent $event): bool
    {
        new PedidoCliente();

        // buscamos los documentos sin uuid
        $db = new DataBase();
        $sqlSelect = 'SELECT pc_uuid, idpedido FROM pedidoscli WHERE pc_uuid IS NULL';
        foreach ($db->select($sqlSelect) as $row) {
            $sqlUpdate = 'UPDATE pedidoscli SET pc_uuid = "' . uniqid() . '" WHERE idpedido = ' . $row['idpedido'];
            $db->exec($sqlUpdate);
        }

        return $this->done();
    }
}