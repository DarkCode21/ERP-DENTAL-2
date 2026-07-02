<?php
/**
 * Copyright (C) 2022-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Ticketbai\Lib\PDF;

use FacturaScripts\Core\Lib\PDF\PDFDocument as parentClass;
use FacturaScripts\Plugins\Ticketbai\Lib\TbaiSignature;

abstract class PDFDocument extends parentClass
{
    protected function insertBusinessDocBody($model)
    {
        parent::insertBusinessDocBody($model);
        if ($model->modelClassName() !== 'FacturaCliente') {
            return;
        }

        TbaiSignature::generateQrCode($model);
        $filename = $model->codigo . '_ticketbai.png';
        $folderPath = FS_FOLDER . '/MyFiles/QRcode/';
        $qrFilePath = $folderPath . $filename;
        if (false === file_exists($qrFilePath)) {
            return;
        }

        $qrSize = getimagesize($qrFilePath);
        $this->pdf->ezImage($qrFilePath, 0, round($qrSize[0] / 4), 'width', 'right');
    }
}
