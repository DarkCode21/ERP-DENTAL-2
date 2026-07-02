<?php
/**
 * This file is part of InformeSII plugin for FacturaScripts
 * Copyright (C) 2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\InformeSII;

use FacturaScripts\Core\Base\AjaxForms\PurchasesHeaderHTML;
use FacturaScripts\Core\Base\AjaxForms\SalesHeaderHTML;
use FacturaScripts\Core\Base\InitClass;
use FacturaScripts\Core\Model\Base\BusinessDocument;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Init extends InitClass
{
    public function init()
    {
        // se ejecuta cada vez que carga FacturaScripts (si este plugin está activado).
        $this->loadExtension(new Extension\Controller\ListFacturaCliente());
        $this->loadExtension(new Extension\Controller\ListFacturaProveedor());
        $this->loadExtension(new Extension\Model\Empresa());
        $this->loadExtension(new Extension\Controller\EditEmpresa());

        SalesHeaderHTML::addMod(new Mod\SalesHeaderHTMLMod());
        PurchasesHeaderHTML::addMod(new Mod\PurchasesHeaderHTMLMod());
      
        BusinessDocument::dontCopyField('sii_status');
        BusinessDocument::dontCopyField('sii_sent');
    }

    public function update()
    {
        // se ejecuta cada vez que se instala o actualiza el plugin.
    }
}