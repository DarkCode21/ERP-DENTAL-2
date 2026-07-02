<?php
/**
 * Copyright (C) 2019-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\RemesasSEPA\Extension\Model;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CuentaBancoCliente;

/**
 * Description of ReciboCliente
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ReciboCliente
{
    /**
     * Prevents remove receipts inside a remittance.
     */
    public function deleteBefore(): Closure
    {
        return function () {
            if (!empty($this->idremesa)) {
                Tools::log()->warning('receipt-inside-remittance');
                return false;
            }
        };
    }

    /**
     * Returns the customer bank account.
     */
    public function getBankAccount(): Closure
    {
        return function () {
            $bankAccount = new CuentaBancoCliente();
            $where = [new DataBaseWhere('codcliente', $this->codcliente)];
            foreach ($bankAccount->all($where) as $cuenta) {
                if ($cuenta->iban == $this->iban) {
                    return $cuenta;
                }

                if ($cuenta->principal) {
                    $bankAccount->loadFromCode($cuenta->primaryColumnValue());
                }
            }

            return $bankAccount;
        };
    }

    /**
     * Sets IBAN and swift before insert.
     */
    public function saveInsertBefore(): Closure
    {
        return function () {
            if (empty($this->iban) && !$this->pagado) {
                $paymentMethod = $this->getPaymentMethod();
                if ($paymentMethod->codcuentabanco || $paymentMethod->domiciliado) {
                    $this->updateBankAccount();
                }
            }
        };
    }

    /**
     * Updated IBAN and swift values.
     */
    public function updateBankAccount(): Closure
    {
        return function () {
            $bankAccount = $this->getBankAccount();
            $this->iban = $bankAccount->exists() ? $bankAccount->iban : null;
            $this->swift = $bankAccount->exists() ? $bankAccount->swift : null;
        };
    }
}
