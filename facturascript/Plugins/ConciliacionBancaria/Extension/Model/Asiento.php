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
class Asiento
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

            // comprobamos si el movimiento bancario tiene más asientos
            // si no tiene más asientos marcamos el movimiento bancario como no conciliado
            if (count($bankMovement->getAccountingEntries()) === 0) {
                $bankMovement->reconciled = false;
                $bankMovement->save();
            }
        };
    }
}
