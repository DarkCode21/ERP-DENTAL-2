<?php
/**
 * Copyright (C) 2022-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Etiquetas\Extension\Controller;

use Closure;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditProducto
{
    public function createViews(): Closure
    {
        return function () {
            $viewName = 'Etiquetas';
            $this->addHtmlView($viewName, 'Tab/' . $viewName, 'Producto', 'tags', 'fas fa-barcode');
            $this->setSettings($viewName, 'card', true);
        };
    }

    public function getAvailableTags(): Closure
    {
        return function () {
            $tags = [];
            $mainViewName = $this->getMainViewName();
            foreach ($this->views[$mainViewName]->model->getVariants() as $key => $variant) {
                $tags[$key] = [
                    'reference' => $variant->referencia,
                    'url' => $variant->url(),
                    'quantity' => 1
                ];
            }

            return $tags;
        };
    }
}