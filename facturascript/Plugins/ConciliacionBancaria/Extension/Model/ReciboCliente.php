<?php
/**
 * Copyright (C) 2023-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\ConciliacionBancaria\Extension\Model;

use Closure;
use FacturaScripts\Dinamic\Model\MovimientoBanco;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ReciboCliente
{
    public function onDelete(): Closure
    {
        return function () {
            $this->updateBankMovement();
        };
    }

    public function onUpdate(): Closure
    {
        return function () {
            $this->updateBankMovement();
        };
    }

    public function setPreviousData(): Closure
    {
        return function () {
            $this->previousData['idbankmovement'] = $this->idbankmovement ?? null;
        };
    }

    public function updateBankMovement(): Closure
    {
        return function () {
            // comprobamos si el recibo tiene movimiento bancario
            $bankMovement = new MovimientoBanco();
            if (false === $bankMovement->loadFromCode($this->previousData['idbankmovement'])) {
                return;
            }

            // comprobamos si el movimiento bancario tiene más recibos
            // si no tiene más recibos marcamos el movimiento bancario como no conciliado
            if (count($bankMovement->getReceipts()) === 0) {
                $bankMovement->reconciled = false;
                $bankMovement->save();
            }
        };
    }
}
