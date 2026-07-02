<?php
/**
 * Copyright (C) 2020-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\RemesasSEPA\Lib\Accounting;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Cuenta;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Dinamic\Model\Subcuenta;
use FacturaScripts\Plugins\RemesasSEPA\Model\RemesaSEPA;

class RemesaToAccounting
{
    public function generate(RemesaSEPA &$remesa): void
    {
        // si ya tiene asiento, no hacemos nada
        if ($remesa->idasiento) {
            Tools::log()->warning('accounting-entry-exists');
            return;
        }

        // creamos el asiento
        $asiento = new Asiento();
        $asiento->concepto = $remesa->primaryDescription();
        $asiento->fecha = $remesa->fechacargo;
        $asiento->idempresa = $remesa->idempresa;
        $asiento->importe = $remesa->total;
        if (false === $asiento->save()) {
            Tools::log()->warning('accounting-entry-error');
            return;
        }

        // añadimos las líneas de recibos
        foreach ($remesa->getReceipts() as $receipt) {
            if (false === $this->addReceiptLine($asiento, $receipt)) {
                Tools::log()->error('receipt-error-' . $receipt->codigofactura);
                $asiento->delete();
                return;
            }
        }

        // añadimos la línea de la remesa y comprobamos
        if (false === $this->addRemittanceLine($asiento, $remesa)) {
            Tools::log()->error('remittance-error-' . $remesa->idremesa);
            $asiento->delete();
            return;
        }

        // comprobamos si el asiento está descuadrado
        if (false === $asiento->isBalanced()) {
            Tools::log()->warning('mismatched-accounting-entry');
            $asiento->delete();
            return;
        }

        $remesa->idasiento = $asiento->primaryColumnValue();
    }

    /**
     * @param Asiento $asiento
     * @param ReciboCliente $receipt
     *
     * @return bool
     */
    protected function addReceiptLine($asiento, $receipt): bool
    {
        $customer = $receipt->getSubject();
        $account = $customer->getSubcuenta($asiento->codejercicio, true);
        if (false === $account->exists()) {
            return false;
        }

        $newLine = $asiento->getNewLine();
        $newLine->setAccount($account);
        $newLine->concepto = Tools::lang()->trans('customer-payment-concept', ['%document%' => $receipt->getCode()]);
        $newLine->haber = $receipt->importe;
        return $newLine->save();
    }

    /**
     * @param Asiento $asiento
     * @param RemesaSEPA $remesa
     *
     * @return bool
     */
    protected function addRemittanceLine($asiento, $remesa): bool
    {
        $bankLine = $asiento->getNewLine();

        $bankSubaccount = new Subcuenta();
        $where = [
            new DataBaseWhere('codsubcuenta', $remesa->getBankAccount()->codsubcuenta),
            new DataBaseWhere('codejercicio', $asiento->codejercicio)
        ];
        if ($bankSubaccount->loadFromCode('', $where)) {
            $bankLine->setAccount($bankSubaccount);
        } else {
            $altBankSubaccount = $this->getSpecialSubAccount('CAJA', $asiento);
            $bankLine->setAccount($altBankSubaccount);
        }

        $bankLine->debe = $remesa->total;
        return $bankLine->save();
    }

    /**
     * @param string $specialAccount
     * @param Asiento $asiento
     *
     * @return Cuenta
     */
    public function getSpecialAccount(string $specialAccount, $asiento): Cuenta
    {
        $account = new Cuenta();
        $where = [
            new DataBaseWhere('codejercicio', $asiento->codejercicio),
            new DataBaseWhere('codcuentaesp', $specialAccount)
        ];
        $orderBy = ['codcuenta' => 'ASC'];
        $account->loadFromCode('', $where, $orderBy);
        return $account;
    }

    /**
     * @param string $specialAccount
     * @param Asiento $asiento
     *
     * @return Subcuenta
     */
    public function getSpecialSubAccount(string $specialAccount, $asiento): Subcuenta
    {
        $subAccount = new Subcuenta();
        $where = [
            new DataBaseWhere('codejercicio', $asiento->codejercicio),
            new DataBaseWhere('codcuentaesp', $specialAccount)
        ];
        $orderBy = ['codsubcuenta' => 'ASC'];
        if ($subAccount->loadFromCode('', $where, $orderBy)) {
            return $subAccount;
        }

        $account = $this->getSpecialAccount($specialAccount, $asiento);
        foreach ($account->getSubcuentas() as $subc) {
            return $subc;
        }

        return new Subcuenta();
    }
}
