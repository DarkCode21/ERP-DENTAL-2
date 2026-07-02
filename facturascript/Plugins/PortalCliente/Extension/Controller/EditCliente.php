<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditCliente
{
    use CommonFileTrait;

    public function createViews(): Closure
    {
        return function () {
            $this->createViewPortalContacts();
            $this->createViewPortalTickets();
            $this->createViewPortalNotes();
            $this->createViewCarts();
        };
    }

    protected function createViewCarts(): Closure
    {
        return function (string $viewName = 'ListPortalCart') {
            $this->addListView($viewName, 'Join\PortalCart', 'client-portal-carts', 'fas fa-cart-shopping')
                ->addOrderBy(['idcontacto'], 'contact')
                ->addOrderBy(['creation_date'], 'creation-date', 2)
                ->addOrderBy(['last_update'], 'last-update')
                ->addFilterAutocomplete('contact', 'contact', 'idcontacto', 'contactos', 'idcontacto', 'pc_nick')
                ->addFilterAutocomplete('reference', 'reference', 'idvariante', 'variantes', 'idvariante', 'referencia')
                ->addFilterPeriod('creation_date', 'creation-date', 'creation_date', true)
                ->addFilterNumber('products-gt', 'products', 'products', '>=')
                ->addFilterNumber('products-lt', 'products', 'products', '<=')
                ->setSettings('btnNew', false)
                ->setSettings('btnDelete', false)
                ->setSettings('checkBoxes', false);
        };
    }

    protected function createViewPortalNotes(): Closure
    {
        return function (string $viewName = 'EditPortalNote') {
            $this->addEditListView($viewName, 'PortalNote', 'client-portal-notes', 'far fa-sticky-note');
        };
    }

    protected function createViewPortalContacts(): Closure
    {
        return function (string $viewName = 'ListPortalContacto') {
            $this->addListView($viewName, 'Contacto', 'client-portal-contacts', 'fas fa-chalkboard-user')
                ->addSearchFields(['pc_nick', 'pc_last_ip'])
                ->addFilterAutocomplete('contact', 'contact', 'pc_nick', 'contactos', 'pc_nick', 'pc_nick')
                ->addFilterPeriod('pc_last_login', 'last-login', 'pc_last_login', true)
                ->setSettings('btnDelete', false)
                ->setSettings('checkBoxes', false);
        };
    }

    protected function createViewPortalTickets(): Closure
    {
        return function (string $viewName = 'ListPortalTicket') {
            $this->addListView($viewName, 'PortalTicket', 'client-portal-tickets', 'far fa-comment-dots')
                ->addOrderBy(['creation_date'], 'date')
                ->addOrderBy(['last_update'], 'last-update', 2)
                ->addSearchFields(['body'])
                ->addFilterAutocomplete('contact', 'contact', 'idcontacto', 'contactos', 'idcontacto', 'pc_nick')
                ->addFilterPeriod('creation_date', 'creation-date', 'creation_date', true)
                ->addFilterAutocomplete('contact', 'contact', 'idcontacto', 'contactos', 'idcontacto', 'pc_nick')
                ->setSettings('btnNew', false)
                ->setSettings('btnDelete', false)
                ->setSettings('checkBoxes', false);
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            $mvn = $this->getMainViewName();
            $codcliente = $this->getViewModelValue($mvn, 'codcliente');

            switch ($viewName) {
                case 'EditPortalNote':
                case 'ListPortalContacto';
                    $where = [new DataBaseWhere('codcliente', $codcliente)];
                    $view->loadData('', $where);
                    break;

                case 'ListPortalCart':
                case 'ListPortalTicket':
                    // obtenemos todos los contactos del cliente
                    $contacts = [];
                    foreach ($this->views[$mvn]->model->getAddresses() as $address) {
                        $contacts[] = $address->idcontacto;
                    }

                    if (empty($contacts)) {
                        return;
                    }

                    $where = [new DataBaseWhere('idcontacto', implode(',', $contacts), 'IN')];
                    $view->loadData('', $where);
                    break;
            }
        };
    }
}
