<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Extension\Controller;

use Closure;
use FacturaScripts\Core\Tools;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait CommonEditDocTrait
{
    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            $mvn = $this->getMainViewName();
            if ($viewName !== $mvn || false === $view->model->exists()) {
                return;
            }

            $this->addButton($viewName, [
                'action' => Tools::siteUrl() . '/' . $view->model->url('public-share'),
                'color' => 'info',
                'icon' => 'fas fa-share',
                'label' => 'share',
                'type' => 'link',
                'target' => '_blank',
            ]);
        };
    }
}