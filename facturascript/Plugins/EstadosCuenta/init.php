<?php

namespace FacturaScripts\Plugins\EstadosCuenta;

class Init extends \FacturaScripts\Core\Base\InitClass
{
    public function init()
    {
        $this->loadExtension(new Extension\Model\ReciboCliente());
    }
    public function update()
    {
        //se ejecuta al actualizar el plugin
    }
}