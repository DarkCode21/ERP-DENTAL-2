<?php
/**
 * Copyright (C) 2021-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Facturae;

use FacturaScripts\Core\Template\InitClass;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class Init extends InitClass
{
    public function init(): void
    {
        $this->loadExtension(new Extension\Controller\EditFacturaCliente());
        $this->loadExtension(new Extension\Model\FacturaCliente());
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
    }
}
