<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\PortalTicket;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditPortalTicket extends EditController
{
    public function getModelClassName(): string
    {
        return 'PortalTicket';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'PortalCliente';
        $data['title'] = 'support-ticket';
        $data['icon'] = 'far fa-comment-dots';
        return $data;
    }

    protected function createViews(): void
    {
        // obtenemos el modelo
        $model = new PortalTicket();

        if (false === $model->loadFromCode($this->request->get('code'))) {
            $this->redirect('ListCliente?activetab=ListPortalTicket');
            return;
        }

        $contact = $model->getContact();
        $contact->updatePCActivity();
        $contact->save();

        $expire = time() + FS_COOKIES_EXPIRE;
        setcookie('pc_idcontacto', $contact->idcontacto, $expire, Tools::config('route', '/'));
        setcookie('pc_log_key', $contact->pc_log_key, $expire, Tools::config('route', '/'));

        $this->redirect($model->url('public'));
    }
}