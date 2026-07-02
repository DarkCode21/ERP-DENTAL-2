<?php

namespace FacturaScripts\Plugins\IeSepaMandato\Extension\Model;

use Closure;

class CuentaBancoCliente
{
    public $mandato;
    public $codcliente;

    public function saveBefore(): Closure
    {
        return function() {
            if (empty($this->mandato)){
                $this->mandato = 'Mandato-' . $this->codcliente;
            }
            
        };
    }
}

