<?php

namespace FacturaScripts\Plugins\FechaEntregaDocumentos;

use FacturaScripts\Core\Base\AjaxForms\SalesHeaderHTML;
use FacturaScripts\Core\Template\InitClass;

class Init extends InitClass
{
    public function init(): void
    {
        SalesHeaderHTML::addMod(new Mod\SalesHeaderHTMLMod());
    }

    public function update(): void {}

    public function uninstall(): void {}
}
