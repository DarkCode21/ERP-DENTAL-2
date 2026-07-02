<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Worker;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\WorkEvent;
use FacturaScripts\Core\Template\WorkerClass;
use FacturaScripts\Dinamic\Model\Contacto;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalCreateLoginContactWorker extends WorkerClass
{
    public function run(WorkEvent $event): bool
    {
        $contactModel = new Contacto();
        $where = [
            new DataBaseWhere('pc_nick', null),
            new DataBaseWhere('pc_nick', '', '=', 'OR')
        ];
        foreach ($contactModel->all($where, [], 0, 0) as $contact) {
            $contact->save();
        }

        return $this->done();
    }
}