<?php
/**
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\AtributosEmpresa;

use FacturaScripts\Core\Base\InitClass;
use FacturaScripts\Dinamic\Model\Empresa;

/**
 * @author Erick Lizana <ericklizana12@gmail.com>
 */
class Init extends InitClass
{
    public function init()
    {
        // se ejecutará cada vez que carga FacturaScripts (si este plugin está activado).
        $this->loadExtension(new Extension\Model\Empresa());
    }

    public function update()
    {
        // se ejecutará cada vez que se instala o actualiza el plugin.
    }

}