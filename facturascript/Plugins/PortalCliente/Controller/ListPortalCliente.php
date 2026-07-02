<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ListPortalCliente extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'sales';
        $data['title'] = 'client-portal';
        $data['icon'] = 'fas fa-chalkboard-user';
        return $data;
    }

    protected function createViews()
    {
        $this->createViewContacts();
        $this->createViewTickets();
        $this->createViewNotes();
        $this->createViewCarts();
        $this->createViewFavorites();
        $this->createViewFiles();
    }

    protected function createViewCarts(string $viewName = 'ListPortalCart'): void
    {
        $this->addView($viewName, 'Join\PortalCart', 'carts', 'fas fa-cart-shopping')
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
    }

    protected function createViewContacts(string $viewName = 'ListPortalContacto'): void
    {
        $this->addView($viewName, 'Contacto', 'contacts', 'fas fa-users')
            ->addSearchFields(['pc_nick', 'pc_last_ip'])
            ->addFilterAutocomplete('contact', 'contact', 'pc_nick', 'contactos', 'pc_nick', 'pc_nick')
            ->addFilterPeriod('pc_last_login', 'last-login', 'pc_last_login', true)
            ->addFilterSelectWhere('pc_active', [
                [
                    'label' =>Tools::lang()->trans('active'),
                    'where' => [
                        new DataBaseWhere('pc_active', true),
                        new DataBaseWhere('pc_nick', null, 'IS NOT'),
                    ]
                ],
                [
                    'label' =>Tools::lang()->trans('inactive'),
                    'where' => [
                        new DataBaseWhere('pc_active', false),
                        new DataBaseWhere('pc_nick', null, 'IS NOT'),
                    ]
                ],
                [
                    'label' =>Tools::lang()->trans('all'),
                    'where' => [new DataBaseWhere('pc_nick', null, 'IS NOT')]
                ],
            ])
            ->setSettings('btnDelete', false)
            ->setSettings('checkBoxes', false);
    }

    protected function createViewFavorites(string $viewName = 'ListPortalFavorite'): void
    {
        $this->addView($viewName, 'Join\PortalFavorite', 'favorites', 'far fa-heart')
            ->addOrderBy(['idcontacto'], 'contact')
            ->addOrderBy(['creation_date'], 'creation-date', 2)
            ->addFilterAutocomplete('contact', 'contact', 'idcontacto', 'contactos', 'idcontacto', 'pc_nick')
            ->addFilterAutocomplete('reference', 'reference', 'idproducto', 'productos', 'idproducto', 'referencia')
            ->addFilterPeriod('creation_date', 'creation-date', 'creation_date', true)
            ->addFilterNumber('products-gt', 'products', 'products', '>=')
            ->addFilterNumber('products-lt', 'products', 'products', '<=')
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false)
            ->setSettings('checkBoxes', false);
    }

    protected function createViewFiles(string $viewName = 'ListAttachedFileRelation'): void
    {
        $this->addView($viewName, 'AttachedFileRelation', 'files', 'fas fa-paperclip')
            ->addSearchFields(['observations'])
            ->addFilterCheckbox('pc_show', 'show-on-client-portal', 'pc_show')
            ->addFilterCheckbox('pc_show_paid', 'show-on-client-portal-when-paying', 'pc_show_paid')
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false)
            ->setSettings('checkBoxes', false);
    }

    protected function createViewNotes(string $viewName = 'ListPortalNote'): void
    {
        $this->addView($viewName, 'PortalNote', 'notes', 'far fa-sticky-note')
            ->addSearchFields(['title', 'body'])
            ->addFilterAutocomplete('customer', 'customer', 'codcliente', 'clientes', 'codcliente', 'nombre')
            ->addFilterAutocomplete('contact', 'contact', 'pc_nick', 'contactos', 'pc_nick', 'pc_nick')
            ->addFilterPeriod('creation_date', 'creation-date', 'creation_date', true)
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false)
            ->setSettings('checkBoxes', false);
    }

    protected function createViewTickets(string $viewName = 'ListPortalTicket'): void
    {
        $this->addView($viewName, 'PortalTicket', 'tickets', 'far fa-comment-dots')
            ->addOrderBy(['creation_date'], 'date')
            ->addOrderBy(['last_update'], 'last-update', 2)
            ->addSearchFields(['body'])
            ->addFilterPeriod('creation_date', 'creation-date', 'creation_date', true)
            ->addFilterAutocomplete('contact', 'contact', 'idcontacto', 'contactos', 'idcontacto', 'pc_nick')
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false)
            ->setSettings('checkBoxes', false);
    }

    protected function loadData($viewName, $view)
    {
        if ($viewName === 'ListAttachedFileRelation') {
            $where = [
                new DataBaseWhere('pc_show', true),
                new DataBaseWhere('pc_show_paid', true, '=', 'OR'),
            ];
            $view->loadData('', $where);
            return;
        }

        parent::loadData($viewName, $view);
    }
}