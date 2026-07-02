<?php

/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Extension\Controller;

use Closure;

class ListFacturaCliente
{
    public function createViews(): Closure
    {
        return function () {
            $this->tab('ListFacturaCliente')->addFilterCheckbox('sent-verifactu', 'sent-verifactu', 'vf_sent');
        };
    }
}