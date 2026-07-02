<?php

namespace FacturaScripts\Plugins\PreciosConImpuestos\Extension\Model;

use Closure;
use FacturaScripts\Core\Model\Producto;
use FacturaScripts\Core\Model\Impuesto;

/**
 * @author Pedro Javier López Sánchez <pedro@takeonme.es>
 */

class Variante
{

    public function saveBefore(): Closure
    {
        return function () {
            $modelProducto = new Producto();
            $producto = $modelProducto->get($this->idproducto);

            $modelImpuesto = new Impuesto();
            $impuesto = $modelImpuesto->get($producto->codimpuesto);

            $old_variante = $this->get($this->idvariante);

            if(!$old_variante){
                $precio = $this->precio;
                $pricewithtaxes = $this->pricewithtaxes;

                if(!empty($this->precio)){
                    $mountiva = ($this->precio / 100) * $impuesto->iva;
                    $pricewithtaxes = $this->precio + $mountiva;
                    $precio = $this->precio;
                }

                if(!empty($this->pricewithtaxes)){
                    $divisor = 1 + ($impuesto->iva /100);
                    $precio = $this->pricewithtaxes / $divisor;
                    $pricewithtaxes = $this->pricewithtaxes;
                }

                $this->precio = $precio;
                $this->pricewithtaxes = $pricewithtaxes;
            }else{
                if($old_variante->precio != $this->precio){
                    $mountiva = ($this->precio / 100) * $impuesto->iva;
                    $this->pricewithtaxes = $this->precio + $mountiva;
                }
    
                if($old_variante->pricewithtaxes != $this->pricewithtaxes OR empty($old_variante->pricewithtaxes)){
                    $divisor = 1 + ($impuesto->iva /100);
                    $this->precio = $this->pricewithtaxes / $divisor;
                }
            }
        };
    }
}