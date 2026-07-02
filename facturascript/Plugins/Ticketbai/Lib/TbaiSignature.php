<?php
/**
 * Copyright (C) 2022-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Ticketbai\Lib;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\PDF\PDFCore;
use FacturaScripts\Dinamic\Model\Empresa;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class TbaiSignature
{
    const SIGNATURE_FOLDER = 'Ticketbai';

    public static function getSignatureFile(Empresa $companyModel): string
    {
        // creamos la ruta de destino
        $destiny = Tools::folder('MyFiles', self::SIGNATURE_FOLDER);

        // si la carpeta no existe o no podemos crearla, devolvemos vacío
        if (false === Tools::folderCheckOrCreate($destiny)) {
            return '';
        }

        // comprobamos si el archivo existe
        if (file_exists($destiny . '/' . $companyModel->tbai_signature)) {
            return $destiny . '/' . $companyModel->tbai_signature;
        }

        // obtenemos el nombre del archivo
        $fileName = $companyModel->tbai_signature;
        if (empty($fileName)) {
            return '';
        }

        // comprobamos si el archivo está en MyFiles
        $filePath = FS_FOLDER . '/MyFiles/' . $fileName;
        if (file_exists($filePath)) {
            // lo movemos a la carpeta de Ticketbai
            rename($filePath, $destiny . '/' . $fileName);
            return $destiny . '/' . $fileName;
        }

        return '';
    }

    public static function setSignature(Empresa $companyModel, UploadedFile $uploadFile): bool
    {
        // creamos la ruta de destino
        $destiny = Tools::folder('MyFiles', self::SIGNATURE_FOLDER);

        // si la carpeta no existe o no podemos crearla, devolvemos false
        if (false === Tools::folderCheckOrCreate($destiny)) {
            Tools::log()->warning('Error creating Ticketbai folder');
            return false;
        }

        // eliminamos el archivo antiguo
        if (file_exists($destiny . '/' . $companyModel->tbai_signature)) {
            unlink($destiny . '/' . $companyModel->tbai_signature);
        }

        // formateamos el nombre del archivo manteniendo la extensión
        $fileName = $uploadFile->getClientOriginalName();
        $fileName = preg_replace('/[^a-zA-Z0-9\.\_\-]/', '', $fileName);

        // movemos el archivo a la carpeta
        if (false === $uploadFile->move($destiny, $fileName)->getRealPath()) {
            Tools::log()->warning('Error moving file to Ticketbai folder');
            return false;
        }

        // guardamos en la empresa el nombre del archivo
        $companyModel->tbai_signature = $fileName;
        if (false === $companyModel->save()) {
            Tools::log()->warning('Error saving company model');
            return false;
        }

        return true;
    }

    public static function generateQrCode($model): void
    {
        if (false === isset($model->tbaiurl) ||
            false === isset($model->tbaicodbar) ||
            empty($model->tbaiurl) ||
            empty($model->tbaicodbar)) {
            return;
        }

        $filename = $model->codigo . '_ticketbai.png';
        $folderPath = FS_FOLDER . '/MyFiles/QRcode/';
        $qrFilePath = $folderPath . $filename;
        if (false === is_dir($folderPath)) {
            // dir doesn't exist, make it
            mkdir($folderPath, 0777, true);
        }

        $qrColor = '#000000';
        $qrBgColor = '#FFFFFF';
        list($rColor, $gColor, $bColor) = sscanf($qrColor, "#%02x%02x%02x");
        list($rBgColor, $gBgColor, $bBgColor) = sscanf($qrBgColor, "#%02x%02x%02x");

        // creamos y guardamos el QR code
        $qrCode = QrCode::create($model->tbaiurl)
            ->setSize(400)
            ->setForegroundColor(new Color($rColor, $gColor, $bColor))
            ->setBackgroundColor(new Color($rBgColor, $gBgColor, $bBgColor));

        $writer = new PngWriter();
        $writer->write($qrCode)->saveToFile($qrFilePath);
        if (false === file_exists($qrFilePath)) {
            return;
        }

        // recuperamos la imagen del qr
        $img = imagecreatefrompng($qrFilePath);
        $qrSize = getimagesize($qrFilePath);

        $size = PDFCore::FONT_SIZE * 4;
        $font = FS_FOLDER . '/vendor/rospdf/pdf-php/src/fonts/FreeSerif.ttf';
        // creamos una simulación del texto
        $txt_space = imagettfbbox($size, 0, $font, $model->tbaicodbar);
        // obtenemos ancho y alto del texto
        $txt_width = abs($txt_space[4] - $txt_space[0]);
        $text_height = abs($txt_space[5] - $txt_space[1]) * 2;

        // creamos una imagen base donde unir el qr y el texto
        $base_width = max($txt_width, $qrSize[0]);
        $base_height = $qrSize[0] + $text_height;
        $baseImagen = Imagecreatetruecolor($base_width, $base_height);
        $bgColor = imagecolorallocate($baseImagen, $rBgColor, $gBgColor, $bBgColor);
        imagefill($baseImagen, 0, 0, $bgColor);
        $base_width = imagesx($baseImagen);

        // añadimos y centramos el qr en la base
        $centerQR = abs($base_width - $qrSize[0]) / 2;
        imagecopy($baseImagen, $img, $centerQR, $text_height, 0, 0, $qrSize[0], $qrSize[1]);

        // añadimos y centramos el texto en la base
        $centerText = abs($base_width - $txt_width) / 2;
        $textColor = imagecolorallocate($baseImagen, $rColor, $gColor, $bColor);
        imagettftext($baseImagen, $size, 0, $centerText, $text_height, $textColor, $font, $model->tbaicodbar);

        imagealphablending($baseImagen, true);
        imagesavealpha($baseImagen, true);

        // actualizamos la imagen
        imagepng($baseImagen, $qrFilePath);

        // destruimos las imágenes
        imagedestroy($img);
        imagedestroy($baseImagen);
    }
}
