<?php
/**
 * Copyright (C) 2022-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PlantillasPDF\Lib\PlantillasPDF\Helper;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\ExtensionsTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CuentaBanco;
use FacturaScripts\Dinamic\Model\CuentaBancoCliente;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\ReciboCliente;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ReceiptBankDataHelper
{
    use ExtensionsTrait;

    public static function get(ReciboCliente $receipt, array $receipts): string
    {
        // buscamos si la forma de pago es igual en todos los recibos
        $paymentEqual = 0;
        foreach ($receipts as $r) {
            if ($receipt->codpago == $r->codpago && $receipt->idrecibo != $receipts[0]->idrecibo) {
                $paymentEqual++;
            }
        }

        // si es igual en todos los recibos, mostramos la forma de pago solo en el 1º recibo
        if (count($receipts) > 0 && count($receipts) == $paymentEqual) {
            return '';
        }

        $payMethod = new FormaPago();
        if (false === $payMethod->loadFromCode($receipt->codpago)) {
            return '-';
        }

        $pipe = new self();
        $return = $pipe->pipe('get', $payMethod, $receipt, $receipts);
        if (null !== $return) {
            return $return;
        }

        $cuentaBcoCli = new CuentaBancoCliente();
        $where = [new DataBaseWhere('codcliente', $receipt->codcliente)];
        if ($payMethod->domiciliado && $cuentaBcoCli->loadFromCode('', $where, ['principal' => 'DESC'])) {
            $bankClient = $payMethod->descripcion
                . '<br/>' . $cuentaBcoCli->getIban(true, true);

            if (false === empty($cuentaBcoCli->swift)) {
                $bankClient .= '<br/>' . Tools::lang()->trans('swift') . ': ' . $cuentaBcoCli->swift;
            }

            return $bankClient;
        }

        $cuentaBco = new CuentaBanco();
        if (empty($payMethod->codcuentabanco) || false === $cuentaBco->loadFromCode($payMethod->codcuentabanco) || empty($cuentaBco->iban)) {
            return $payMethod->descripcion;
        }

        $bank = $payMethod->descripcion
            . '<br/>' . Tools::lang()->trans('iban') . ': ' . $cuentaBco->getIban(true);

        if (false === empty($cuentaBco->swift)) {
            $bank .= '<br/>' . Tools::lang()->trans('swift') . ': ' . $cuentaBco->swift;
        }

        return $bank;
    }
}
