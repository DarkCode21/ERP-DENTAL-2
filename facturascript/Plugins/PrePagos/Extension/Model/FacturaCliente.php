<?php
/**
 * Copyright (C) 2023-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PrePagos\Extension\Model;

use Closure;
use FacturaScripts\Dinamic\Lib\Accounting\PrePagoCliToAccounting;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class FacturaCliente
{
    public function delete(): Closure
    {
        return function () {
            // obtenemos los documentos padres
            $parents = $this->parentDocuments();
            if (empty($parents)) {
                return;
            }

            // marcamos los pagos como no copiados
            foreach ($parents as $parent) {
                foreach ($parent->getPayments() as $payment) {
                    $payment->copied = false;
                    $payment->save();
                }
            }

            PrePagoCliToAccounting::regenerate($parents);
        };
    }

    public function getPayments(): Closure
    {
        return function () {
            return [];
        };
    }
}
