<?php
/**
 * This file is part of PagosMultiples plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * PagosMultiples Copyright (C) 2020-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\PagosMultiples\Extension\Model;

use Closure;
use FacturaScripts\Core\Tools;

/**
 * Description of ReciboProveedor
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class ReciboProveedor
{

    /**
     * Prevents remove receipts inside a multiple payment.
     */
    public function deleteBefore(): Closure
    {
        return function () {
            if (empty($this->idmultiple)) {
                return true;
            }

            Tools::log()->warning('receipt-inside-multiple-payment');
            return false;
        };
    }

    /**
     * Prevents string empty value into idmultiple.
     */
    public function test(): Closure
    {
        return function () {
            if (empty($this->idmultiple)) {
                $this->idmultiple = null;
            }
            return true;
        };
    }
}
