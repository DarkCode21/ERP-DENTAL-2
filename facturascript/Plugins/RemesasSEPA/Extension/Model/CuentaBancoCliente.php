<?php
/**
 * Copyright (C) 2020-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\RemesasSEPA\Extension\Model;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\ReciboCliente;

/**
 * Description of CuentaBancoCliente
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class CuentaBancoCliente
{

    /**
     * Updates pending customer receipts when customer bank account is updated.
     */
    public function save(): Closure
    {
        return function () {
            $receiptModel = new ReciboCliente();
            $where = [
                new DataBaseWhere('codcliente', $this->codcliente),
                new DataBaseWhere('pagado', false)
            ];
            foreach ($receiptModel->all($where, [], 0, 0) as $receipt) {
                $paymentMethod = $receipt->getPaymentMethod();
                if (!empty($receipt->iban) || $paymentMethod->codcuentabanco || $paymentMethod->domiciliado) {
                    $receipt->updateBankAccount();
                    $receipt->save();
                }
            }
        };
    }
}
