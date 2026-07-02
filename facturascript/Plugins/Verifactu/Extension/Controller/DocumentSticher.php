<?php
/**
 * Copyright (C) 2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Extension\Controller;

use Closure;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class DocumentSticher
{
    public function addBlankLine(): Closure
    {
        return function ($blankLine) {
            $blankLine->vf_send = false;
        };
    }

    public function addInfoLine(): Closure
    {
        return function ($blankLine) {
            $blankLine->vf_send = false;
        };
    }
}
