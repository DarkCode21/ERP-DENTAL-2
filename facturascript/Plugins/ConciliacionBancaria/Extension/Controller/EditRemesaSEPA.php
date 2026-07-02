<?php
/**
 * Copyright (C) 2023-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\ConciliacionBancaria\Extension\Controller;

use Closure;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Plugins\ConciliacionBancaria\Model\MovimientoBanco;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditRemesaSEPA
{
    public function execPreviousAction(): Closure
    {
        return function ($action) {
            if ($action === 'remove-receipts') {
                $this->removeReceiptsBankMovementAction();
            }
        };
    }

    protected function removeReceiptsBankMovementAction(): Closure
    {
        return function () {
            $codes = $this->request->request->get('code', []);
            if (false === is_array($codes)) {
                return;
            }

            foreach ($codes as $code) {
                // comprobamos si existe el recibo
                $receipt = new ReciboCliente();
                if (false === $receipt->loadFromCode($code)) {
                    continue;
                }

                // comprobamos si el recibo tiene movimiento bancario
                $bankMovement = new MovimientoBanco();
                if (false === $bankMovement->loadFromCode($receipt->idbankmovement)) {
                    continue;
                }

                // eliminamos el movimiento bancario
                $receipt->idbankmovement = null;
                $receipt->save();
            }
        };
    }
}
