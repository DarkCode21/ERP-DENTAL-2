<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PrePagos\Extension\Controller;

use Closure;

class ListAlbaranProveedor
{
    use PrePagoProvListControllerTrait;

    public function createViews(): Closure
    {
        return function () {
            $this->listView('ListAlbaranProveedor')
                ->addFilterNumber('totalPending-gt', 'total-pending', 'total_pending', '>=')
                ->addFilterNumber('totalPending-lt', 'total-pending', 'total_pending', '<=');

            $this->createViewPrePago();
        };
    }
}
