<?php

namespace FacturaScripts\Plugins\TPVneo\Lib\TPVneo;

use FacturaScripts\Dinamic\Model\Variante;

trait TPVTrait
{
    protected static function getImage(Variante $variant, string $classCSS = 'photo img-fluid'): string
    {
        $img = '';
        $images = $variant->getImages();
        if (empty($images)) {
            return $img;
        }

        $product = $variant->getProducto();
        $file = $images[0]->getFile();
        return '<img src="' . $file->url('download-permanent') . '" class="' . $classCSS . '" loading="lazy" title="' . $product->descripcion . '" alt="' . $variant->referencia . '">';
    }
}