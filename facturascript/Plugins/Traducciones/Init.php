<?php
/**
 * Copyright (C) 2023 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\Traducciones;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\InitClass;
use FacturaScripts\Plugins\Traducciones\Lib\LanguageTrait;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Init extends InitClass
{
    public function init()
    {
        // se ejecuta cada vez que carga FacturaScripts (si este plugin está activado).
        $this->loadExtension(new Extension\Controller\EditPais());
        $this->loadExtension(new Extension\Controller\EditUser());
    }

    public function update()
    {
        // se ejecuta cada vez que se instala o actualiza el plugin.
        $this->initLanguages();
    }

    protected function initLanguages()
    {
        $db = new DataBase();

        // si la tabla "languages" existe, terminamos
        if ($db->tableExists('languages')) {
            return;
        }

        // si no existe, pre-cargamos los idiomas por defecto
        LanguageTrait::copyNewLanguages();
    }
}