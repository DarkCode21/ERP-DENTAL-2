<?php
/**
 * Copyright (C) 2019-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PlantillasPDF;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Plugins\PlantillasPDF\Model\FormatoDocumento;

/**
 * Composer autoload.
 */
require_once __DIR__ . '/vendor/autoload.php';

final class Init extends InitClass
{
    public function init(): void
    {
        $this->loadExtension(new Extension\Controller\EditFormatoDocumento());
        ExportManager::addTool('main', 'AdminPlantillasPDF', 'pdf-templates', 'fas fa-cog');
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
        // configuración predeterminada
        $defaults = [
            'bottommargin' => 20,
            'color1' => '#2770CA',
            'color2' => '#FFFFFF',
            'color3' => '#F1F1F1',
            'endalign' => 'justify',
            'endfontsize' => 10,
            'endtext' => '',
            'font' => 'DejaVuSans',
            'fontcolor' => '#000000',
            'fontsize' => 12,
            'footeralign' => 'center',
            'footerfontsize' => 10,
            'footertext' => '{PAGENO} / {nbpg}',
            'linecols' => 'descripcion,cantidad,pvpunitario,dtopor,pvptotal,iva,recargo,irpf',
            'linecolalignments' => 'left,right,right,right,right,right,right,right',
            'linecoltypes' => 'text,number2,number,percentage,number,percentage1,percentage1,percentage1',
            'linesheight' => 400,
            'logoalign' => 'right',
            'logosize' => 100,
            'orientation' => 'portrait',
            'password' => '',
            'productimageheight' => 50,
            'productimagewidth' => 50,
            'qrbgcolor' => '#FFFFFF',
            'qrcolor' => '#000000',
            'qrsize' => 80,
            'qrtransparent' => 1,
            'size' => 'A4',
            'thankstext' => '',
            'thankstitle' => '',
            'template' => 'Template1',
            'titlefontsize' => 18,
            'topmargin' => 50
        ];
        foreach ($defaults as $key => $value) {
            Tools::settings('plantillaspdf', $key, $value);
        }
        Tools::settingsSave();

        $this->addFormats();
    }

    private function addFormats(): void
    {
        $newFormat = new FormatoDocumento();
        if (false === $newFormat->loadFromCode('', [new DataBaseWhere('nombre', 'sin valorar')])) {
            $newFormat->autoaplicar = false;
            $newFormat->color1 = '#2770CA';
            $newFormat->nombre = 'sin valorar';
            $newFormat->hidetotals = 1;
            $newFormat->linecols = 'referencia,descripcion,cantidad';
            $newFormat->linecolalignments = 'left,left,right';
            $newFormat->linecoltypes = 'text,text,number2';
            $newFormat->save();
        }
    }
}
