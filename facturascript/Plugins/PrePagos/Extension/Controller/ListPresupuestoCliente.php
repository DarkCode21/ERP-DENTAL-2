<?php
/**
 * Copyright (C) 2023-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PrePagos\Extension\Controller;

use Closure;

class ListPresupuestoCliente
{
    use PrePagoCliListControllerTrait;

    public function createViews(): Closure
    {
        return function () {
            $this->listView('ListPresupuestoCliente')
                ->addFilterNumber('totalPending-gt', 'total-pending', 'total_pending', '>=')
                ->addFilterNumber('totalPending-lt', 'total-pending', 'total_pending', '<=');

            $this->createViewPrePago();
        };
    }
}
