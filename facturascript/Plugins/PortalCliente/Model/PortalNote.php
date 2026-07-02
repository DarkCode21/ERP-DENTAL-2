<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Session;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Contacto;
use Parsedown;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalNote extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $body;

    /** @var string */
    public $codcliente;

    /** @var string */
    public $creation_date;

    /** @var int */
    public $id;

    /** @var int */
    public $idcontacto;

    /** @var string */
    public $last_nick;

    /** @var string */
    public $last_update;

    /** @var string */
    public $nick;

    /** @var string */
    public $title;

    public function clear() 
    {
        parent::clear();
        $this->creation_date = Tools::dateTime();
        $this->last_update = Tools::dateTime();
        $this->nick = Session::user()->nick;
    }

    public function getContact(): Contacto
    {
        $contact = new Contacto();
        $contact->loadFromCode($this->idcontacto);
        return $contact;
    }

    public function getCustomer(): Cliente
    {
        $customer = new Cliente();
        $customer->loadFromCode($this->codcliente);
        return $customer;
    }

    public function markdown(): string
    {
        $parser = new Parsedown();
        $parser->setSafeMode(true);
        $html = $parser->parse(Tools::fixHtml($this->body));

        // some html fixes
        return str_replace(
            ['<pre>', '<img ', '<h2>', '<h3>', '<h4>'],
            [
                '<pre class="bg-light p-3">',
                '<img class="img-fluid img-thumbnail mb-3" loading="lazy" ',
                '<h2 class="h3 mb-1 mt-5">',
                '<h3 class="h4 mb-1 mt-5">',
                '<h4 class="h5 mb-1 mt-5">'
            ],
            $html
        );
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'portal_notes';
    }

    public function test(): bool
    {
        $this->body = Tools::noHtml($this->body);
        $this->last_nick = Tools::noHtml($this->last_nick);
        $this->nick = $this->nick ?? Session::user()->nick;
        $this->title = Tools::noHtml($this->title);
        $this->idcontacto = empty($this->idcontacto) ? null : $this->idcontacto;
        $this->codcliente = empty($this->codcliente) ? null : $this->codcliente;

        if (empty($this->body)) {
            Tools::log()->warning('note-body-is-required');
            return false;
        }

        if (empty($this->codcliente) && empty($this->idcontacto)) {
            Tools::log()->warning('note-customer-or-contact-is-required');
            return false;
        }

        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        if ($type === 'public') {
            return 'PortalNote?code=' . $this->primaryColumnValue();
        }

        if (false === empty($this->idcontacto)) {
            return 'EditContacto?activetab=EditPortalNote&code=' . $this->idcontacto;
        } elseif (false === empty($this->codcliente)) {
            return 'EditCliente?activetab=EditPortalNote&code=' . $this->codcliente;
        }

        return parent::url($type, $list);
    }

    protected function saveUpdate(array $values = []): bool
    {
        $this->last_nick = Session::user()->nick;
        $this->last_update = Tools::dateTime();

        return parent::saveUpdate($values);
    }
}
