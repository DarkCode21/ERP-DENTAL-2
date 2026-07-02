<?php
/**
 * Copyright (C) 2021-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\FacturasCompraUniq;

use FacturaScripts\Core\Base\AjaxForms\PurchasesHeaderHTML;
use FacturaScripts\Core\Template\InitClass;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class Init extends InitClass
{
    public function init(): void
    {
        // extensiones
        $this->loadExtension(new Extension\Model\FacturaProveedor());

        // mods
        PurchasesHeaderHTML::addMod(new Mod\PurchasesHeaderHTMLMod());
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
        // se ejecutará cada vez que se instala o actualiza el plugin.
    }
}
