<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Model;

use FacturaScripts\Core\Controller\Myfiles;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalTicketFile extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var int */
    public $id_ticket;

    /** @var int */
    public $id_ticket_comment;

    /** @var string */
    public $file_name;

    /** @var string */
    public $file_path;

    public function delete(): bool
    {
        if (parent::delete()) {
            $this->deleteFile();
            return true;
        }

        return false;
    }

    public function install(): string
    {
        // dependencias
        new PortalTicket();
        new PortalTicketComment();

        return parent::install();
    }

    public static function primaryColumn(): string
    {
        return "id";
    }

    public function save(): bool
    {
        if (false === parent::save()) {
            $this->deleteFile();
            return false;
        }

        return true;
    }

    public function setFile(string $path, string $fileName, PortalTicket $ticket, PortalTicketComment $comment = null): bool
    {
        $this->id_ticket = $ticket->id;

        if ($comment !== null) {
            $this->id_ticket_comment = $comment->id;
        }

        // comprobar si el archivo es seguro
        $lowerFileName = strtolower($fileName);
        if (false === Myfiles::isFileSafe($lowerFileName)) {
            $parts = explode('.', $lowerFileName);
            Tools::log()->warning('unsupported-file', [
                '%extension%' => end($parts),
                '%supported%' => 'avi, csv, gif, jpg, pdf, mp4, png, zip'
            ]);
            return false;
        }

        // comprobamos la carpeta
        $folderPath = 'MyFiles' . DIRECTORY_SEPARATOR . 'PortalTicket' . DIRECTORY_SEPARATOR . $this->id_ticket;
        $folderFullPath = FS_FOLDER . DIRECTORY_SEPARATOR . $folderPath;
        if (false === Tools::folderCheckOrCreate($folderFullPath)) {
            Tools::log()->warning('folder-not-created', ['%folder%' => $folderPath]);
            return false;
        }

        $this->file_name = time() . '_' . preg_replace('/[^a-z0-9\.]/i', '-', $lowerFileName);
        $this->file_path = $folderPath . DIRECTORY_SEPARATOR . $this->file_name;
        if (false === rename($path, FS_FOLDER . DIRECTORY_SEPARATOR . $this->file_path)) {
            Tools::log()->warning('file-not-moved', ['%file%' => $fileName]);
            return false;
        }

        return true;
    }

    public static function tableName(): string
    {
        return "portal_tickets_files";
    }

    public function test(): bool
    {
        $this->file_name = Tools::noHtml($this->file_name);
        $this->file_path = Tools::noHtml($this->file_path);
        return parent::test();
    }

    protected function deleteFile(): void
    {
        // si el archivo existe, lo borramos
        $fileFullPath = FS_FOLDER . DIRECTORY_SEPARATOR . $this->file_path;
        if (file_exists($fileFullPath)) {
            unlink($fileFullPath);
        }
    }
}