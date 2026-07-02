<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\RemesasSEPA\Extension\Model;

class PagoCliente
{
    public function saveBefore()
    {
        return function () {
            // desactivamos la generación del asiento si el pago es de una remesa
            if ($this->getReceipt()->idremesa) {
                $this->disableAccountingGeneration(true);
            }
        };
    }
}
