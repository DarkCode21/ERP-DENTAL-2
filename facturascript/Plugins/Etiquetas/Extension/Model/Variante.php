<?php
/**
 * Copyright (C) 2020-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Etiquetas\Extension\Model;

use Closure;
use Com\Tecnick\Barcode\Barcode;
use Exception;
use FacturaScripts\Core\App\AppSettings;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Variante
{
    public function clear(): Closure
    {
        return function () {
            $this->tipocodbarras = 'C128';
        };
    }

    public function generateEAN(): Closure
    {
        return function () {
            $id = $this->idvariante ?? $this->newCode();

            // concatena 200 (código país fake) + 9 dígitos
            $code = '200' . str_pad($id, 9, '0', STR_PAD_LEFT);

            // calculamos los dígitos de control
            $sum = 0;
            $weightFlag = true;
            for ($i = strlen($code) - 1; $i >= 0; $i--) {
                $sum += (int)$code[$i] * ($weightFlag ? 3 : 1);
                $weightFlag = !$weightFlag;
            }
            $code .= (10 - ($sum % 10)) % 10;

            return $code;
        };
    }

    public function getBarcodeImg(): Closure
    {
        return function (int $width = -1, int $height = 15) {
            if (empty($this->codbarras)) {
                return '';
            }

            $folderPath = implode(DIRECTORY_SEPARATOR, [FS_FOLDER, 'MyFiles', 'Public', 'Barcode']);
            if (false === file_exists($folderPath) && false === mkdir($folderPath, 0777, true)) {
                return '';
            }

            $filePath = $folderPath . DIRECTORY_SEPARATOR . urlencode($this->codbarras) . '.png';
            $fileUrl = implode(DIRECTORY_SEPARATOR, ['MyFiles', 'Public', 'Barcode', urlencode($this->codbarras) . '.png']);
            if (file_exists($filePath)) {
                return $fileUrl;
            }

            // instantiate the barcode class
            $generateBarcode = new Barcode();

            // generate a barcode
            $objBarcode = $generateBarcode->getBarcodeObj(
                $this->tipocodbarras ?? 'C128',
                $this->codbarras,
                $width,
                $height,
                'black',
                array(0, 0, 0, 0)
            )->setBackgroundColor('white');

            try {
                file_put_contents($filePath, $objBarcode->getPngData());
            } catch (Exception $exc) {
                return '';
            }

            return $fileUrl;
        };
    }

    public function saveBefore(): Closure
    {
        return function () {
            // si no hay código de barras y está marcado el generar automáticamente, generamos uno
            if (empty($this->codbarras) && AppSettings::get('default', 'autogenbarcodes', 0)) {
                $this->codbarras = $this->generateEAN();
            }
        };
    }
}
