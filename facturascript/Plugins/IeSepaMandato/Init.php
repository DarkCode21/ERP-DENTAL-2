<?php

namespace FacturaScripts\Plugins\IeSepaMandato;
use FacturaScripts\Core\Template\InitClass;

final class Init extends InitClass
{
    public function init(): void
    {
        $this->loadExtension(new Extension\Controller\EditCliente());
        $this->loadExtension(new Extension\Model\CuentaBancoCliente());
    }

    public function update(): void
    {
    }

    public function uninstall(): void
    {
    }

}
