<?php
namespace FacturaScripts\Plugins\ColumnaSaldoBalance;

use FacturaScripts\Core\Base\AjaxForms\AccountingLineHTML;
use FacturaScripts\Core\Base\InitClass;

class Init extends InitClass
{
    public function init() {
        // se ejecutara cada vez que carga FacturaScripts (si este plugin está activado).
      	#AccountingLineHTML::addMod(new Mod\AccountingLineHTMLMod());
    }

    public function update() {
        // se ejecutara cada vez que se instala o actualiza el plugin.
    }
}