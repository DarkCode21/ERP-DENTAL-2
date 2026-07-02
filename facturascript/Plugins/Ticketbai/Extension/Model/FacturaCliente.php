<?php
/**
 * Copyright (C) 2022-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Ticketbai\Extension\Model;

use Barnetik\Tbai\Api;
use Barnetik\Tbai\Api\Araba\Endpoint as ArabaEndpoint;
use Barnetik\Tbai\Api\Bizkaia\Endpoint as BizkaiaEndpoint;
use Barnetik\Tbai\Api\Bizkaia\IncomeTax\Collection;
use Barnetik\Tbai\Api\Gipuzkoa\Endpoint as GipuzkoaEndpoint;
use Barnetik\Tbai\Fingerprint\Vendor;
use Barnetik\Tbai\PrivateKey;
use Barnetik\Tbai\Qr;
use Barnetik\Tbai\TicketBai;
use Barnetik\Tbai\TicketBaiCancel;
use Closure;
use DOMDocument;
use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\MyFilesToken;
use FacturaScripts\Core\DataSrc\Paises;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\FiscalNumberValidator;
use FacturaScripts\Dinamic\Lib\ProductType;
use FacturaScripts\Dinamic\Model\CodigoIae;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\EstadoDocumento;
use FacturaScripts\Dinamic\Model\IaeEmpresa;
use FacturaScripts\Plugins\Ticketbai\Lib\TbaiTools;
use FacturaScripts\Plugins\Ticketbai\Lib\TbaiSignature;

/**
 * @author Carlos Garcia Gomez                  <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez             <hola@danielfg.es>
 * @author Alayn Gortazar Huete - Barnetik Koop <alayn@barnetik.com>
 */
class FacturaCliente
{
    /** @var array */
    protected $tbaiJson = [];

