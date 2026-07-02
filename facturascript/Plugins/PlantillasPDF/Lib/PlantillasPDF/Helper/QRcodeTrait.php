<?php
/**
 * Copyright (C) 2022-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PlantillasPDF\Lib\PlantillasPDF\Helper;

use Mpdf\QrCode\Output;
use Mpdf\QrCode\QrCode;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait QRcodeTrait
{
    /** @var string */
    protected $qrFilePath = '';

    protected function createQR(): bool
    {
        if (empty($this->headerModel)) {
            return false;
        }

        if (false === empty($this->qrFilePath)) {
            return true;
        }

        $filename = $this->headerModel->codigo . '_' . $this->get('qrfield') . '.png';
        $folderPath = FS_FOLDER . '/MyFiles/QRcode/';
        $this->qrFilePath = $folderPath . $filename;

        if (false === is_dir($folderPath)) {
            // dir doesn't exist, make it
            mkdir($folderPath, 0777, true);
        }

        switch ($this->get('qrfield')) {
            case 'ticketbai':
                return $this->createQRTicketbai();

            default:
                return $this->createQRDefault();
        }
    }

    protected function createQRDefault(string $value = ''): bool
    {
        if (empty($value) && (false === isset($this->headerModel->{$this->get('qrfield')}) || empty($this->headerModel->{$this->get('qrfield')}))) {
            return false;
        } elseif (empty($value) && isset($this->headerModel->{$this->get('qrfield')})) {
            $value = $this->headerModel->{$this->get('qrfield')};
        }

        if (empty($value)) {
            return false;
        }

        // creamos el qr normal
        $qrCode = new QrCode($value, 'M');
        $qrCode->disableBorder();
        $qrcolor = $this->get('qrcolor') ?? '#000000';
        $qrbgcolor = $this->get('qrbgcolor') ?? '#FFFFFF';
        list($rColor, $gColor, $bColor) = sscanf($qrcolor, "#%02x%02x%02x");
        list($rBgColor, $gBgColor, $bBgColor) = sscanf($qrbgcolor, "#%02x%02x%02x");
        $qrsize = $this->get('qrsize') ?? 75;

        $output = new Output\Png();
        $data = $output->output($qrCode, $qrsize, [$rBgColor, $gBgColor, $bBgColor], [$rColor, $gColor, $bColor]);

        // guardamos el qr
        if (false === file_put_contents($this->qrFilePath, $data)) {
            return false;
        }

        if ($this->get('qrtransparent')) {
            // si el modo transparencia está habilitado eliminamos el fondo
            $im = imagecreatefrompng($this->qrFilePath);
            $rmBgColor = imagecolorallocate($im, $rBgColor, $gBgColor, $bBgColor);
            imagecolortransparent($im, $rmBgColor);

            // actualizamos la imagen
            imagepng($im, $this->qrFilePath);

            // destruimos la imagen
            imagedestroy($im);
        }

        return true;
    }

    protected function createQRTicketbai(): bool
    {
        if (false === isset($this->headerModel->tbaiurl) || false === isset($this->headerModel->tbaicodbar)
            || empty($this->headerModel->tbaiurl) || empty($this->headerModel->tbaicodbar)) {
            return false;
        }

        $this->createQRDefault($this->headerModel->tbaiurl);

        if (false === file_exists($this->qrFilePath)) {
            return false;
        }

        $qrcolor = $this->get('qrcolor') ?? '#000000';
        $qrbgcolor = $this->get('qrbgcolor') ?? '#FFFFFF';
        list($rColor, $gColor, $bColor) = sscanf($qrcolor, "#%02x%02x%02x");
        list($rBgColor, $gBgColor, $bBgColor) = sscanf($qrbgcolor, "#%02x%02x%02x");

        // recuperamos la imagen del qr
        $img = imagecreatefrompng($this->qrFilePath);
        $qrsize = getimagesize($this->qrFilePath);

        // convertimos los pixels del texto en puntos
        $size = (72 / 96) * $this->get('fontsize');
        $font = FS_FOLDER . '/Plugins/PlantillasPDF/vendor/mpdf/mpdf/ttfonts/' . $this->get('font') . '.ttf';

        // creamos una simulación del texto
        $txt_space = imagettfbbox($size, 0, $font, $this->headerModel->tbaicodbar);

        // obtenemos ancho y alto del texto
        $txt_width = abs($txt_space[4] - $txt_space[6]);
        $text_height = abs($txt_space[5] - $txt_space[3]);

        // creamos una imagen base donde unir el qr y el texto
        $base_width = max($txt_width, $qrsize[0]);
        $base_height = $qrsize[0] + ($text_height * 2);
        $baseimagen = Imagecreatetruecolor($base_width, $base_height);
        $bgcolor = imagecolorallocate($baseimagen, $rBgColor, $gBgColor, $bBgColor);
        imagefill($baseimagen, 0, 0, $bgcolor);

        if ($this->get('qrtransparent')) {
            // si el modo transparencia está habilitado eliminamos el fondo de la base
            imagesavealpha($baseimagen, true);
            $trans_background = imagecolorallocatealpha($baseimagen, $rBgColor, $gBgColor, $bBgColor, 127);
            imagefill($baseimagen, 0, 0, $trans_background);
        }

        // añadimos y centramos el qr en la base
        $centerQR = abs($base_width - $qrsize[0]) / 2;
        imagecopy($baseimagen, $img, $centerQR, $text_height * 2, 0, 0, $qrsize[0], $qrsize[0]);

        // añadimos y centramos el texto en la base
        $centerText = abs($base_width - $txt_width) / 2;
        $textColor = imagecolorallocate($baseimagen, $rColor, $gColor, $bColor);
        imagettftext($baseimagen, $size, 0, $centerText, $text_height, $textColor, $font, $this->headerModel->tbaicodbar);

        // actualizamos la imagen
        imagepng($baseimagen, $this->qrFilePath);

        // destruimos las imágenes
        imagedestroy($img);
        imagedestroy($baseimagen);

        return true;
    }

    protected function getQRcode(bool $onlyURL = false): string
    {
        if (false === $this->createQR()) {
            $this->qrFilePath = '';
            return '';
        }

        if (false === empty($this->qrFilePath) && file_exists($this->qrFilePath)) {
            if ($onlyURL) {
                return $this->qrFilePath;
            }
            return '<img src="' . $this->qrFilePath . '">';
        }

        return '';
    }
}