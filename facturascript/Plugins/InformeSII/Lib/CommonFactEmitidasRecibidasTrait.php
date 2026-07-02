<?php
/**
 * This file is part of InformeSII plugin for FacturaScripts
 * Copyright (C) 2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\InformeSII\Lib;

use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\DataSrc\Paises;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FacturaProveedor;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait CommonFactEmitidasRecibidasTrait
{
    /** @var array */
    protected $invoices = [];

    protected $paisesIntracomunitarios = ['DE', 'AT', 'BE', 'BG', 'CZ', 'CY', 'HR', 'DK', 'SK', 'SI', 'EE', 'FI', 'FR', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'GB'];

    /** @var array */
    protected $povincias = [
        'las palmas',
        'las palmas de gran canaria',
        'las palmas de gran canarias',
        'santa cruz de tenerife',
        'santa cruz',
        'tenerife',
        'canarias',
        'gran canaria',
        'gran canarias',
        'ceuta',
        'melilla'
    ];

    protected function getClaveRegimenEspecial(BusinessDocument $invoice): string
    {
        $subject = $invoice->getSubject();
        $dir = $subject->getDefaultAddress();

        $codpais = $invoice->codpais ?? $dir->codpais;
        $provincia = $invoice->provincia ?? $dir->provincia;

        // si el país de la factura es diferente al de la empresa,
        // pero el país de la factura es un país de la UE
        // entonces 01 si es factura de cliente o 09 si es factura de proveedor
        if ($codpais !== Empresas::get($this->idempresa)->codpais
            && in_array(Paises::get($codpais)->codiso, $this->paisesIntracomunitarios)) {
            return $invoice->modelClassName() === 'FacturaCliente' ? '01' : '09';
        }

        // si el país de la factura es diferente al de la empresa,
        // pero el país de la factura no es un país de la UE
        // entonces 02
        if ($codpais !== Empresas::get($this->idempresa)->codpais
            && false === in_array(Paises::get($codpais)->codiso, $this->paisesIntracomunitarios)) {
            return '02';
        }

        // si la provincia de la empresa coincide con el array de provincias
        // y la provincia de la factura coincide con el array de provincias
        // entonces 01
        if (in_array(strtolower(Empresas::get($this->idempresa)->provincia), $this->povincias)
            && in_array(strtolower($provincia), $this->povincias)) {
            return '01';
        }

        // si la provincia de la empresa coincide con el array de provincias
        // y la provincia de la factura no coincide con el array de provincias
        // entonces 02
        if (in_array(strtolower(Empresas::get($this->idempresa)->provincia), $this->povincias)
            && false === in_array(strtolower($provincia), $this->povincias)) {
            return '02';
        }

        // si la provincia de la empresa no coincide con el array de provincias
        // y la provincia de la factura coincide con el array de provincias
        // entonces 02
        if (false === in_array(strtolower(Empresas::get($this->idempresa)->provincia), $this->povincias)
            && in_array(strtolower($provincia), $this->povincias)) {
            return '02';
        }

        // por defecto 01
        return '01';
    }

    protected function getContraparte(BusinessDocument $invoice): string
    {
        if (empty($invoice->cifnif)) {
            return '';
        }

        $nombre = $invoice->nombrecliente ?? $invoice->nombre;
        return '<' . self::NS_1 . ':Contraparte>
                    <' . self::NS_1 . ':NombreRazon>' . $this->escape($nombre) . '</' . self::NS_1 . ':NombreRazon>'
            . $this->getContraparteCIF($invoice)
            . $this->getContraparteOtro($invoice) . '
                </' . self::NS_1 . ':Contraparte>';
    }

    protected function getContraparteCIF(BusinessDocument $invoice): string
    {
        $subject = $invoice->getSubject();
        $dir = $subject->getDefaultAddress();
        $codpais = $invoice->codpais ?? $dir->codpais;

        // si el país de la factura/subject es diferente a España, terminamos
        if ($codpais !== 'ESP') {
            return '';
        }

        // reemplaza los espacios por nada
        $cifnif = str_replace(' ', '', $invoice->cifnif);

        // si el cifnif es mayor de 9 caracteres
        // lo cortamos a 9 caracteres quedándonos la parte derecha
        if (strlen($cifnif) > 9) {
            $cifnif = substr($cifnif, -9);
        }

        return '<' . self::NS_1 . ':NIF>' . $cifnif . '</' . self::NS_1 . ':NIF>';
    }

    protected function getContraparteOtro(BusinessDocument $invoice): string
    {
        $subject = $invoice->getSubject();
        $dir = $subject->getDefaultAddress();
        $codpais = $invoice->codpais ?? $dir->codpais;

        // si el país de la factura/subject es España, terminamos
        if ($codpais === 'ESP') {
            return '';
        }

        return '<' . self::NS_1 . ':IDOtro>'
            . '<' . self::NS_1 . ':CodigoPais>' . Paises::get($codpais)->codiso . '</' . self::NS_1 . ':CodigoPais>'
            . '<' . self::NS_1 . ':IDType>04</' . self::NS_1 . ':IDType>'
            . '<' . self::NS_1 . ':ID>' . $invoice->cifnif . '</' . self::NS_1 . ':ID>'
            . '</' . self::NS_1 . ':IDOtro>';
    }

    protected function getCustomerInvoice(string $codigo): FacturaCliente
    {
        $invoice = new FacturaCliente();
        $where = [
            new DataBaseWhere('codigo', $codigo),
            new DataBaseWhere('idempresa', $this->idempresa),
        ];
        $invoice->loadFromCode('', $where);
        return $invoice;
    }

    protected function getDesgloseIva(BusinessDocument $invoice): string
    {
        $xml = '';
        $subtotals = Calculator::getSubtotals($invoice, $invoice->getLines());

        // si es una factura de compra intracomunitaria
        // y no tiene iva, debemos añadir una línea de iva al 21%
        if ($invoice->modelClassName() === 'FacturaProveedor'
            && empty($invoice->totaliva)
            && $this->getClaveRegimenEspecial($invoice) === '09') {
            $subtotals['iva'] = [];
            $subtotals['iva']['21|0'] = [
                'iva' => 21,
                'neto' => $invoice->neto,
                'recargo' => 0,
                'totaliva' => round($invoice->neto * 21 / 100, FS_NF0),
                'totalrecargo' => 0.0
            ];
        }

        foreach ($subtotals['iva'] as $subtotal) {
            $xml .= '<' . self::NS_1 . ':DetalleIVA>'
                . '<' . self::NS_1 . ':TipoImpositivo>' . $subtotal['iva'] . '</' . self::NS_1 . ':TipoImpositivo>'
                . '<' . self::NS_1 . ':BaseImponible>' . $subtotal['neto'] . '</' . self::NS_1 . ':BaseImponible>';

            if ($invoice->modelClassName() === 'FacturaCliente') {
                $xml .= '<' . self::NS_1 . ':CuotaRepercutida>' . $subtotal['totaliva'] . '</' . self::NS_1 . ':CuotaRepercutida>';
            } elseif ($invoice->modelClassName() === 'FacturaProveedor') {
                $xml .= '<' . self::NS_1 . ':CuotaSoportada>' . $subtotal['totaliva'] . '</' . self::NS_1 . ':CuotaSoportada>';
            }

            if ($subtotal['recargo'] > 0.0) {
                $xml .= '<' . self::NS_1 . ':TipoRecargoEquivalencia>' . $subtotal['recargo'] . '</' . self::NS_1 . ':TipoRecargoEquivalencia>'
                    . '<' . self::NS_1 . ':CuotaRecargoEquivalencia>' . $subtotal['totalrecargo'] . '</' . self::NS_1 . ':CuotaRecargoEquivalencia>';
            }

            $xml .= "</" . self::NS_1 . ":DetalleIVA>\n";
        }

        return "\n<" . self::NS_1 . ":DesgloseIVA>\n" . $xml . '</' . self::NS_1 . ':DesgloseIVA>';
    }

    protected function getSupplierInvoice(string $codigo): FacturaProveedor
    {
        $invoice = new FacturaProveedor();
        $where = [
            new DataBaseWhere('codigo', $codigo),
            new DataBaseWhere('idempresa', $this->idempresa),
        ];
        $invoice->loadFromCode('', $where);
        return $invoice;
    }

    protected function getTipoComunicacion(): string
    {
        return 'A0';
    }

    protected function getTipoFactura(BusinessDocument $invoice): string
    {
        // si la factura tiene cifnif, y es una rectificativa
        if (false === empty($invoice->cifnif) && false === empty($invoice->codigorect)) {
            return 'R1';
        }

        // si la factura no tiene cifnif, pero es una rectificativa
        if (empty($invoice->cifnif) && false === empty($invoice->codigorect)) {
            return 'R5';
        }

        // si la factura no tiene cifnif
        if (empty($invoice->cifnif)) {
            return 'F2';
        }

        // si la factura tiene cifnif
        return 'F1';
    }

    protected function getTipoRectificativa(BusinessDocument $invoice): string
    {
        if (empty($invoice->idfacturarect)) {
            return '';
        }

        $invoiceRect = $invoice->parentDocuments();
        if (empty($invoiceRect)) {
            return '';
        }

        return '<' . self::NS_1 . ':TipoRectificativa>I</' . self::NS_1 . ':TipoRectificativa>'
            . '<' . self::NS_1 . ':FacturasRectificadas>'
            . '<' . self::NS_1 . ':IDFacturaRectificada>'
            . '<' . self::NS_1 . ':NumSerieFacturaEmisor>' . $this->escape($invoiceRect[0]->codigo) . '</' . self::NS_1 . ':NumSerieFacturaEmisor>'
            . '<' . self::NS_1 . ':FechaExpedicionFacturaEmisor>' . $invoiceRect[0]->fecha . '</' . self::NS_1 . ':FechaExpedicionFacturaEmisor>'
            . '</' . self::NS_1 . ':IDFacturaRectificada>'
            . '</' . self::NS_1 . ':FacturasRectificadas>';
    }

    protected function readResponse(string $response, string $type): array
    {
        $result = [
            'errors' => 0,
            'invoices' => count($this->invoices),
            'success' => 0,
            'trs' => '',
            'warnings' => 0,
        ];

        // eliminamos namespaces
        $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $response);

        // convertimos a xml
        $xml = simplexml_load_string($response);

        // si no existe el elemento siiRRespuestaLinea, terminamos
        if (empty($xml->xpath('//envBody')[0]->xpath('//siiRRespuesta' . $type)[0]->xpath('//siiRRespuestaLinea'))) {
            Tools::log()->error('siiRRespuestaLinea not found in response');
            return $result;
        }

        foreach ($xml->xpath('//envBody')[0]->xpath('//siiRRespuesta' . $type)[0]->xpath('//siiRRespuestaLinea') as $invoice) {
            // convertimos la factura a objeto
            $object = json_decode(json_encode((array)$invoice));

            $codigo = $object->siiRIDFactura->siiNumSerieFacturaEmisor;
            $status = $object->siiREstadoRegistro;

            $error = '';
            if (isset($object->siiRCodigoErrorRegistro)) {
                $error = $object->siiRCodigoErrorRegistro . ': ' . $object->siiRDescripcionErrorRegistro;
            }

            $invoiceModel = $type === 'LRFacturasEmitidas'
                ? $this->getCustomerInvoice($codigo)
                : $this->getSupplierInvoice($codigo);

            switch ($status) {
                case 'Correcto':
                case 'Correcta':
                    $cssTr = 'table-success';
                    $result['success']++;
                    $this->setInvoiceSend($invoiceModel, $status);
                    break;

                case 'Incorrecto':
                    // si la factura está duplicada, guardamos el estado de la factura duplicada
                    if (isset($object->siiRRegistroDuplicado)
                        && $object->siiRDescripcionErrorRegistro === 'Factura duplicada') {
                        $status = $object->siiRRegistroDuplicado->siiEstadoRegistro;
                        $error = '';
                        switch ($status) {
                            case 'Correcto':
                            case 'Correcta':
                                $cssTr = 'table-success';
                                $result['success']++;
                                break;

                            case 'AceptadaConErrores':
                                $cssTr = 'table-warning';
                                $result['warnings']++;
                                break;
                        }
                    } else {
                        $cssTr = 'table-danger';
                        $result['errors']++;
                    }
                    $this->setInvoiceSend($invoiceModel, $status);
                    break;

                case 'AceptadaConErrores':
                    $cssTr = 'table-warning';
                    $result['warnings']++;
                    $this->setInvoiceSend($invoiceModel, $status);
                    break;

                default:
                    $cssTr = '';
            }

            $result['trs'] .= '<tr class="' . $cssTr . '">'
                . '<td><a target="_blank" href="' . $invoiceModel->url('edit') . '">' . $codigo . '</a></td>'
                . '<td>' . $status . '</td>'
                . '<td>' . $error  . '</td>'
                . '</tr>';
        }

        return $result;
    }

    protected function setInvoiceSend(BusinessDocument $invoice, string $sii_status)
    {
        // guardamos el estado del envío SII
        $invoice->sii_status = $sii_status;
        $invoice->sii_sent = Tools::dateTime();

        // marcamos la factura como emitida
        // si el estado de la factura es AceptadaConErrores, Correcto o Correcta
        if (in_array($sii_status, ['AceptadaConErrores', 'Correcto', 'Correcta'])) {
            $status = $invoice->getStatus();
            if ($status->editable) {
                // cambiamos el estado de la factura si su estado actual es editable
                foreach ($invoice->getAvailableStatus() as $stat) {
                    if (false === $stat->editable) {
                        $this->idestado = $stat->idestado;
                        break;
                    }
                }
            }
        }

        $invoice->save();
    }
}