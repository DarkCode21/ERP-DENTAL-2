<?php
/**
 * Copyright (C) 2022-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Ticketbai\Extension\Controller;

use Closure;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use FacturaScripts\Core\Base\MyFilesToken;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\Ticketbai\Lib\TbaiTools;

/**
 * Description of EditFacturaCliente
 *
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditFacturaCliente
{
    protected function annularTbaiAction(): Closure
    {
        return function () {
            $invoice = new FacturaCliente();
            if (false === $invoice->loadFromCode($this->request->query->get('code'))) {
                Tools::log()->warning('invoice-not-found');
                return;
            }

            $invoice->annularTbai();
        };
    }

    protected function createViews(): Closure
    {
        return function () {
            if (false === function_exists('gmp_import')) {
                Tools::log()->warning('php-gmp-not-installed');
                return;
            }

            $company = $this->getModel()->getCompany();
            if (TbaiTools::isBasqueCountryCompany($company)) {
                $this->addHtmlView('ticketbai', 'Tab/Ticketbai', 'FacturaCliente', 'ticketbai', 'fa-solid fa-qrcode');
            }
        };
    }

    protected function downloadXmlTbaiAction(): Closure
    {
        return function () {
            $invoice = new FacturaCliente();
            if (false === $invoice->loadFromCode($this->request->query->get('code'))) {
                Tools::log()->warning('invoice-not-found');
                return;
            }

            if (false === $invoice->signTbai()) {
                return;
            }

            $fileXmlSign = $invoice->url('xml-sign');
            if (false === file_exists($fileXmlSign)) {
                Tools::log()->error('file-not-found');
                return;
            }

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($fileXmlSign) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($fileXmlSign));
            readfile($fileXmlSign);
        };
    }

    protected function execPreviousAction(): Closure
    {
        return function ($action) {
            switch ($action) {
                case 'annular-tbai':
                    $this->annularTbaiAction();
                    break;

                case 'download-xml-tbai':
                    $this->downloadXmlTbaiAction();
                    break;

                case 'sign-send-tbai':
                    $this->signSendTbaiAction();
                    break;
            }
        };
    }

    public function generateQRcode(): Closure
    {
        return function ($dataQRcode) {
            if (empty($dataQRcode)) {
                return '';
            }

            $QRcode = QrCode::create($dataQRcode)
                ->setSize(300)
                ->setForegroundColor(new Color(0, 0, 0))
                ->setBackgroundColor(new Color(255, 255, 255));

            $writer = new PngWriter();
            $result = $writer->write($QRcode);
            return $result->getDataUri();
        };
    }

    public function getTbaiFiles(): Closure
    {
        return function (): array {
            $invoice = new FacturaCliente();
            if (false === $invoice->loadFromCode($this->request->get('code'))) {
                return [];
            }

            $files = [];

            // leemos los archivos de la carpeta
            $tmpFolder = $invoice->url('files-tmp');

            // si el directorio no existe, terminamos
            if (false === file_exists($tmpFolder)) {
                return [];
            }

            foreach (Tools::folderScan($tmpFolder) as $file) {
                $filePath = $tmpFolder . $file;
                $files[] = [
                    'name' => basename($file),
                    'url' => $filePath . '?myft=' . MyFilesToken::get($filePath, false),
                ];
            }

            return $files;
        };
    }

    protected function signSendTbaiAction(): Closure
    {
        return function () {
            $invoice = new FacturaCliente();
            if (false === $invoice->loadFromCode($this->request->query->get('code'))) {
                Tools::log()->warning('invoice-not-found');
                return;
            }

            if (false === $invoice->signSendTbai(true)) {
                Tools::log()->error('record-save-error');
                return;
            }

            Tools::log()->notice('record-updated-correctly');
        };
    }
}
