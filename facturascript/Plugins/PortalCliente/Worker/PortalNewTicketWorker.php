<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Worker;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\WorkEvent;
use FacturaScripts\Core\Template\WorkerClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Email\ButtonBlock;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Lib\Email\SpaceBlock;
use FacturaScripts\Dinamic\Lib\Email\TextBlock;
use FacturaScripts\Dinamic\Model\PortalTicket;
use FacturaScripts\Dinamic\Model\RoleUser;
use FacturaScripts\Dinamic\Model\User;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalNewTicketWorker extends WorkerClass
{
    public function run(WorkEvent $event): bool
    {
        // obtenemos el listado de usuario al que notificar
        $userGroup = Tools::settings('portalcliente', 'group_notify_tickets');
        if (empty($userGroup)) {
            return $this->done();
        }

        // obtenemos el ticket
        $ticket = new PortalTicket();
        if (false === $ticket->loadFromCode($event->value)) {
            return $this->done();
        }

        $roleUserModel = new RoleUser();
        $where = [new DataBaseWhere('codrole', $userGroup)];
        foreach ($roleUserModel->all($where, [], 0, 0) as $roleUser) {
            $user = new User();
            if (false === $user->loadFromCode($roleUser->nick)) {
                continue;
            }

            // preparamos y enviamos el email
            NewMail::create()
                ->to($user->email)
                ->subject(Tools::lang($user->langcode)->trans('new-support-ticket-subject', ['%code%' => $ticket->id]))
                ->addMainBlock(new TextBlock(Tools::lang($user->langcode)->trans('new-support-ticket-body', ['%code%' => $ticket->id, '%nick%' => $ticket->getContact()->pc_nick])))
                ->addMainBlock(new SpaceBlock(10))
                ->addMainBlock(new ButtonBlock(Tools::lang($user->langcode)->trans('view-support-ticket'), Tools::siteUrl() . "/" . $ticket->url()))
                ->send();
        }

        return $this->done();
    }
}