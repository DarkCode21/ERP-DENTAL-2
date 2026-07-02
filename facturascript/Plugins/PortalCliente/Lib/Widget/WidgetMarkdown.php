<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Lib\Widget;

use FacturaScripts\Core\Lib\Widget\WidgetTextarea;
use FacturaScripts\Dinamic\Lib\AssetManager;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class WidgetMarkdown extends WidgetTextarea
{

    protected function assets()
    {
        AssetManager::add('css', FS_ROUTE . '/Plugins/PortalCliente/node_modules/easymde/dist/easymde.min.css');
        AssetManager::add('js', FS_ROUTE . '/Plugins/PortalCliente/node_modules/easymde/dist/easymde.min.js');
        AssetManager::add('js', FS_ROUTE . '/Dinamic/Assets/JS/WidgetMarkdown.js');
    }

    /**
     * 
     * @param string $type
     * @param string $extraClass
     *
     * @return string
     */
    protected function inputHtml($type = 'text', $extraClass = 'markdown-editor'): string
    {
        return parent::inputHtml($type, $extraClass);
    }
}