    public function annularTbai(): Closure
    {
        return function (): bool {
            // si la empresa no es del país vasco, terminamos
            $company = $this->getCompany();
            if (false === TbaiTools::isBasqueCountryCompany($company)) {
                Tools::log()->warning('no-basque-country-company', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            } elseif (false === TbaiTools::checkCompanyLicense($company)) {
                Tools::log()->warning('missing-ticketbai-company', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            } elseif (empty($this->tbai_sent_date)) {
                // comprobamos que la factura esté enviada
                Tools::log()->warning('invoice-not-signed', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            } elseif ($this->tbai_canceled) {
                // comprobamos que la factura no esté anulada
                Tools::log()->warning('invoice-already-annulled', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            // obtenemos el archivo de firma
            $fileXmlSign = $this->url('xml-sign');
            if (false === file_exists($fileXmlSign)) {
                Tools::log()->warning('ticketbai-xml-sign-not-found', [
                    '%file%' => $fileXmlSign,
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            // creamos el fichero de cancelación xml
            $fileXmlCancel = $this->url('xml-cancel');
            $this->deleteXmlTbai($fileXmlCancel);
            if (false === touch($fileXmlCancel)) {
                Tools::log()->warning('cannot-create-file', [
                    '%file%' => $fileXmlCancel,
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            try {
                $debugMode = $company->tbai_debugmode;

                // seleccionamos el endpoint según el territorio
                switch (TbaiTools::getTerritory($company)) {
                    case '01':
                        $endpoint = new ArabaEndpoint($debugMode, $debugMode);
                        $ticketbai = TicketBai::createFromXml(
                            file_get_contents($fileXmlSign),
                            Ticketbai::TERRITORY_ARABA,
                            (bool)$company->personafisica
                        );
                        break;

                    case '02':
                        $endpoint = new BizkaiaEndpoint($debugMode, $debugMode);
                        $ticketbai = TicketBai::createFromXml(
                            file_get_contents($fileXmlSign),
                            Ticketbai::TERRITORY_BIZKAIA,
                            (bool)$company->personafisica
                        );
                        break;

                    case '03':
                        $endpoint = new GipuzkoaEndpoint($debugMode, $debugMode);
                        $ticketbai = TicketBai::createFromXml(
                            file_get_contents($fileXmlSign),
                            Ticketbai::TERRITORY_GIPUZKOA,
                            (bool)$company->personafisica
                        );
                        break;

                    default:
                        Tools::log()->warning('ticketbai-endpoint-not-found', [
                            'model-code' => $this->primaryColumnValue(),
                            'model-class' => $this->modelClassName(),
                        ]);
                        unlink($fileXmlCancel);
                        return false;
                }

                // cargamos la firma de la empresa
                $signatureFile = TbaiSignature::getSignatureFile($company);
                if (false === file_exists($signatureFile)) {
                    Tools::log()->warning('ticketbai-signature-file-not-exists', [
                        '%file%' => $signatureFile,
                        'model-code' => $this->primaryColumnValue(),
                        'model-class' => $this->modelClassName(),
                    ]);
                    return false;
                }

                // firmamos el fichero de cancelación xml
                $tbaiPrivateKey = PrivateKey::p12($signatureFile);
                $ticketbaiCancel = TicketBaiCancel::createForTicketBai($ticketbai);
                $ticketbaiCancel->sign($tbaiPrivateKey, $company->tbai_password, $fileXmlCancel);
                $response = $endpoint->cancelInvoice($ticketbaiCancel, $tbaiPrivateKey, $company->tbai_password);
            } catch (Exception $e) {
                $this->deleteXmlTbai($fileXmlCancel);
                Tools::log()->warning('tbai-annular-error', [
                    '%error%' => $e->getMessage(),
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            // obtener errores de la respuesta
            $errorData = $response->errorDataRegistry();
            $messageError = TbaiTools::getErrorToUTF8($response->mainErrorMessage());

            // mostramos los posibles mensajes de errores o advertencias
            TbaiTools::logMessageErros($this, $errorData, $messageError);

            // respuesta de la llamada al servicio, si no es correcta, terminamos
            if ($response->status() != 200) {
                $this->deleteXmlTbai($fileXmlCancel);
                Tools::log()->error('ticketbai-anullar-response-not-ok', [
                    '%status%' => $messageError,
                    '%code%' => $response->status(),
                    '%message%' => $response->content(),
                    '%error%' => $errorData,
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            // guardamos el fichero enviado
            $fileSent = $endpoint->debugData(Api::DEBUG_SENT_FILE);
            $fileSentName = Tools::folder(TbaiTools::FILE_PATH_TBAI_TMP, $this->idfactura, $this->codigo . '-annulled-sent-' . date('YmdHis') . '.xml');
            if (false === copy($fileSent, $fileSentName)) {
                Tools::log()->info('ticketbai-annulled-sent-file-not-copy', [
                    '%file%' => $fileSentName,
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
            }

            // si es Bizkaia debemos descomprimir el archivo enviado y guardarlo de nuevo
            if (TbaiTools::getTerritory($company) === '02') {
                $fileSentContent = gzdecode(file_get_contents($fileSentName));

                $dom = new DOMDocument();
                $dom->loadXML($fileSentContent);
                $ticketbaiContent = $dom->getElementsByTagName('AnulacionTicketBai')->item(0)->textContent;

                if (false === file_put_contents($fileSentName, base64_decode($ticketbaiContent))) {
                    Tools::log()->info('ticketbai-annulled-sent-file-not-decompress', [
                        '%file%' => $fileSentName,
                        'model-code' => $this->primaryColumnValue(),
                        'model-class' => $this->modelClassName(),
                    ]);
                }
            }

            // guardamos la respuesta
            $fileResponseName = Tools::folder(TbaiTools::FILE_PATH_TBAI_TMP, $this->idfactura, $this->codigo . '-annulled-response-' . date('YmdHis') . '.json');
            $data = [$response->headers(), $response->content()];
            if (false === file_put_contents($fileResponseName, json_encode($data, JSON_PRETTY_PRINT))) {
                Tools::log()->info('ticketbai-annulled-response-file-not-copy', [
                    '%file%' => $fileResponseName,
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
            }

            // comprobamos si la entrega es correcta
            if (false === $response->isDelivered()) {
                $this->deleteXmlTbai($fileXmlCancel);
                Tools::log()->warning('tbai-annular-delivered-error', [
                    '%error%' => $response->mainErrorMessage(),
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            // comprobamos si la respuesta es correcta
            if (false === $response->isCorrect()) {
                Tools::log()->warning('tbai-annular-correct-error', [
                    '%error%' => $response->mainErrorMessage(),
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
            }

            $this->tbai_canceled = true;
            $this->tbai_canceled_date = Tools::dateTime();
            if (false === $this->save()) {
                Tools::log()->warning('invoice-annulled-but-not-saved', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            Tools::log()->notice('invoice-annulled-correctly', [
                'model-code' => $this->primaryColumnValue(),
                'model-class' => $this->modelClassName(),
            ]);
            return true;
        };
    }

    public function deleteBefore(): Closure
    {
        return function () {
            if (false === empty($this->tbai_sent_date)) {
                Tools::log()->warning('ticketbai-delete-before');
                return false;
            }
        };
    }

    public function getPreviousInvoice(): Closure
    {
        return function (Empresa $company) {
            $where = [
                new DataBaseWhere('idempresa', $company->idempresa),
                new DataBaseWhere('idfactura', $this->idfactura, '<'),
                new DataBaseWhere('fecha', $company->tbai_startdatesign, '>=')
            ];
            $orderBy = ['idfactura' => 'DESC'];
            foreach ($this->all($where, $orderBy, 0, 0) as $invoice) {
                return $invoice;
            }

            return null;
        };
    }

    public function signTbai(): Closure
    {
        return function (): bool {
            // si la empresa no es del país vasco, terminamos
            $company = $this->getCompany();
            if (false === TbaiTools::isBasqueCountryCompany($company)) {
                Tools::log()->warning('no-basque-country-company', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            } elseif (false === TbaiTools::checkCompanyLicense($company)) {
                Tools::log()->warning('missing-ticketbai-company', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            } elseif (false === empty($this->tbai_sent_date)) {
                // si la factura ya ha sido enviada, terminamos
                Tools::log()->warning('ticketbai-already-sent', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            } elseif ($this->tbai_canceled) {
                // si la factura ya ha sido anulada, terminamos
                Tools::log()->warning('ticketbai-already-annulled', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            if (false === $this->generateJsonTbai($company)) {
                return false;
            }

            if (false === $this->generateXmlTbai($company)) {
                return false;
            }

            if (false === $this->signXmlTbai($company)) {
                return false;
            }

            return true;
        };
    }

    public function signSendTbai(): Closure
    {
        return function (bool $save): bool {
            if (false === $this->signTbai()) {
                return false;
            }

            $company = $this->getCompany();
            if (false === $this->sendXmlTbai($company, $save)) {
                return false;
            }

            return true;
        };
    }

    public function onChange(): Closure
    {
        return function ($field) {
            if ($field !== 'idestado') {
                return;
            }

            // obtenemos la empresa de la factura
            $company = $this->getCompany();

            // si la empresa no es del país vasco, terminamos
            if (false === TbaiTools::isBasqueCountryCompany($company)) {
                return;
            }

            // obtenemos el estado actual de la factura
            $status = new EstadoDocumento();
            $status->loadFromCode($this->idestado);

            // obtenemos el estado previo de la factura
            $previousStatus = new EstadoDocumento();
            $previousStatus->loadFromCode($this->previousData['idestado']);

            // si el estado previo era editable y el actual no es editable y no está enviada a Ticketbai, la enviamos
            if ($previousStatus->editable && false === $status->editable && empty($this->tbai_sent_date)) {
                $this->signSendTbai(false);
                return;
            }

            // si el estado previo no era editable y el actual es editable y está enviada a Ticketbai, no dejamos cambiar el estado
            if (false === $previousStatus->editable && $status->editable && false === empty($this->tbai_sent_date)) {
                Tools::log()->warning('ticketbai-invoice-not-editable-signed');
                return false;
            }
        };
    }

    public function url(): Closure
    {
        return function (string $type = 'auto', string $list = 'List') {
            switch ($type) {
                case 'files-tmp':
                    return TbaiTools::FILE_PATH_TBAI_TMP . $this->idfactura . '/';
                case 'xml-sign':
                    return TbaiTools::FILE_PATH_TBAI . $this->codigo . '-signed.xml';
                case 'xml-cancel':
                    return TbaiTools::FILE_PATH_TBAI . $this->codigo . '-cancel.xml';
                case 'download-sign':
                    $filePath = $this->url('xml-sign');
                    return file_exists($filePath)
                        ? $filePath . '?myft=' . MyFilesToken::get($filePath, false) : '';
                case 'download-annulled':
                    $filePath = $this->url('xml-cancel');
                    return file_exists($filePath)
                        ? $filePath . '?myft=' . MyFilesToken::get($filePath, false) : '';
            }
        };
    }

    protected function generateJsonTbai(): Closure
    {
        return function (Empresa $company): bool {
            if (Tools::settings('default', 'decimals') > 2) {
                Tools::log()->warning('ticketbai-decimals-2', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            $invoicePrev = $this->getPreviousInvoice($company);
            $invoiceRect = $this->getRectificationInvoice();

            if (false === TbaiTools::isPreviousInvoiceSigned($company, $this, $invoicePrev, $invoiceRect)) {
                return false;
            }

            $customer = $this->getSubject();
            $customerForeign = TbaiTools::getForeignSubject($this);

            $json = [];
            $json['selfEmployed'] = (bool)$company->personafisica;
            $json['territory'] = TbaiTools::getTerritory($company);

            // validamos el cif de la empresa
            if (false === FiscalNumberValidator::validate($company->tipoidfiscal, $company->cifnif, true)) {
                Tools::log()->warning('ticketbai-company-invalid-cifnif', [
                    '%cifnif%' => $company->cifnif,
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            // SUBJECT
            $json['subject']['issuer'] = [
                'vatId' => TbaiTools::normaliceCifNif($company->cifnif),
                'name' => $company->nombre,
            ];

            $json['subject']['recipients'][] = [
                'vatId' => TbaiTools::normaliceCifNif($this->cifnif),
                'vatIdType' => TbaiTools::getVatIdType($customer),
                'name' => $this->nombrecliente,
                'postalCode' => $this->codpostal,
                'address' => $this->direccion,
                'countryCode' => Paises::get($this->codpais)->codiso,
            ];

            // si el cif del certificado no coincide con el de la empresa, es representante
            if (TbaiTools::normaliceCifNif($company->cifnif) !== TbaiTools::normaliceCifNif($company->tbai_signature_nif)) {
                $json['subject']['issuedBy'] = 'T';
            }

            // INVOICE
            // comprobamos el tipo de la serie para saber si es simplificada o no
            $json['invoice']['header'] = [
                'series' => $this->codserie,
                'invoiceNumber' => $this->numero,
                'expeditionDate' => $this->fecha,
                'expeditionTime' => $this->hora,
                'simplifiedInvoice' => $this->getSerie()->tipo === 'S'
            ];

            // comprobamos si esta factura es rectificativa de otra
            if ($invoiceRect) {
                $json['invoice']['header']['rectifyingInvoice'] = [
                    'code' => $invoiceRect->getSerie()->tipo === 'S' ? 'R5' : 'R1',
                    'type' => 'I'
                ];

                if ($json['invoice']['header']['rectifyingInvoice']['type'] === 'S') {
                    $json['invoice']['header']['rectifyingInvoice']['rectifyingAmount']['base'] = round($invoiceRect->neto, 2);
                    $json['invoice']['header']['rectifyingInvoice']['rectifyingAmount']['quota'] = round($invoiceRect->totaliva, 2);
                }

                $json['invoice']['header']['rectifiedInvoices'][] = [
                    'invoiceNumber' => $invoiceRect->numero,
                    'sentDate' => $invoiceRect->fecha,
                ];

                // comprobamos que el tipo de la serie de la factura rectificativa es simplificada
                if ($invoiceRect->getSerie()->tipo === 'S') {
                    $json['invoice']['header']['simplifiedInvoice'] = true;
                }
            }

            // añadimos el código como descripción
            $json['invoice']['data']['description'] = $this->codigo;

            // añadimos la fecha de operación si es diferente a la fecha de creación
            if (false === empty($this->fechadevengo) && strtotime($this->fecha) !== strtotime($this->fechadevengo)) {
                $json['invoice']['data']['operationDate'] = $this->fechadevengo;
            }

            // eliminamos el cliente si la factura es simplificada
            if ($json['invoice']['header']['simplifiedInvoice']) {
                unset($json['subject']['recipients']);
            } elseif (empty($this->direccion) || empty($this->codpostal) || empty($this->ciudad) || empty($this->provincia) || empty($this->codpais)) {
                // si no es una factura simplificada, comprobamos los datos de la dirección
                Tools::log()->warning('invoice-address-not-filled', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            } elseif (false === FiscalNumberValidator::validate($customer->tipoidfiscal, $this->cifnif, true)) {
                // validamos el cif que tiene la factura del cliente
                Tools::log()->warning('ticketbai-invoice-invalid-cifnif', [
                    '%cifnif%' => $customer->cifnif,
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            // si tenemos cliente porque no es una factura simplificada
            // y además el cif del cliente está vacío
            // terminamos
            if (isset($json['subject']['recipients'])
                && empty($json['subject']['recipients'][0]['vatId'])) {
                Tools::log()->warning('ticketbai-simplified-without-cifnif', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            // LINES INVOICE
            $linesDiscount100 = false;
            $lines = $this->getLines();
            foreach ($lines as $line) {
                if (empty($line->descripcion) && $line->pvpunitario <> 0) {
                    Tools::log()->warning('ticketbai-line-without-description-and-price', [
                        'model-code' => $this->primaryColumnValue(),
                        'model-class' => $this->modelClassName(),
                    ]);
                    return false;
                }

                if (is_null($line->tbai_send)) {
                    Tools::log()->warning('ticketbai-line-without-tbai_send', [
                        'model-code' => $this->primaryColumnValue(),
                        'model-class' => $this->modelClassName(),
                    ]);
                    return false;
                }

                if (false === $line->tbai_send || empty($line->descripcion)) {
                    continue;
                }

                // si hay descuento global del documento debemos aplicarlo al total de la línea
                $linePvpTotal = $line->pvptotal * (100 - $this->dtopor1) / 100 * (100 - $this->dtopor2) / 100;

                // calculamos el descuento de la línea
                $discount = $line->pvpsindto - $linePvpTotal;

                // calculamos el R.E.
                $re = $line->recargo > 0 ? ($linePvpTotal * $line->recargo) / 100 : 0;

                // calculamos el total + impuestos + recargo
                // quitamos el IRPF, porque no debe llevarlo en la línea
                // quitamos el R.E., porque no debe llevarlo en la línea
                // la suma del totalAmount de cada línea debe coincidir con el total de la factura
                $totalAmount = $linePvpTotal * (100 + $line->iva) / 100;
                $totalAmount += $re;

                // si el total de la línea es 0 porque tiene el máximo de descuento
                // entonces nos guardamos que existen líneas con 0
                if ($line->pvptotal === 0.0 && ($line->dtopor + $line->dtopor2) === 100.0) {
                    $linesDiscount100 = true;
                } else {
                    $linesDiscount100 = false;
                }

                $json['invoice']['data']['details'][] = [
                    'description' => substr($line->descripcion, 0, 250),
                    'unitPrice' => $line->pvpunitario,
                    'quantity' => $line->cantidad,
                    'discount' => round($discount, 2),
                    'totalAmount' => round($totalAmount, 2)
                ];
            }

            if (empty($json['invoice']['data']['details'])) {
                Tools::log()->warning('ticketbai-invoice-without-lines', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            // comprobamos si el total de la factura es 0
            // debe tener descuento global del 100% o de las líneas
            if ($this->total === 0.0) {
                $invoiceDto = $this->dtopor1 + $this->dtopor2;
                // si no hay líneas con descuento 100% y el descuento global no es 100%, terminamos
                if (false === $linesDiscount100 && $invoiceDto !== 100.0) {
                    Tools::log()->warning('ticketbai-invoice-total-0-without-discount', [
                        'model-code' => $this->primaryColumnValue(),
                        'model-class' => $this->modelClassName(),
                    ]);
                    return false;
                }
            }

            // El total de factura debe llevar el IRPF incluido y quitar los suplidos
            $json['invoice']['data']['total'] = round(($this->total + $this->totalirpf) - $this->totalsuplidos, 2);
            $json['invoice']['data']['vatRegimes'] = TbaiTools::getTypeIvaCustomer($company, $customer, $lines, $this);
            $json['invoice']['data']['supportedRetention'] = $this->totalirpf > 0 ? $this->totalirpf : null;

            // Solo se debe informar el campo taxBaseCost si alguna vatRegimes tiene un valor igual a 06
            if (in_array('06', $json['invoice']['data']['vatRegimes'])) {
                $json['invoice']['data']['taxBaseCost'] = round($this->neto, 2);
            }

            // Desglose factura - sujeta exenta
            $nationalSubjectExemptBreakdownItems = [];

            // Desglose factura - sujeta no exento
            $nationalSubjectNotExemptBreakdownItems = [];

            // Desglose factura - no sujeta
            $nationalNotSubjectBreakdownItems = [];

            // Desglose factura - servicios sujeta exenta
            $foreignServiceSubjectExemptBreakdownItems = [];

            // Desglose tipo de operación - servicios sujeta no exenta
            $foreignServiceSubjectNotExemptBreakdownItems = [];

            // Desglose tipo de operación - servicios no sujeta
            $foreignServiceNotSubjectBreakdownItems = [];

            // Desglose tipo de operación - entrega sujeta exenta
            $foreignDeliverySubjectExemptBreakdownItems = [];

            // Desglose tipo de operación - entrega sujeta no exenta
            $foreignDeliverySubjectNotExemptBreakdownItems = [];

            // Desglose tipo de operación -entrega no sujeta
            $foreignDeliveryNotSubjectBreakdownItems = [];

            foreach (TbaiTools::getTaxes($this, $lines) as $subtotal) {
                $excepcionIva = TbaiTools::getExceptionTax($subtotal['lines'], $company);

                // si la contraparte (cliente) es nacional o no existe
                if (false === $customerForeign) {

                    // si el total es 0 y tiene excepción de iva
                    if ($subtotal['taxamount'] == 0 && false === empty($excepcionIva)) {
                        $nationalSubjectExemptBreakdownItems = TbaiTools::setNationalSubjectExemptBreakdownItems(
                            $subtotal, $excepcionIva, $nationalSubjectExemptBreakdownItems);
                        continue;
                    }

                    // si el total es 0 y no tiene excepción de iva
                    if ($subtotal['taxamount'] == 0 && empty($excepcionIva)) {
                        $nationalNotSubjectBreakdownItems = TbaiTools::setNationalNotSubjectBreakdownItems(
                            $this->operacion, $subtotal, $nationalNotSubjectBreakdownItems);
                        continue;
                    }

                    // si el total no es 0 o no tiene excepción de iva
                    if ($subtotal['taxamount'] != 0 || empty($excepcionIva)) {
                        $nationalSubjectNotExemptBreakdownItems = TbaiTools::setNationalSubjectNotExemptBreakdownItems(
                            $subtotal, $json['invoice']['data']['vatRegimes'], $nationalSubjectNotExemptBreakdownItems);
                        continue;
                    }

                    continue;
                }

                // la contraparte (cliente) es extranjera
                // debemos preguntar si la primera línea de grupo de líneas es de tipo servicio o entrega
                $product = $subtotal['lines'][0]->getProducto();
                if ($product->exists() && $product->tipo === ProductType::SERVICE) {
                    // si el total es 0 y tiene excepción de iva
                    if ($subtotal['taxamount'] == 0 && false === empty($excepcionIva)) {
                        $foreignServiceSubjectExemptBreakdownItems = TbaiTools::setForeignServiceSubjectExemptBreakdownItems(
                            $subtotal, $excepcionIva, $foreignServiceSubjectExemptBreakdownItems);
                        continue;
                    }

                    // si el total es 0 y no tiene excepción de iva
                    if ($subtotal['taxamount'] == 0 && empty($excepcionIva)) {
                        $foreignServiceNotSubjectBreakdownItems = TbaiTools::setForeignServiceNotSubjectBreakdownItems(
                            $this->operacion, $subtotal, $foreignServiceNotSubjectBreakdownItems);
                        continue;
                    }

                    // si el total no es 0 o no tiene excepción de iva
                    if ($subtotal['taxamount'] != 0 || empty($excepcionIva)) {
                        $foreignServiceSubjectNotExemptBreakdownItems = TbaiTools::setForeignServiceSubjectNotExemptBreakdownItems(
                            $subtotal, $json['invoice']['data']['vatRegimes'], $foreignServiceSubjectNotExemptBreakdownItems);
                        continue;
                    }

                    continue;
                }

                // si el total es 0 y tiene excepción de iva
                if ($subtotal['taxamount'] == 0 && false === empty($excepcionIva)) {
                    $foreignDeliverySubjectExemptBreakdownItems = TbaiTools::setForeignDeliverySubjectExemptBreakdownItems(
                        $subtotal, $excepcionIva, $foreignDeliverySubjectExemptBreakdownItems);
                    continue;
                }

                // si el total es 0 y no tiene excepción de iva
                if ($subtotal['taxamount'] == 0 && empty($excepcionIva)) {
                    $foreignDeliveryNotSubjectBreakdownItems = TbaiTools::setForeignDeliveryNotSubjectBreakdownItems(
                        $this->operacion, $subtotal, $foreignDeliveryNotSubjectBreakdownItems);
                    continue;
                }

                // si el total no es 0 o no tiene excepción de iva
                if ($subtotal['taxamount'] != 0 || empty($excepcionIva)) {
                    $foreignDeliverySubjectNotExemptBreakdownItems = TbaiTools::setForeignDeliverySubjectNotExemptBreakdownItems(
                        $subtotal, $json['invoice']['data']['vatRegimes'], $foreignDeliverySubjectNotExemptBreakdownItems);
                }
            }

            foreach ($nationalSubjectExemptBreakdownItems as $a) {
                $json['invoice']['breakdown']['nationalSubjectExemptBreakdownItems'][] = $a;
            }

            foreach ($nationalSubjectNotExemptBreakdownItems as $a) {
                $json['invoice']['breakdown']['nationalSubjectNotExemptBreakdownItems'][] = $a;
            }

            foreach ($nationalNotSubjectBreakdownItems as $a) {
                $json['invoice']['breakdown']['nationalNotSubjectBreakdownItems'][] = $a;
            }

            foreach ($foreignServiceSubjectExemptBreakdownItems as $a) {
                $json['invoice']['breakdown']['foreignServiceSubjectExemptBreakdownItems'][] = $a;
            }

            foreach ($foreignServiceSubjectNotExemptBreakdownItems as $a) {
                $json['invoice']['breakdown']['foreignServiceSubjectNotExemptBreakdownItems'][] = $a;
            }

            foreach ($foreignServiceNotSubjectBreakdownItems as $a) {
                $json['invoice']['breakdown']['foreignServiceNotSubjectBreakdownItems'][] = $a;
            }

            foreach ($foreignDeliverySubjectExemptBreakdownItems as $a) {
                $json['invoice']['breakdown']['foreignDeliverySubjectExemptBreakdownItems'][] = $a;
            }

            foreach ($foreignDeliverySubjectNotExemptBreakdownItems as $a) {
                $json['invoice']['breakdown']['foreignDeliverySubjectNotExemptBreakdownItems'][] = $a;
            }

            foreach ($foreignDeliveryNotSubjectBreakdownItems as $a) {
                $json['invoice']['breakdown']['foreignDeliveryNotSubjectBreakdownItems'][] = $a;
            }

            if (false === empty($invoicePrev)) {
                $json['fingerprint'] = [
                    'previousInvoice' => [
                        'invoiceNumber' => $invoicePrev->numero,
                        'sentDate' => $invoicePrev->fecha,
                        'signature' => empty($invoicePrev->tbaisignature) ?
                            $invoicePrev->tbaicodbar :
                            substr($invoicePrev->tbaisignature, 0, 100),
                        'series' => $invoicePrev->codserie,
                    ]
                ];
            }

            if ($company->personafisica && $json['territory'] === TicketBai::TERRITORY_BIZKAIA) {
                if (false === TbaiTools::setBizkaiaIAE($json, $lines, $this)) {
                    return false;
                }
            }

            $this->tbaiJson = $json;
            return true;
        };
    }

    protected function generateXmlTbai(): Closure
    {
        return function (Empresa $company): bool {
            // si el json está vacío, terminamos
            if (empty($this->tbaiJson)) {
                Tools::log()->warning('ticketbai-json-not-generated', [
                    'json' => $this->tbaiJson,
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            // creamos la carpeta donde se guardará el fichero
            $folderPath = Tools::folder(TbaiTools::FILE_PATH_TBAI);
            if (false === Tools::folderCheckOrCreate($folderPath)) {
                Tools::log()->warning('cannot-create-folder', [
                    '%folder%' => $folderPath,
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            // obtenemos la ruta del fichero
            $filePath = $this->url('xml-sign');
            $this->deleteXmlTbai($filePath);
            if (false === touch($filePath)) {
                Tools::log()->warning('cannot-create-file', [
                    '%file%' => $filePath,
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            return true;
        };
    }

    protected function getIaesEmpresa(): Closure
    {
        return function () {
            $company = $this->getCompany();

            $where = [
                new DataBaseWhere('idempresa', $company->idempresa),
            ];

            $iaesEmpresa = IaeEmpresa::all($where, [], 0, 0);
            if (count($iaesEmpresa) === 0) {
                return [];
            }

            $idiaes = [];
            foreach ($iaesEmpresa as $iaeEmpresa) {
                $idiaes[] = $iaeEmpresa->idiae;
            }

            $where = [
                new DatabaseWhere('idiae', implode(',', $idiaes), 'IN')
            ];

            return CodigoIae::all($where, [], 0, 0);
        };
    }

    protected function getRectificationInvoice(): Closure
    {
        return function () {
            if (empty($this->idfacturarect)) {
                return null;
            }

            $invoiceRect = new self();
            if ($invoiceRect->loadFromCode($this->idfacturarect)) {
                return $invoiceRect;
            }

            return null;
        };
    }

    protected function deleteXmlTbai(): Closure
    {
        return function (string $file): void {
            if (false === empty($file) && file_exists($file)) {
                unlink($file);
            }
        };
    }

    protected function sendXmlTbai(): Closure
    {
        return function (Empresa $company, bool $save): bool {
            // si el archivo xml no existe, terminamos
            $fileXmlPath = $this->url('xml-sign');
            if (false === file_exists($fileXmlPath)) {
                Tools::log()->warning('ticketbai-xml-not-exists', [
                    '%file%' => $fileXmlPath,
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            // creamos la carpeta temporal
            $tempPath = Tools::folder(TbaiTools::FILE_PATH_TBAI_TMP, $this->idfactura);
            if (false === Tools::folderCheckOrCreate($tempPath)) {
                $this->deleteXmlTbai($fileXmlPath);
                Tools::log()->warning('cannot-create-folder', [
                    '%folder%' => $tempPath,
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            try {
                $devMode = $company->tbai_debugmode;

                // seleccionamos el endpoint según el territorio
                switch (TbaiTools::getTerritory($company)) {
                    case '01':
                        $endpoint = new ArabaEndpoint($devMode, true);
                        $ticketbai = TicketBai::createFromXml(
                            file_get_contents($fileXmlPath),
                            Ticketbai::TERRITORY_ARABA,
                            (bool)$company->personafisica
                        );
                        break;

                    case '02':
                        $endpoint = new BizkaiaEndpoint($devMode, true);
                        $ticketbai = TicketBai::createFromXml(
                            file_get_contents($fileXmlPath),
                            Ticketbai::TERRITORY_BIZKAIA,
                            (bool)$company->personafisica
                        );

                        if ($company->personafisica) {
                            // necesitamos añadir los impuestos de la renta de Bizkaia manualmente
                            $json = [];
                            $lines = $this->getLines();
                            TbaiTools::setBizkaiaIAE($json, $lines, $this);
                            $batuzIncomeTaxCollection = Collection::createFromJson($json['batuzIncomeTaxes']);
                            $ticketbai->addBatuzIncomeTaxes($batuzIncomeTaxCollection);
                        }
                        break;

                    case '03':
                        $endpoint = new GipuzkoaEndpoint($devMode, true);
                        $ticketbai = TicketBai::createFromXml(
                            file_get_contents($fileXmlPath),
                            Ticketbai::TERRITORY_GIPUZKOA,
                            (bool)$company->personafisica
                        );
                        break;

                    default:
                        $this->deleteXmlTbai($fileXmlPath);
                        Tools::log()->warning('ticketbai-endpoint-not-found', [
                            'model-code' => $this->primaryColumnValue(),
                            'model-class' => $this->modelClassName(),
                        ]);
                        return false;
                }

                // enviamos la factura
                $signatureFile = TbaiSignature::getSignatureFile($company);
                if (false === file_exists($signatureFile)) {
                    Tools::log()->warning('ticketbai-signature-file-not-exists', [
                        '%file%' => $signatureFile,
                        'model-code' => $this->primaryColumnValue(),
                        'model-class' => $this->modelClassName(),
                    ]);
                    return false;
                }

                $tbaiPrivateKey = PrivateKey::p12($signatureFile);
                $response = $endpoint->submitInvoice($ticketbai, $tbaiPrivateKey, $company->tbai_password);
            } catch (Exception $e) {
                $this->deleteXmlTbai($fileXmlPath);
                Tools::log()->warning($e->getMessage(), [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            // obtener errores de la respuesta
            $errorData = $response->errorDataRegistry();
            $messageError = TbaiTools::getErrorToUTF8($response->mainErrorMessage());

            // mostramos los posibles mensajes de errores o advertencias
            TbaiTools::logMessageErros($this, $errorData, $messageError);

            // respuesta de la llamada al servicio, si no es correcta, terminamos
            if ($response->status() != 200) {
                $this->deleteXmlTbai($fileXmlPath);
                Tools::log()->error('ticketbai-response-not-ok', [
                    '%status%' => $messageError,
                    '%code%' => $response->status(),
                    '%message%' => $response->content(),
                    '%error%' => $errorData,
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            // guardamos el fichero enviado
            $fileSent = $endpoint->debugData(Api::DEBUG_SENT_FILE);
            $fileSentName = $tempPath . '/' . $this->codigo . '-sign-sent-' . date('YmdHis') . '.xml';
            if (false === copy($fileSent, $fileSentName)) {
                Tools::log()->info('ticketbai-sent-file-not-copy', [
                    '%file%' => $fileSentName,
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
            }

            // si es Bizkaia debemos descomprimir el archivo enviado y guardarlo de nuevo
            if (TbaiTools::getTerritory($company) === '02') {
                $fileSentContent = gzdecode(file_get_contents($fileSentName));

                $dom = new DOMDocument();
                $dom->loadXML($fileSentContent);
                $ticketbaiContent = $dom->getElementsByTagName('TicketBai')->item(0)->nodeValue;

                if (false === file_put_contents($fileSentName, base64_decode($ticketbaiContent))) {
                    Tools::log()->info('ticketbai-sent-file-not-decompress', [
                        '%file%' => $fileSentName,
                        'model-code' => $this->primaryColumnValue(),
                        'model-class' => $this->modelClassName(),
                    ]);
                }
            }

            // guardamos la respuesta
            $fileResponseName = $tempPath . '/' . $this->codigo . '-sign-response-' . date('YmdHis') . '.json';
            $data = [$response->headers(), $response->content()];
            if (false === file_put_contents($fileResponseName, json_encode($data, JSON_PRETTY_PRINT))) {
                Tools::log()->info('ticketbai-response-file-not-copy', [
                    '%file%' => $fileResponseName,
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
            }

            // una factura puede estar presentada isDelivered() = true
            // pero puede tener errores, en ese caso isCorrect() = false

            // si la factura no fue entregada correctamente, terminamos
            if (false === $response->isDelivered()) {
                $this->deleteXmlTbai($fileXmlPath);
                Tools::log()->warning('ticketbai-response-not-delivered', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            // comprueba si la factura no es correcta 100%, entonces fué entregada pero con errores
            if (false === $response->isCorrect()) {
                Tools::log()->warning('ticketbai-response-not-correct', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
            }

            // guardamos los datos
            $qr = new Qr($ticketbai, $company->tbai_debugmode);
            $this->tbaicodbar = $qr->ticketbaiIdentifier();
            $this->tbaiurl = $qr->qrUrl();
            $this->tbaisignature = $ticketbai->signatureValue();
            $this->tbai_sent_date = Tools::dateTime();

            // si no hay que guardar la factura, terminamos
            if (false === $save) {
                return true;
            }

            $status = $this->getStatus();
            if ($status->editable) {
                // cambiamos el estado de la factura si su estado actual es editable
                foreach ($this->getAvailableStatus() as $stat) {
                    if (false === $stat->editable) {
                        $this->idestado = $stat->idestado;
                        break;
                    }
                }
            }

            if (false === $this->save()) {
                Tools::log()->warning('ticketbai-invoice-send-but-not-saved', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            return true;
        };
    }

    protected function signXmlTbai(): Closure
    {
        return function (Empresa $company): bool {
            // si el archivo xml no existe, terminamos
            $fileXmlPath = $this->url('xml-sign');
            if (false === file_exists($fileXmlPath)) {
                Tools::log()->warning('ticketbai-xml-not-exists', [
                    '%file%' => $fileXmlPath,
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            try {
                // cargamos la firma de la empresa
                $signatureFile = TbaiSignature::getSignatureFile($company);
                if (false === file_exists($signatureFile)) {
                    Tools::log()->warning('ticketbai-signature-file-not-exists', [
                        '%file%' => $signatureFile,
                        'model-code' => $this->primaryColumnValue(),
                        'model-class' => $this->modelClassName(),
                    ]);
                    return false;
                }

                // firmamos el json
                $tbaiPrivateKey = PrivateKey::p12($signatureFile);
                $vendor = new Vendor($company->tbai_license, $company->tbai_developer, $company->tbai_supplier, $company->tbai_version);
                $ticketbai = TicketBai::createFromJson($vendor, $this->tbaiJson);
                $ticketbai->sign($tbaiPrivateKey, $company->tbai_password, $fileXmlPath);
            } catch (Exception $e) {
                $this->deleteXmlTbai($fileXmlPath);
                Tools::log()->warning('tbai-sign-error', [
                    '%error%' => $e->getMessage(),
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }

            return true;
        };
    }
}
