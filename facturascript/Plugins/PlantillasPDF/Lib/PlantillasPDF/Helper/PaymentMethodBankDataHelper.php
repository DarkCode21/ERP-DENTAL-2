<?php
/**
 * Copyright (C) 2022-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PlantillasPDF\Lib\PlantillasPDF\Helper;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\ExtensionsTrait;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CuentaBanco;
use FacturaScripts\Dinamic\Model\CuentaBancoCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FormaPago;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PaymentMethodBankDataHelper
{
    use ExtensionsTrait;

    /**
     * @param BusinessDocument|FacturaCliente $model
     */
    public static function get($model): string
    {
        $payMethod = new FormaPago();
        if (false === $payMethod->loadFromCode($model->codpago)) {
            return '-';
        }

        $pipe = new self();
        $return = $pipe->pipe('get', $model, $payMethod);
        if (null !== $return) {
            return $return;
        }

        $cuentaBcoCli = new CuentaBancoCliente();
        $where = [new DataBaseWhere('codcliente', $model->codcliente)];
        if ($payMethod->domiciliado && $cuentaBcoCli->loadFromCode('', $where, ['principal' => 'DESC'])) {
            $bankClient = $payMethod->descripcion
                . '<br/>' . $cuentaBcoCli->getIban(true, true);

            if (false === empty($cuentaBcoCli->swift)) {
                $bankClient .= '<br/>' . Tools::lang()->trans('swift') . ': ' . $cuentaBcoCli->swift;
            }

            return $bankClient;
        }

        $cuentaBco = new CuentaBanco();
        if (empty($payMethod->codcuentabanco) ||
            false === $cuentaBco->loadFromCode($payMethod->codcuentabanco) ||
            empty($cuentaBco->iban)) {
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
