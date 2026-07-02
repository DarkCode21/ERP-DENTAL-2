<?php
/**
 * Copyright (C) 2022-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PrePagos;

use FacturaScripts\Core\Base\AjaxForms\SalesFooterHTML;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Plugins\PrePagos\Model\PrePagoCli;

final class Init extends InitClass
{
    public function init(): void
    {
        // cargamos las extensiones
        $this->loadExtension(new Extension\Controller\EditCliente());
        $this->loadExtension(new Extension\Controller\EditProveedor());
        $this->loadExtension(new Extension\Controller\EditAlbaranCliente());
        $this->loadExtension(new Extension\Controller\EditAlbaranProveedor());
        $this->loadExtension(new Extension\Controller\EditPedidoCliente());
        $this->loadExtension(new Extension\Controller\EditPedidoProveedor());
        $this->loadExtension(new Extension\Controller\EditPresupuestoCliente());
        $this->loadExtension(new Extension\Controller\EditPresupuestoProveedor());
        $this->loadExtension(new Extension\Controller\ListAlbaranCliente());
        $this->loadExtension(new Extension\Controller\ListAlbaranProveedor());
        $this->loadExtension(new Extension\Controller\ListPedidoCliente());
        $this->loadExtension(new Extension\Controller\ListPedidoProveedor());
        $this->loadExtension(new Extension\Controller\ListPresupuestoCliente());
        $this->loadExtension(new Extension\Controller\ListPresupuestoProveedor());
        $this->loadExtension(new Extension\Lib\BusinessDocumentGenerator());
        $this->loadExtension(new Extension\Model\AlbaranCliente());
        $this->loadExtension(new Extension\Model\AlbaranProveedor());
        $this->loadExtension(new Extension\Model\DocRecurringPurchase());
        $this->loadExtension(new Extension\Model\DocRecurringSale());
        $this->loadExtension(new Extension\Model\FacturaCliente());
        $this->loadExtension(new Extension\Model\FacturaProveedor());
        $this->loadExtension(new Extension\Model\PedidoCliente());
        $this->loadExtension(new Extension\Model\PedidoProveedor());
        $this->loadExtension(new Extension\Model\PresupuestoCliente());
        $this->loadExtension(new Extension\Model\PresupuestoProveedor());

        // cargamos los mods
        SalesFooterHTML::addMod(new Mod\SalesFooterHTMLMod());
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
        $this->updateModelCli();
        $this->updateEditableCli();
    }

    private function updateEditableCli(): void
    {
        // recorremos todos los prepagos
        $where = [new DataBaseWhere('editable', null)];
        foreach (PrePagoCli::all($where, [], 0, 0) as $prePago) {
            // obtenemos el documento, si no existe, continuamos
            $doc = $prePago->getDocument();
            if (empty($doc) || false === $doc->exists()) {
                continue;
            }

            // si el campo editable del documento es igual al del prepago, continuamos
            if ($doc->editable === $prePago->editable) {
                continue;
            }

            // actualizamos el campo editable del prepago para que sea igual al del documento
            $prePago->editable = $doc->editable;
            $prePago->save();
        }
    }

    private function updateModelCli(): void
    {
        $db = new DataBase();

        // buscamos si existe la tabla prepagos y la renombramos por prepagoscli
        if ($db->tableExists('prepagos')) {
            $sql = 'RENAME TABLE prepagos TO prepagoscli;';
            $db->exec($sql);
        }
    }
}
