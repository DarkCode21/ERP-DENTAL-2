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
use Parsedown;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalTicket extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $body;

    /** @var bool */
    public $closed;

    /** @var string */
    public $creation_date;

    /** @var int */
    public $id;

    /** @var int */
    public $idcontacto;

    /** @var string */
    public $last_update;

    /** @var int */
    public $num_comments;

    public function addComment(PortalTicketComment &$comment): bool
    {
        // añadimos el comentario
        $comment->id_ticket = $this->id;
        if (false === $comment->save()) {
            return false;
        }

        // actualizamos el ticket
        $this->closed = 0;
        $this->last_update = $comment->creation_date;
        return $this->save();
    }

    public function addFile(string $path, string $fileName): bool
    {
        $newFile = new PortalTicketFile();
        if ($newFile->setFile($path, $fileName, $this)) {
            return $newFile->save();
        }

        return false;
    }

    public function clear(): void
    {
        parent::clear();
        $this->closed = false;
        $this->num_comments = 0;
    }

    public function delete(): bool
    {
        if (false === parent::delete()) {
            return false;
        }

        // eliminamos los archivos del ticket
        foreach ($this->getFiles() as $file) {
            $file->delete();
        }

        // eliminamos los comentarios
        foreach ($this->getComments() as $comment) {
            $comment->delete();
        }

        return true;
    }

    public function getComments(): array
    {
        $issueComment = new PortalTicketComment();
        $where = [new DataBaseWhere('id_ticket', $this->id)];
        return $issueComment->all($where, ['creation_date' => 'ASC'], 0, 0);
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
            new DataBaseWhere('id_ticket', $this->id),
            new DataBaseWhere('id_ticket_comment', null),
        ];
        return $fileModel->all($where, ['id' => 'ASC'], 0, 0);
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
        return "portal_tickets";
    }

    public function test(): bool
    {
        $this->creation_date = $this->creation_date ?? Tools::dateTime();
        $this->body = Tools::noHtml($this->body);
        return parent::test();
    }

    public function title(): string
    {
        return Tools::lang()->trans('support-ticket') . ' #' . $this->id;
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        return match ($type) {
            'public' => 'PortalTicket?code=' . $this->primaryColumnValue(),
            'new' => 'PortalTicket',
            default => parent::url($type, $list),
        };
    }

    protected function saveUpdate(array $values = []): bool
    {
        $this->num_comments = count($this->getComments());
        return parent::saveUpdate($values);
    }
}