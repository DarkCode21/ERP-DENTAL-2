<?php

namespace FacturaScripts\Plugins\PreciosConImpuestos;

/**
 * @author Pedro Javier López Sánchez <pedro@takeonme.es>
 */

class Init extends \FacturaScripts\Core\Base\InitClass
{
    public function init() {
        $this->loadExtension(new Extension\Model\Variante());
    }

    public function update() {
    }

}