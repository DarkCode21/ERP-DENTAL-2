<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Worker;

use FacturaScripts\Core\Model\WorkEvent;
use FacturaScripts\Core\Template\WorkerClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Email\ButtonBlock;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Lib\Email\SpaceBlock;
use FacturaScripts\Dinamic\Lib\Email\TextBlock;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\PortalNote;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalNoteWorker extends WorkerClass
{
    public function run(WorkEvent $event): bool
    {
        // obtenemos la nota
        $note = new PortalNote();
        if (false === $note->loadFromCode($event->value)) {
            return $this->done();
        }

        // si la nota no tiene contacto ni cliente, terminamos
        if (empty($note->idcontacto) && empty($note->codcliente)) {
            return $this->done();
        }

        $contacts = [];
        $customer = $note->getCustomer();
        $contact = $note->getContact();

        // si el contacto tiene email, lo añadimos a la lista
        if ($contact->exists() && false === empty($contact->email)) {
            $contacts[] = [
                'email' => $contact->email,
                'langcode' => $contact->langcode,
            ];
        }

        // si hay cliente, obtenemos todos los contactos de cliente que tengan acceso al portal
        // y añadimos sus emails
        $this->getCustomerContacts($customer, $contacts);

        // si no hay emails, terminamos
        if (empty($contacts)) {
            return $this->done();
        }

        foreach ($contacts as $arrContact) {
            $title = Tools::lang($arrContact['langcode'])->trans('update-note-subject', ['%title%' => $note->title]);
            $body = Tools::lang($arrContact['langcode'])->trans('update-note-body');

            // si la nota es nueva, editamos los textos
            if (false === empty($note->creation_date) && empty($note->last_update)) {
                $title = Tools::lang($arrContact['langcode'])->trans('new-note-subject', ['%title%' => $note->title]);
                $body = Tools::lang($arrContact['langcode'])->trans('new-note-body');
            }

            NewMail::create()
                ->to($arrContact['email'])
                ->subject($title)
                ->addMainBlock(new TextBlock($body))
                ->addMainBlock(new SpaceBlock(10))
                ->addMainBlock(new ButtonBlock(Tools::lang($arrContact['langcode'])->trans('view-note'), Tools::siteUrl() . "/" . $note->url('public')))
                ->send();
        }

        return $this->done();
    }

    protected function getCustomerContacts(Cliente $customer, array &$contacts): void
    {
        if (false === $customer->exists()) {
            return;
        }

        foreach ($customer->getAddresses() as $contact) {
            if (false === $contact->pc_active || empty($contact->email)) {
                continue;
            }

            // buscamos en el array multidimensional si ya existe el email
            if (false === in_array($contact->email, array_column($contacts, 'email'))) {
                $contacts[] = [
                    'email' => $contact->email,
                    'langcode' => $contact->langcode,
                ];
            }
        }
    }
}