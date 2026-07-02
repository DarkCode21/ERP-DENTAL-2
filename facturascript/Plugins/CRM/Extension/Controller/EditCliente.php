<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CRM\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Cliente;

class EditCliente
{
    protected function createViews(): Closure
    {
        return function () {
            $viewName = 'EditCrmNota';
            $this->addEditListView($viewName, 'CrmNota', 'notes', 'far fa-sticky-note');
            $this->views[$viewName]->disableColumn('contact', true);
        };
    }

    protected function execPreviousAction(): Closure
    {
        return function ($action) {
            if ($action === 'show-contact') {
                $idcontacto = $this->request->request->get('code');
                $this->redirect('EditContacto?code=' . $idcontacto);
            }
        };
    }

    protected function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName === 'EditCrmNota') {
                $customer = new Cliente();
                $customer->loadFromCode($this->request->get('code'));

                $where = [new DataBaseWhere('idcontacto', $customer->idcontactofact)];
                $view->loadData('', $where);
            }
        };
    }
}