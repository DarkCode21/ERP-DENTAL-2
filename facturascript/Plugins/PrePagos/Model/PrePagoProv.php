<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PrePagos\Model;

use FacturaScripts\Core\DbQuery;
use FacturaScripts\Core\Model\Base\AccEntryRelationTrait;
use FacturaScripts\Core\Model\Base\ModelOnChangeClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Model\Base\PaymentRelationTrait;
use FacturaScripts\Core\Model\Base\PurchaseDocument;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Accounting\PrePagoProvToAccounting;
use FacturaScripts\Dinamic\Model\AlbaranProveedor;
use FacturaScripts\Dinamic\Model\PedidoProveedor;
use FacturaScripts\Dinamic\Model\PresupuestoProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PrePagoProv extends ModelOnChangeClass
{
    use ModelTrait;
    use AccEntryRelationTrait;
    use PaymentRelationTrait;

    /** @var float */
    public $amount;

    /** @var string */
    public $codproveedor;

    /** @var bool */
    public $copied;

    /** @var string */
    public $creationdate;

    /** @var bool */
    public $editable;

    /** @var int */
    public $id;

    /** @var string */
    public $lastnick;

    /** @var string */
    public $lastupdate;

    /** @var int */
    public $modelid;

    /** @var string */
    public $modelname;

    /** @var string */
    public $nick;

    /** @var string */
    public $notes;

    /** @var string */
    public $payment_date;

    public function __get(string $field)
    {
        if ($field != 'document') {
            return null;
        }

        $document = $this->getDocument();
        if (empty($document) || false === $document->exists()) {
            return null;
        }

        return $document->codigo;
    }

    public function clear()
    {
        parent::clear();
        $this->amount = 0.0;
        $this->editable = true;
        $this->copied = false;
        $this->codpago = Tools::settings('default', 'codpago');
        $this->payment_date = Tools::date();
    }

    public function delete(): bool
    {
        // si el pago tiene un documento y este no es editable, no se puede eliminar
        $document = $this->getDocument();
        if ($document && $document->exists() && false === $document->editable) {
            Tools::log()->warning('document-not-editable');
            return false;
        }

        // si está copiado, no se puede eliminar
        if ($this->copied) {
            Tools::log()->warning('document-copied');
            return false;
        }

        if (false === parent::delete()) {
            return false;
        }

        $this->deleteAccountingEntry();
        $this->setTotalPendingDocument();

        return true;
    }

    public function deleteAccountingEntry(): bool
    {
        $entry = $this->getAccountingEntry();
        if ($entry->exists() && $entry->delete()) {
            $this->idasiento = null;
            return true;
        }

        return false;
    }

    public function getDocument(): ?PurchaseDocument
    {
        switch ($this->modelname) {
            case 'AlbaranProveedor':
                $alb = new AlbaranProveedor();
                $alb->loadFromCode($this->modelid);
                return $alb;

            case 'PedidoProveedor':
                $ped = new PedidoProveedor();
                $ped->loadFromCode($this->modelid);
                return $ped;

            case 'PresupuestoProveedor':
                $pre = new PresupuestoProveedor();
                $pre->loadFromCode($this->modelid);
                return $pre;
        }

        return null;
    }

    public function getSupplier(): ?Proveedor
    {
        $proveedor = new Proveedor();
        if (!empty($this->codproveedor) && $proveedor->loadFromCode($this->codproveedor)) {
            return $proveedor;
        }

        return null;
    }

    public static function primaryColumn(): string
    {
        return "id";
    }

    public function save(): bool
    {
        if (false === parent::save()) {
            return false;
        }

        $this->setTotalPendingDocument();
        return true;
    }

    public static function tableName(): string
    {
        return "prepagosprov";
    }

    public function test(): bool
    {
        $this->creationdate = $this->creationdate ?? Tools::dateTime();
        $this->modelname = Tools::noHtml($this->modelname);
        $this->nick = $this->nick ?? Session::user()->nick;
        $this->notes = Tools::noHtml($this->notes);

        if (empty($this->amount)) {
            Tools::log()->warning('amount-not-zero');
            return false;
        }

        if (empty($this->payment_date)) {
            $this->payment_date = $this->creationdate;
        }

        // obtenemos el documento
        $document = $this->getDocument();

        // obtenemos la forma de pago
        $paymentMethod = $this->getPaymentMethod();

        // si hay documento, pero no hay cliente, lo asignamos
        if ($document && $document->exists() && empty($this->codproveedor)) {
            $this->codproveedor = $document->codproveedor;
        }

        // si existe el documento
        // y la empresa del documento es diferente a la empresa de la forma de pago del prepago, terminamos
        if ($document && $document->exists() && $document->idempresa !== $paymentMethod->idempresa) {
            Tools::log()->warning('document-company-different-from-payment-method-company');
            return true;
        }

        // si no se pudo asignar el cliente, no se puede guardar
        if (empty($this->codproveedor)) {
            Tools::log()->warning('supplier-not-assigned');
            return false;
        }

        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        $document = $this->getDocument();
        if ($document && $document->exists()) {
            return $document->url($type, $list);
        }

        $supplier = $this->getSupplier();
        if ($supplier && $supplier->exists() && $type === 'list') {
            return $supplier->url();
        }

        return parent::url($type, $list);
    }

    protected function onChange($field)
    {
        // si cambia el importe o la fecha, eliminamos el asiento
        if (in_array($field, ['amount', 'codpago', 'payment_date'])) {
            $entry = $this->getAccountingEntry();
            if ($entry->primaryColumnValue() && $entry->delete()) {
                $this->idasiento = null;
            }

            // y regeneramos el asiento
            $document = $this->getDocument();
            if ($document && $document->exists()) {
                PrePagoProvToAccounting::generate($this->id, false);
            }
        }

        return parent::onChange($field);
    }

    protected function saveInsert(array $values = []): bool
    {
        if (false === parent::saveInsert($values)) {
            return false;
        }

        $document = $this->getDocument();
        if ($document && $document->exists()) {
            PrePagoProvToAccounting::generate($this->id, true);
        }

        return true;
    }

    protected function saveUpdate(array $values = []): bool
    {
        $this->lastnick = Session::user()->nick;
        $this->lastupdate = Tools::dateTime();

        return parent::saveUpdate($values);
    }

    protected function setPreviousData(array $fields = [])
    {
        $more = ['amount', 'codpago', 'creationdate'];
        parent::setPreviousData(array_merge($fields, $more));
    }

    protected function setTotalPendingDocument(): void
    {
        // obtenemos el documento
        $doc = $this->getDocument();
        if (empty($doc) || false === $doc->exists() || false === property_exists($doc, 'total_pending')) {
            return;
        }

        // calculamos el total pendiente
        $total_pending = $doc->total;
        foreach ($doc->getPayments() as $payment) {
            $total_pending -= $payment->amount;
        }

        // actualizamos el documento
        DbQuery::table($doc->tableName())
            ->whereEq($doc->primaryColumn(), $doc->primaryColumnValue())
            ->update(['total_pending' => $total_pending]);
    }
}
