<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Lib;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Dinamic\Model\AttachedFileRelation;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait PortalDocFilesTrait
{
    public function getContactDocFiles(): array
    {
        $contactFiles = [];
        $docModel = new AttachedFileRelation();

        // obtenemos los archivos del contacto, si existe
        if ($this->contact->exists()) {
            $whereContact = [new DataBaseWhere('pc_show', true)];
            $whereContact[] = new DataBaseWhere('model', 'Contacto');
            $whereContact[] = new DataBaseWhere('modelid|modelcode', $this->contact->idcontacto);
            $contactFiles = $docModel->all($whereContact, ['creationdate' => 'DESC']);
        }

        // si no hay cliente, devolvemos los archivos del contacto
        if (empty($this->contact->codcliente)) {
            return $contactFiles;
        }

        // obtenemos los archivos del cliente
        $whereClient = [new DataBaseWhere('pc_show', true)];
        $whereClient[] = new DataBaseWhere('model', 'Cliente');
        $whereClient[] = new DataBaseWhere('modelid|modelcode', $this->contact->codcliente);
        $clientFiles = $docModel->all($whereClient, ['creationdate' => 'DESC']);

        // unimos los dos arrays
        $result = array_merge($contactFiles, $clientFiles);

        // ordenamos el array por fecha de creación en php 8
        usort($result, fn($a, $b) => $a->creationdate <=> $b->creationdate);

        return $result;
    }

    public function getModelDocFiles(SalesDocument $docModel): array
    {
        // obtenemos los archivos del documento
        $attachModel = new AttachedFileRelation();
        $where = [new DataBaseWhere('model', $docModel->modelClassName()),];
        $where[] = is_numeric($docModel->primaryColumnValue()) ?
            new DataBaseWhere('modelid|modelcode', $docModel->primaryColumnValue()) :
            new DataBaseWhere('modelcode', $docModel->primaryColumnValue());

        if ($docModel->pc_paid) {
            $where[] = new DataBaseWhere('pc_show|pc_show_paid', true);
        } else {
            $where[] = new DataBaseWhere('pc_show', true);
        }

        $files = $attachModel->all($where, ['creationdate' => 'DESC'], 0, 0);

        // recorremos las líneas del documento
        foreach ($docModel->getLines() as $line) {
            // si la línea no tiene referencia, pasamos a la siguiente
            if (empty($line->referencia)) {
                continue;
            }

            // obtenemos los archivos del producto
            $where = [
                new DataBaseWhere('model', 'Producto'),
                new DataBaseWhere('modelid|modelcode', $line->idproducto),
            ];
            if ($docModel->pc_paid) {
                $where[] = new DataBaseWhere('pc_show|pc_show_paid', true);
            } else {
                $where[] = new DataBaseWhere('pc_show', true);
            }
            $lineFiles = $attachModel->all($where, ['creationdate' => 'DESC'], 0, 0);
            $files = array_merge($files, $lineFiles);
        }

        return $files;
    }

    protected function createViewDocFiles(string $viewName = 'docfiles', string $template = 'Tab/PortalDocFiles'): void
    {
        $this->addHtmlView($viewName, $template, 'AttachedFileRelation', 'files', 'fas fa-paperclip');
    }
}