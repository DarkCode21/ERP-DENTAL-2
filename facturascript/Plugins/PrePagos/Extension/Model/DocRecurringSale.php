<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PrePagos\Extension\Model;

use Closure;

class DocRecurringSale
{
    public function getPayments(): Closure
    {
        return function () {
            return [];
        };
    }
}
