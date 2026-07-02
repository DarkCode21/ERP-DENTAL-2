<?php
/**
 * Copyright (C) 2023 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\Traducciones\Lib\Widget;

use FacturaScripts\Core\Lib\Widget\BaseWidget;
use FacturaScripts\Dinamic\Model\AttachedFile;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class WidgetImage extends BaseWidget
{
    /** @var string */
    public $width;

    /** @var string */
    public $height;

    public function __construct($data)
    {
        parent::__construct($data);
        $this->width = $data['width'] ?? 'auto';
        $this->height = $data['height'] ?? 'auto';
    }

    protected function show(): string
    {
        $file = new AttachedFile();
        if (empty($this->value)
            || false === $file->loadFromCode($this->value)
            || 'image' !== substr($file->mimetype, 0, 5)) {
            return '';
        }

        return '<img src="' . $file->url('download') . '" height="' . $this->height . '" width="' . $this->width . '" />';
    }
}