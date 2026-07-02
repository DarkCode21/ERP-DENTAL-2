<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Email\ButtonBlock;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Lib\Email\SpaceBlock;
use FacturaScripts\Dinamic\Lib\Email\TextBlock;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditContacto
{
    use CommonFileTrait;

    protected function createViews(): Closure
    {
        return function () {
            $this->createViewTickets();
            $this->createViewNotes();
            $this->createViewCarts();
            $this->createViewFavorites();
        };
    }

    protected function createViewCarts(): Closure
    {
        return function (string $viewName = 'ListPortalCartLine') {
            $this->addListView($viewName, 'PortalCart', 'client-portal-cart', 'fas fa-cart-shopping')
                ->disableColumn('contact', true)
                ->setSettings('btnNew', false)
                ->setSettings('btnDelete', false)
                ->setSettings('checkBoxes', false)
                ->setSettings('clickable', false);

            $this->addButton($viewName, [
                'action' => 'remember-cart',
                'color' => 'primary',
                'icon' => 'fas fa-paper-plane',
                'label' => 'remember-cart',
                'type' => 'action',
            ]);
        };
    }

    protected function createViewFavorites(): Closure
    {
        return function (string $viewName = 'ListPortalFavoriteLine') {
            $this->addListView($viewName, 'PortalFavorite', 'client-portal-favorites', 'far fa-heart')
                ->disableColumn('contact', true)
                ->setSettings('btnNew', false)
                ->setSettings('btnDelete', false)
                ->setSettings('checkBoxes', false)
                ->setSettings('clickable', false);
        };
    }

    protected function createViewNotes(): Closure
    {
        return function (string $viewName = 'EditPortalNote') {
            $this->addEditListView($viewName, 'PortalNote', 'client-portal-notes', 'far fa-sticky-note');
        };
    }

    protected function createViewTickets(): Closure
    {
        return function (string $viewName = 'ListPortalTicket') {
            $this->addListView($viewName, 'PortalTicket', 'client-portal-tickets', 'far fa-comment-dots')
                ->addOrderBy(['creation_date'], 'date')
                ->addOrderBy(['last_update'], 'last-update', 2)
                ->addSearchFields(['body'])
                ->addFilterAutocomplete('contact', 'contact', 'idcontacto', 'contactos', 'idcontacto', 'pc_nick')
                ->addFilterPeriod('creation_date', 'creation-date', 'creation_date', true)
                ->disableColumn('contact', true)
                ->disableColumn('email', true)
                ->setSettings('btnNew', false)
                ->setSettings('btnDelete', false)
                ->setSettings('checkBoxes', false);
        };
    }

    public function execPreviousAction(): Closure
    {
        return function ($action) {
            switch ($action) {
                case 'client-portal';
                    $this->clientPortalAction();
                    break;

                case 'remember-cart':
                    $this->rememberCartAction();
                    break;

                case 'send-link-client-portal':
                    $this->sendLinkClientPortalAction();
                    break;
            }
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            $mvn = $this->getMainViewName();

            switch ($viewName) {
                case 'EditPortalNote':
                case 'ListPortalCartLine':
                case 'ListPortalFavoriteLine':
                case 'ListPortalTicket':
                    $idcontacto = $this->getViewModelValue($mvn, 'idcontacto');
                    $where = [new DataBaseWhere('idcontacto', $idcontacto)];
                    $view->loadData('', $where);
                    break;

                case $mvn:
                    // si el contacto está activo en el portal cliente, terminamos
                    if (false === $view->model->pc_active) {
                        return;
                    }

                    $this->addButton($mvn, [
                        'action' => 'client-portal',
                        'color' => 'primary',
                        'icon' => 'fas fa-chalkboard-user',
                        'label' => 'client-portal',
                        'type' => 'action',
                    ]);

                    $this->addButton($mvn, [
                        'action' => 'send-link-client-portal',
                        'color' => 'light',
                        'icon' => 'fas fa-paper-plane',
                        'title' => 'send-link-client-portal',
                        'label' => 'send-link-client-portal-abb',
                        'type' => 'action',
                    ]);
                    break;
            }
        };
    }

    protected function clientPortalAction(): Closure
    {
        return function () {
            $contact = $this->getModel();
            if (false === $contact->loadFromCode($this->request->get('code'))
                || false === $contact->pc_active) {
                return;
            }

            if (empty($contact->pc_log_key)) {
                $contact->newPCLogkey();
            }

            $contact->updatePCActivity($this->request->headers->get('User-Agent'));
            $contact->save();

            $expire = time() + FS_COOKIES_EXPIRE;
            setcookie('pc_idcontacto', $contact->idcontacto, $expire, Tools::config('route', '/'));
            setcookie('pc_log_key', $contact->pc_log_key, $expire, Tools::config('route', '/'));

            $this->redirect('PortalCliente');
        };
    }

    protected function rememberCartAction(): Closure
    {
        return function () {
            $contact = $this->getModel();
            if (false === $contact->loadFromCode($this->request->get('code'))
                || false === $contact->pc_active) {
                return;
            }

            // si el contacto no tiene email, terminamos
            if (empty($contact->email)) {
                Tools::log()->warning('contact-without-email-portal', ['%nick%' => $contact->pc_nick]);
                return;
            }

            // si el contacto no está activo, terminamos
            if (false === $contact->pc_active) {
                Tools::log()->warning('contact-not-active-portal', ['%nick%' => $contact->pc_nick]);
                return;
            }

            // enviamos el correo
            $email = NewMail::create()
                ->to($contact->email)
                ->subject(Tools::lang($contact->langcode)->trans('remember-cart-subject'))
                ->addMainBlock(new TextBlock(Tools::lang($contact->langcode)->trans('remember-cart-body')))
                ->addMainBlock(new SpaceBlock(10))
                ->addMainBlock(new ButtonBlock(Tools::lang($contact->langcode)->trans('client-portal'), Tools::siteUrl() . '/PortalCliente'));

            if ($email->send()) {
                Tools::log()->info('remember-cart-sent');
                return;
            }

            Tools::log()->error('remember-cart-not-sent');
        };
    }

    protected function sendLinkClientPortalAction(): Closure
    {
        return function () {
            $contact = $this->getModel();
            if (false === $contact->loadFromCode($this->request->get('code'))
                || false === $contact->pc_active) {
                return;
            }

            // si el contacto no tiene email, terminamos
            if (empty($contact->email)) {
                Tools::log()->warning('contact-without-email-portal', ['%nick%' => $contact->pc_nick]);
                return;
            }

            // si el contacto no está activo, terminamos
            if (false === $contact->pc_active) {
                Tools::log()->warning('contact-not-active-portal', ['%nick%' => $contact->pc_nick]);
                return;
            }

            // establecemos una nueva contraseña
            $newPassword = Tools::randomString(8);
            $contact->setPCPassword($newPassword);
            if (false === $contact->save()) {
                Tools::log()->error('record-save-error');
                return;
            }

            // enviamos el correo
            $email = NewMail::create()
                ->to($contact->email)
                ->subject(Tools::lang($contact->langcode)->trans('client-portal-link-subject'))
                ->addMainBlock(new TextBlock(Tools::lang($contact->langcode)->trans('client-portal-access')))
                ->addMainBlock(new TextBlock(Tools::lang($contact->langcode)->trans('client-portal-access-nick', ['%nick%' => $contact->pc_nick])))
                ->addMainBlock(new TextBlock(Tools::lang($contact->langcode)->trans('client-portal-access-password', ['%password%' => $newPassword])))
                ->addMainBlock(new SpaceBlock(10))
                ->addMainBlock(new TextBlock(Tools::lang($contact->langcode)->trans('client-portal-link-body')))
                ->addMainBlock(new ButtonBlock(Tools::lang($contact->langcode)->trans('client-portal'), Tools::siteUrl() . '/PortalCliente'));

            if ($email->send()) {
                Tools::log()->info('client-portal-link-sent');
                return;
            }

            Tools::log()->error('client-portal-link-not-sent');
        };
    }
}
