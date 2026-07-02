<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\PortalTicket as DinPortalTicket;
use Parsedown;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalTicketComment extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $body;

    /** @var string */
    public $creation_date;

    /** @var int */
    public $id;

    /** @var int */
    public $idcontacto;

    /** @var int */
    public $id_ticket;

    /** @var string */
    public $nick;

    /** @var string */
    public $read;

    public function addFile(string $path, string $fileName): bool
    {
        $ticket = $this->getTicket();
        $newFile = new PortalTicketFile();
        if ($newFile->setFile($path, $fileName, $ticket, $this)) {
            return $newFile->save();
        }

        return false;
    }

    public function delete(): bool
    {
        if (false === parent::delete()) {
            return false;
        }

        // eliminamos los archivos del comentario
        foreach ($this->getFiles() as $file) {
            $file->delete();
        }

        return true;
    }

    public function getContact(): Contacto
    {
        $contact = new Contacto();
        $contact->loadFromCode($this->idcontacto);
        return $contact;
    }

    public function getFiles(): array
    {
        $fileModel = new PortalTicketFile();
        $where = [
            new DataBaseWhere('id_ticket', $this->id_ticket),
            new DataBaseWhere('id_ticket_comment', $this->id),
        ];
        return $fileModel->all($where, ['id' => 'ASC'], 0, 0);
    }

    public function getTicket(): DinPortalTicket
    {
        $issue = new DinPortalTicket();
        $issue->loadFromCode($this->id_ticket);
        return $issue;
    }

    public function install(): string
    {
        // dependencias
        new PortalTicket();

        return parent::install();
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
        return "id";
    }

    public static function tableName(): string
    {
        return "portal_tickets_comments";
    }

    public function test(): bool
    {
        $this->creation_date = $this->creation_date ?? Tools::dateTime();
        $this->body = Tools::noHtml($this->body);
        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        return $this->getTicket()->url('public', $list) . '#comm' . $this->id;
    }
}