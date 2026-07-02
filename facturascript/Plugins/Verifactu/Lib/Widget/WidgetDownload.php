<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Widget;

use FacturaScripts\Core\Base\MyFilesToken;
use FacturaScripts\Dinamic\Lib\Widget\WidgetLink;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class WidgetDownload extends WidgetLink
{
    /**
     * @var bool
     */
    public $permanent = false;

    public function __construct($data)
    {
        parent::__construct($data);
        $this->permanent = isset($data['permanent']) && strtolower($data['permanent']) === 'true';
    }

    /**
     * @param string $inside
     * @param string $titleurl
     *
     * @return string
     */
    protected function onclickHtml($inside, $titleurl = ''): string
    {
        // si el archivo no existe, no se muestra el enlace
        if (empty($inside) || !file_exists($inside)) {
            return '-';
        }

        $url = MyFilesToken::getUrl($inside, $this->permanent);
        $class = empty($this->class) ? 'btn btn-info' : $this->class;
        return '<a href="' . $url . '" class="' . $class . '" download><i class="fa-solid fa-download"></i></a>';
    }

    /**
     * @param object $model
     * @param string $display
     *
     * @return string
     */
    public function tableCell($model, $display = 'left')
    {
        $this->setValue($model);
        return '<td class="' . $this->tableCellClass('text-' . $display) . '">'
            . $this->onclickHtml($this->show()) . '</td>';
    }
}