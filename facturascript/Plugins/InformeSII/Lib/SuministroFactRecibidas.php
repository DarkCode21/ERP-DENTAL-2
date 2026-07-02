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

use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\DataSrc\Paises;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use SoapClient;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class SuministroFactRecibidas extends SuministroSII
{
    use CommonFactEmitidasRecibidasTrait;

    const WSDL = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/ssii_1_1_bis/fact/ws/SuministroFactRecibidas.wsdl';

    public function __construct(int $idempresa, string $startDate, string $endDate, ?string $codserie = null, ?string $codpais = null, ?string $codpago = null, ?string $provincia = null)
    {
        $this->idempresa = $idempresa;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->codserie = $codserie;
        $this->codpais = $codpais;
        $this->codpago = $codpago;
        $this->provincia = $provincia;
        $this->generateXML();
    }

    public function sendXml(): array
    {
        if (count($this->invoices) === 0) {
            return [];
        }

        $certPem = $this->prepararCertificadoLocal($this->getCompany()->sii_signature, $this->getCompany()->sii_password);
        if (empty($certPem)) {
            return [];
        }

        try {
            $client = new SoapClient(
                static::WSDL,
                array(
                    'soap_version' => SOAP_1_1,
                    'trace' => true,
                    'exceptions' => true,
                    'local_cert' => $certPem,
                )
            );

            $port = $this->getCompany()->sii_debugmode
                ? 'SuministroFactRecibidasPruebas'
                : 'SuministroFactRecibidas';

            $location = $this->getLocationForPort(static::WSDL, $port);
            if (empty($location)) {
                ToolBox::i18nLog()->warning('no-location-for-port', ['%port%' => $port]);
                return [];
            }

            $response = $client->__doRequest(
                $this->xml,
                $location,
                'SuministroLRFacturasRecibidas',
                SOAP_1_1,
                false
            );

            //echo "REQUEST:<br/>" . htmlentities($this->xml) . "<br/>";

            if ($response === null) {
                throw new Exception("siiws_con_error " . openssl_error_string());
            }

            //echo "RESPONSE:<br/>" . htmlentities($response) . "<br/>";

            return $this->readResponse($response, 'LRFacturasRecibidas');
        } catch (Exception $e) {
            ToolBox::log()->error($e->getFile() . ":" . $e->getLine() . " " . $e->getMessage());
        }

        return [];
    }

    protected function generateXML(): void
    {
        $xmlInvoices = $this->getRegistroLRFacturasRecibidas();

        if (empty($xmlInvoices)) {
            return;
        }

        $this->xml = '<' . self::NS_ENVELOPE . ':Envelope xmlns:' . self::NS_ENVELOPE . '="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:' . self::NS_1 . '="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/ssii/fact/ws/SuministroInformacion.xsd"
                  xmlns:' . self::NS_2 . '="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/ssii/fact/ws/SuministroLR.xsd">
    <' . self::NS_ENVELOPE . ':Body>
        <' . self::NS_2 . ':SuministroLRFacturasRecibidas>
            <' . self::NS_1 . ':Cabecera>
                <' . self::NS_1 . ':IDVersionSii>1.1</' . self::NS_1 . ':IDVersionSii>
                <' . self::NS_1 . ':Titular>
                    <' . self::NS_1 . ':NombreRazon>' . $this->escape(Empresas::get($this->idempresa)->nombre) . '</' . self::NS_1 . ':NombreRazon>
                    <' . self::NS_1 . ':NIF>' . $this->escape(Empresas::get($this->idempresa)->cifnif) . '</' . self::NS_1 . ':NIF>
                </' . self::NS_1 . ':Titular>
                <' . self::NS_1 . ':TipoComunicacion>' . $this->getTipoComunicacion() . '</' . self::NS_1 . ':TipoComunicacion>
            </' . self::NS_1 . ':Cabecera>
            ' . $xmlInvoices . '
        </' . self::NS_2 . ':SuministroLRFacturasRecibidas>
    </' . self::NS_ENVELOPE . ':Body>
</' . self::NS_ENVELOPE . ':Envelope>';
    }

    protected function getCuotaDeducible(BusinessDocument $invoice)
    {
        // si es una factura de compra intracomunitaria
        // y no tiene iva, debemos añadir una línea de iva al 21%
        if ($invoice->modelClassName() === 'FacturaProveedor'
            && empty($invoice->totaliva)
            && $this->getClaveRegimenEspecial($invoice) === '09') {
            return round($invoice->neto * 21 / 100, FS_NF0);
        }

        return $invoice->totaliva;
    }

    protected function getEmisorCIF(BusinessDocument $invoice): string
    {
        $supplier = $invoice->getSubject();
        $dir = $supplier->getDefaultAddress();

        // si el país del proveedor es diferente a España, terminamos
        if ($dir->codpais !== 'ESP') {
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

    protected function getEmisorOtro(BusinessDocument $invoice): string
    {
        $supplier = $invoice->getSubject();
        $dir = $supplier->getDefaultAddress();

        // si el país del proveedor es España, terminamos
        if ($dir->codpais === 'ESP') {
            return '';
        }

        return '<' . self::NS_1 . ':IDOtro>'
            . '<' . self::NS_1 . ':CodigoPais>' . Paises::get($dir->codpais)->codiso . '</' . self::NS_1 . ':CodigoPais>'
            . '<' . self::NS_1 . ':IDType>04</' . self::NS_1 . ':IDType>'
            . '<' . self::NS_1 . ':ID>' . $invoice->cifnif . '</' . self::NS_1 . ':ID>'
            . '</' . self::NS_1 . ':IDOtro>';
    }

    protected function getRecibidasDescripcionOperacion(BusinessDocument $invoice): string
    {
        $supplier = $invoice->getSubject();
        $dir = $supplier->getDefaultAddress();

        $text = $supplier->acreedor ? 'Compra de servicios' : 'Compra de mercadería';
        if ($dir->codpais !== Empresas::get($this->idempresa)->codpais) {
            if (in_array(Paises::get($dir->codpais)->codiso, $this->paisesIntracomunitarios)) {
                $text = $supplier->acreedor ? 'Compra de servicios intracomunitarios' : 'Compra de mercadería intracomunitaria';
            } elseif (false === in_array(Paises::get($dir->codpais)->codiso, $this->paisesIntracomunitarios)) {
                $text = $supplier->acreedor ? 'Importación de servicios' : 'Importación de mercadería';
            }
        }

        return $text;
    }

    protected function getRegistroLRFacturasRecibidas(): string
    {
        $xml = '';

        // buscamos las facturas recibidas
        $invoiceModel = new FacturaProveedor();
        $where = [
            new DataBaseWhere('idempresa', $this->idempresa),
            new DataBaseWhere('fecha', $this->startDate, '>='),
            new DataBaseWhere('fecha', $this->endDate, '<='),
            new DataBaseWhere('sii_status', null),
            new DataBaseWhere('sii_status', 'Incorrecto', '=', 'OR'),
        ];

        if ($this->codserie) {
            $where[] = new DataBaseWhere('codserie', $this->codserie);
        }

        if ($this->codpais) {
            $where[] = new DataBaseWhere('codpais', $this->codpais);
        }

        if ($this->codpago) {
            $where[] = new DataBaseWhere('codpago', $this->codpago);
        }

        $orderBy = ['fecha' => 'ASC', 'codigo' => 'ASC'];
        $this->invoices = $invoiceModel->all($where, $orderBy, 0, 0);
        foreach ($this->invoices as $index => $invoice) {
            if ($index > 0) {
                $xml .= "\n            ";
            }

            $xml .= '<' . self::NS_2 . ':RegistroLRFacturasRecibidas>
                <' . self::NS_1 . ':PeriodoLiquidacion>
                    <' . self::NS_1 . ':Ejercicio>' . date('Y', strtotime($invoice->fecha)) . '</' . self::NS_1 . ':Ejercicio>
                    <' . self::NS_1 . ':Periodo>' . date('m', strtotime($invoice->fecha)) . '</' . self::NS_1 . ':Periodo>
                </' . self::NS_1 . ':PeriodoLiquidacion>
                <' . self::NS_2 . ':IDFactura>
                    <' . self::NS_1 . ':IDEmisorFactura>'
                . $this->getEmisorCIF($invoice)
                . $this->getEmisorOtro($invoice) . '
                    </' . self::NS_1 . ':IDEmisorFactura>
                    <' . self::NS_1 . ':NumSerieFacturaEmisor>' . $this->escape($invoice->codigo) . '</' . self::NS_1 . ':NumSerieFacturaEmisor>
                    <' . self::NS_1 . ':FechaExpedicionFacturaEmisor>' . $invoice->fecha . '</' . self::NS_1 . ':FechaExpedicionFacturaEmisor>
                </' . self::NS_2 . ':IDFactura>
                <' . self::NS_2 . ':FacturaRecibida>
                    <' . self::NS_1 . ':TipoFactura>' . $this->getTipoFactura($invoice) . '</' . self::NS_1 . ':TipoFactura>'
                    . $this->getTipoRectificativa($invoice) . '
                    <' . self::NS_1 . ':FechaOperacion>' . $invoice->fecha . '</' . self::NS_1 . ':FechaOperacion>
                    <' . self::NS_1 . ':ClaveRegimenEspecialOTrascendencia>' . $this->getClaveRegimenEspecial($invoice) . '</' . self::NS_1 . ':ClaveRegimenEspecialOTrascendencia>
                    <' . self::NS_1 . ':ImporteTotal>' . $invoice->total . '</' . self::NS_1 . ':ImporteTotal>
                    <' . self::NS_1 . ':DescripcionOperacion>' . $this->getRecibidasDescripcionOperacion($invoice) . '</' . self::NS_1 . ':DescripcionOperacion>'
                . '<' . self::NS_1 . ':DesgloseFactura>'
                . $this->getDesgloseIva($invoice) . '
                    </' . self::NS_1 . ':DesgloseFactura>'
                . $this->getContraparte($invoice) . '
                    <' . self::NS_1 . ':FechaRegContable>' . $invoice->fecha . '</' . self::NS_1 . ':FechaRegContable>
                    <' . self::NS_1 . ':CuotaDeducible>' . $this->getCuotaDeducible($invoice) . '</' . self::NS_1 . ':CuotaDeducible>
                </' . self::NS_2 . ':FacturaRecibida>
            </' . self::NS_2 . ':RegistroLRFacturasRecibidas>';
        }

        return $xml;
    }
}