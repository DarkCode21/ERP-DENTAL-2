<?php
/**
 * This file is part of PagosMultiples plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * PagosMultiples Copyright (C) 2020-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\PagosMultiples\Lib\PagosMultiples;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\CustomerReceiptGroup;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Dinamic\Model\SupplierReceiptGroup;
use FacturaScripts\Dinamic\Model\Base\PaymentReceiptGroup;
use FacturaScripts\Plugins\PagosMultiples\Lib\Accounting\CustomerReceiptGroupToAccounting;
use FacturaScripts\Plugins\PagosMultiples\Lib\Accounting\SupplierReceiptGroupToAccounting;

/**
 * Auxiliar class for EditPaymentReceiptGroup controller actions.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class PaymentReceiptGroupActions
{

    private const MODEL_NAMESPACE = '\\FacturaScripts\\Dinamic\\Model\\';

    /**
     * Parent document.
     *
     * @var CustomerReceiptGroup|SupplierReceiptGroup
     */
    private $multiple;

    /**
     * Array of data received in the Post.
     *
     * @var array
     */
    private $data;

    /**
     * Class constructor.
     * Load parent document and set initial data.
     *
     * @param string $paymentClassName
     * @param string $code
     * @param array $data
     */
    function __construct(string $paymentClassName, string $code, array $data) {
        $modelClass = self::MODEL_NAMESPACE . $paymentClassName;
        $this->multiple = new $modelClass();
        $this->multiple->loadFromCode($code);
        $this->data = $data;
    }

    /**
     * Main action execute.
     *
     * @param string $action
     * @return bool
     */
    public function exec(string $action): bool
    {
        switch ($action) {
            case 'add-receipts':
                return $this->addReceiptsAction();

            case 'charged-receipts':
                return $this->chargedReceiptAction();

            case 'remove-receipts':
                return $this->removeReceiptsAction();

            case 'reopen-receipts':
                return $this->reopenReceiptsAction();
        }
    }

    /**
     * Add receipts to selected list.
     * Recalculate totals for multiple payment.
     *
     * @return bool
     */
    protected function addReceiptsAction(): bool
    {
        $selectedIds = $this->data['code'] ?? [];
        if (empty($this->multiple->id) || empty($selectedIds) || false === is_array($selectedIds)) {
            return true;
        }

        $receipt = $this->multiple->getReceipt();
        $count = 0;
        foreach ($selectedIds as $id) {
            if (false === $receipt->loadFromCode($id)) {
                continue;
            }

            $receipt->idmultiple = $this->multiple->id;
            if ($receipt->save()) {
                $count++;
            }
        }

        $this->multiple->updateTotal();
        Tools::log()->notice('items-added-correctly', ['%num%' => $count]);
        return true;
    }

    /**
     * Execute the bill collection process.
     *
     * @return bool
     */
    protected function chargedReceiptAction(): bool
    {
        $dataBase = new DataBase();
        $newTransation = false === $dataBase->inTransaction() && $dataBase->beginTransaction();
        try {
            if ($this->chargedReceiptPayment()
                && $this->chargedReceiptContabilize()
                && $this->updateMultiple(PaymentReceiptGroup::STATUS_CHARGED))
            {
                if ($newTransation) {
                    $dataBase->commit();
                }
                Tools::log()->notice('record-updated-correctly');
                return true;
            }
        } finally {
            if ($newTransation && $dataBase->inTransaction()) {
                $dataBase->rollback();
                Tools::log()->error('record-save-error');
            }
        }

        return false;
    }

    /**
     * Remove receipts and his payments from selected list.
     * Recalculate totals for multiple payment.
     *
     * @return bool
     */
    protected function removeReceiptsAction(): bool
    {
        $selectedIds = $this->data['code'] ?? [];
        if (empty($this->multiple->id) || empty($selectedIds) || false === is_array($selectedIds)) {
            return true;
        }

        $receipt = $this->multiple->getReceipt();
        $count = 0;
        foreach ($selectedIds as $id) {
            if ($receipt->loadFromCode($id)
                && $receipt->idmultiple == $this->multiple->id
                && $this->removeReceipt($receipt))
            {
                $count++;
            }
        }

        $this->multiple->updateTotal();
        Tools::log()->notice('items-removed-correctly', ['%num%' => $count]);
        return true;
    }

    /**
     * Execute undo charged receipt.
     *
     * @return bool
     */
    protected function reopenReceiptsAction(): bool
    {
        $dataBase = new DataBase();
        $newTransation = false === $dataBase->inTransaction() && $dataBase->beginTransaction();
        try {
            if ($this->removeReceiptContabilize()
                && $this->removeReceiptPayment()
                && $this->updateMultiple(PaymentReceiptGroup::STATUS_PENDING))
            {
                if ($newTransation) {
                    $dataBase->commit();
                }
                Tools::log()->notice('record-updated-correctly');
                return true;
            }
        } finally {
            if ($newTransation && $dataBase->inTransaction()) {
                $dataBase->rollback();
                Tools::log()->error('record-save-error');
            }
        }

        return false;
    }

    /**
     * Executes the accounting collection process.
     *
     * @return bool
     */
    private function chargedReceiptContabilize(): bool
    {
        if ($this->multiple->noentry) {
            return true;
        }

        if ($this->multiple instanceof CustomerReceiptGroup) {
            $accounting = new CustomerReceiptGroupToAccounting();
            if ($accounting->generate($this->multiple)) {
                return true;
            }
        }

        if ($this->multiple instanceof SupplierReceiptGroup) {
            $accounting = new SupplierReceiptGroupToAccounting();
            if ($accounting->generate($this->multiple)) {
                return true;
            }
        }

        Tools::log()->warning('accounting-entry-error');
        return false;
    }

    /**
     * Records a payment for each uncollected receipt.
     *
     * @return bool
     */
    private function chargedReceiptPayment(): bool
    {
        foreach ($this->multiple->getReceipts() as $receipt) {
            if ($receipt->pagado) {
                Tools::log()->warning('receipt-already-collected', ['%code%' => $receipt->getCode()]);
                return false;
            }
            $receipt->disablePaymentGeneration();
            $receipt->fechapago = $this->multiple->groupdate;
            $receipt->nick = $this->data['nick'];
            $receipt->pagado = true;
            if (false === $receipt->save()) {
                return false;
            }

            $receipt->disablePaymentGeneration();
            if (false === $this->newPayment($receipt)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Add new payment to the receipt.
     *
     * @param ReciboCliente|ReciboProveedor $receipt
     * @param bool $refund
     * @return bool
     */
    private function newPayment($receipt, bool $refund = false)
    {
        $payment = $this->multiple->getPayment();
        $payment->customid = $this->multiple->id;
        $payment->customstatus = Tools::lang()->trans('multiple-payment');
        $payment->idrecibo = $receipt->idrecibo;
        $payment->fecha = empty($receipt->fechapago) ? $this->multiple->groupdate : $receipt->fechapago;
        $payment->importe = $refund ? (0 - $receipt->importe) : $receipt->importe;
        $payment->nick = $this->data['nick'];
        $payment->disableAccountingGeneration();
        return $payment->save();
    }

    /**
     * removes the receipt payment and optionally removes the multiple payment receipt.
     *
     * @param ReciboCliente|ReciboProveedor $receipt
     * @param int|null $newIdMultiple
     * @return bool
     */
    private function removeReceipt($receipt, $newIdMultiple = null): bool
    {
        if ($receipt->pagado) {
            $this->newPayment($receipt, true);
        }

        $receipt->disablePaymentGeneration();
        $receipt->idmultiple = $newIdMultiple;
        $receipt->fechapago = null;
        $receipt->pagado = false;
        return $receipt->save();
    }

    /**
     * Remove account entry for multiple payment.
     *
     * @return bool
     */
    private function removeReceiptContabilize(): bool
    {
        if (empty($this->multiple->identry)) {
            return true;
        }

        $entry = new Asiento();
        $entry->loadFromCode($this->multiple->identry);
        $entry->editable = true;
        if ($entry->delete()) {
            $this->multiple->identry = null;
            return true;
        }

        return false;
    }

    /**
     * Remove payments for all receipts of the multiple payment.
     *
     * @return bool
     */
    private function removeReceiptPayment(): bool
    {
        foreach ($this->multiple->getReceipts() as $receipt) {
            if (false === $this->removeReceipt($receipt, $this->multiple->id)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Update parent document.
     *
     * @return bool
     */
    private function updateMultiple(int $status)
    {
        $this->multiple->status = $status;
        return $this->multiple->save();
    }
}
