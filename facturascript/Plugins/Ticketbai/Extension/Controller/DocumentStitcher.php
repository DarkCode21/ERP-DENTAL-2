<?php
/**
 * Copyright (C) 2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Ticketbai\Extension\Controller;

use Closure;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class DocumentStitcher
{
    public function addBlankLine(): Closure
    {
        return function ($blankLine) {
            $blankLine->tbai_send = false;
        };
    }

    public function addInfoLine(): Closure
    {
        return function ($blankLine) {
            $blankLine->tbai_send = false;
        };
    }
}
