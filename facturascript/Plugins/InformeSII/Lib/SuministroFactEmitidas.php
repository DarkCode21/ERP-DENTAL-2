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
use FacturaScripts\Dinamic\Model\FacturaCliente;
use SoapClient;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class SuministroFactEmitidas extends SuministroSII
{
    use CommonFactEmitidasRecibidasTrait;

    const WSDL = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/ssii_1_1_bis/fact/ws/SuministroFactEmitidas.wsdl';

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
                ? 'SuministroFactEmitidasPruebas'
                : 'SuministroFactEmitidas';

            $location = $this->getLocationForPort(static::WSDL, $port);
            if (empty($location)) {
                ToolBox::i18nLog()->warning('no-location-for-port', ['%port%' => $port]);
                return [];
            }

            $response = $client->__doRequest(
                $this->xml,
                $location,
                'SuministroLRFacturasEmitidas',
                SOAP_1_1,
                false
            );

            //echo "REQUEST:<br/>" . htmlentities($this->xml) . "<br/>";

            if ($response === null) {
                throw new Exception("siiws_con_error " . openssl_error_string());
            }

            //echo "RESPONSE:<br/>" . htmlentities($response) . "<br/>";

            return $this->readResponse($response, 'LRFacturasEmitidas');
        } catch (Exception $e) {
            ToolBox::log()->error($e->getFile() . ":" . $e->getLine() . " " . $e->getMessage());
        }

        return [];
    }

    protected function generateXml(): void
    {
        $xmlInvoices = $this->getRegistroLRFacturasEmitidas();

        if (empty($xmlInvoices)) {
            return;
        }

        $this->xml = '<?xml version="1.0" encoding="UTF-8"?>
<' . self::NS_ENVELOPE . ':Envelope xmlns:' . self::NS_ENVELOPE . '="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:' . self::NS_1 . '="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/ssii/fact/ws/SuministroInformacion.xsd"
                  xmlns:' . self::NS_2 . '="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/ssii/fact/ws/SuministroLR.xsd">
    <' . self::NS_ENVELOPE . ':Body>
        <' . self::NS_2 . ':SuministroLRFacturasEmitidas>
            <' . self::NS_1 . ':Cabecera>
                <' . self::NS_1 . ':IDVersionSii>1.1</' . self::NS_1 . ':IDVersionSii>
                <' . self::NS_1 . ':Titular>
                    <' . self::NS_1 . ':NombreRazon>' . $this->escape(Empresas::get($this->idempresa)->nombre) . '</' . self::NS_1 . ':NombreRazon>
                    <' . self::NS_1 . ':NIF>' . $this->escape(Empresas::get($this->idempresa)->cifnif) . '</' . self::NS_1 . ':NIF>
                </' . self::NS_1 . ':Titular>
                <' . self::NS_1 . ':TipoComunicacion>' . $this->getTipoComunicacion() . '</' . self::NS_1 . ':TipoComunicacion>
            </' . self::NS_1 . ':Cabecera>
            ' . $xmlInvoices . '
        </' . self::NS_2 . ':SuministroLRFacturasEmitidas>
    </' . self::NS_ENVELOPE . ':Body>
</' . self::NS_ENVELOPE . ':Envelope>';
    }

    protected function getCausaExenta(BusinessDocument $invoice): string
    {
        // si el país de la factura es diferente al de la empresa
        if ($invoice->codpais !== Empresas::get($this->idempresa)->codpais) {
            return 'E5';
        }

        // si el país de la factura es igual al de la empresa
        // es el caso de exportaciones para canarias/ceuta/melilla
        return 'E2';
    }

    protected function getDesgloseFactura(BusinessDocument $invoice): string
    {
        return '<' . self::NS_1 . ':DesgloseFactura>
                    <' . self::NS_1 . ':Sujeta>
                        ' . $this->getDesgloseFacturaSujetaNoExenta($invoice) . '
                        ' . $this->getDesgloseFacturaSujetaExenta($invoice) . '
                    </' . self::NS_1 . ':Sujeta>
                </' . self::NS_1 . ':DesgloseFactura>';
    }

    protected function getDesgloseFacturaSujetaExenta(BusinessDocument $invoice): string
    {
        // Si la única clave de regimen especial es 02 y el TipoComunicacion no es A5 ni A6,
        // solo se puede indicar operación Sujeta/Exenta
        // o si el totaliva es 0
        if ($this->getClaveRegimenEspecial($invoice) === '02'
            && false === in_array($this->getTipoComunicacion(), ['A5', 'A6'])
            || $invoice->totaliva == 0) {
            return '<' . self::NS_1 . ':Exenta>
                        <' . self::NS_1 . ':DetalleExenta>
                            <' . self::NS_1 . ':CausaExencion>' . $this->getCausaExenta($invoice) . '</' . self::NS_1 . ':CausaExencion>
                            <' . self::NS_1 . ':BaseImponible>' . round($invoice->neto, FS_NF0) . '</' . self::NS_1 . ':BaseImponible>
                        </' . self::NS_1 . ':DetalleExenta>
                    </' . self::NS_1 . ':Exenta>';
        }

        return '';
    }

    protected function getDesgloseFacturaSujetaNoExenta(BusinessDocument $invoice): string
    {
        // Si la única clave de regimen especial es 02 y el TipoComunicacion no es A5 ni A6,
        // solo se puede indicar operación Sujeta/Exenta
        if ($this->getClaveRegimenEspecial($invoice) === '02'
            && false === in_array($this->getTipoComunicacion(), ['A5', 'A6'])) {
            return '';
        }

        if ($invoice->totaliva != 0) {
            return '<' . self::NS_1 . ':NoExenta>
                        <' . self::NS_1 . ':TipoNoExenta>S1</' . self::NS_1 . ':TipoNoExenta>'
                . $this->getDesgloseIva($invoice) . '
                    </' . self::NS_1 . ':NoExenta>';
        }

        return '';
    }

    protected function getDesgloseOperacion(BusinessDocument $invoice): string
    {
        return '<' . self::NS_1 . ':DesgloseTipoOperacion>
                        <' . self::NS_1 . ':Entrega>
                            <' . self::NS_1 . ':Sujeta>'
            . $this->getDesgloseOperacionEntregaSujetaExenta($invoice)
            . $this->getDesgloseOperacionEntregaSujetaNoExenta($invoice) . '
                            </' . self::NS_1 . ':Sujeta>
                        </' . self::NS_1 . ':Entrega>
                    </' . self::NS_1 . ':DesgloseTipoOperacion>';
    }

    protected function getDesgloseOperacionEntregaSujetaExenta(BusinessDocument $invoice): string
    {
        if ($invoice->totaliva == 0) {
            return '<' . self::NS_1 . ':Exenta>
                    <' . self::NS_1 . ':DetalleExenta>
                        <' . self::NS_1 . ':CausaExencion>' . $this->getCausaExenta($invoice) . '</' . self::NS_1 . ':CausaExencion>
                        <' . self::NS_1 . ':BaseImponible>' . round($invoice->neto, FS_NF0) . '</' . self::NS_1 . ':BaseImponible>
                    </' . self::NS_1 . ':DetalleExenta>
                </' . self::NS_1 . ':Exenta>';
        }

        return '';
    }

    protected function getDesgloseOperacionEntregaSujetaNoExenta(BusinessDocument $invoice): string
    {
        if ($invoice->totaliva != 0) {
            return '<' . self::NS_1 . ':NoExenta>
                    <' . self::NS_1 . ':TipoNoExenta>S1</' . self::NS_1 . ':TipoNoExenta>'
                . $this->getDesgloseIva($invoice) . '
                </' . self::NS_1 . ':NoExenta>';
        }

        return '';
    }

    protected function getDesgloseOperacionServicioSujetaExenta(BusinessDocument $invoice): string
    {
        if ($invoice->totaliva == 0) {
            return '<' . self::NS_1 . ':Exenta>
                    <' . self::NS_1 . ':DetalleExenta>
                        <' . self::NS_1 . ':CausaExencion>' . $this->getCausaExenta($invoice) . '</' . self::NS_1 . ':CausaExencion>
                        <' . self::NS_1 . ':BaseImponible>' . round($invoice->neto, FS_NF0) . '</' . self::NS_1 . ':BaseImponible>
                    </' . self::NS_1 . ':DetalleExenta>
                </' . self::NS_1 . ':Exenta>';
        }

        return '';
    }

    protected function getDesgloseOperacionServicioSujetaNoExenta(BusinessDocument $invoice): string
    {
        if ($invoice->totaliva != 0) {
            return '<' . self::NS_1 . ':NoExenta>
                    <' . self::NS_1 . ':TipoNoExenta>S1</' . self::NS_1 . ':TipoNoExenta>'
                . $this->getDesgloseIva($invoice) . '
                </' . self::NS_1 . ':NoExenta>';
        }

        return '';
    }

    protected function getRegistroLRFacturasEmitidas(): string
    {
        $xml = '';

        // buscamos las facturas emitidas
        $invoiceModel = new FacturaCliente();
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

        if ($this->provincia) {
            $where[] = new DataBaseWhere('provincia', $this->provincia);
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

            $xml .=
                '            <' . self::NS_2 . ':RegistroLRFacturasEmitidas>
                <' . self::NS_1 . ':PeriodoLiquidacion>
                    <' . self::NS_1 . ':Ejercicio>' . date('Y', strtotime($invoice->fecha)) . '</' . self::NS_1 . ':Ejercicio>
                    <' . self::NS_1 . ':Periodo>' . date('m', strtotime($invoice->fecha)) . '</' . self::NS_1 . ':Periodo>
                </' . self::NS_1 . ':PeriodoLiquidacion>
                <' . self::NS_2 . ':IDFactura>
                    <' . self::NS_1 . ':IDEmisorFactura>
                        <' . self::NS_1 . ':NIF>' . $this->escape(Empresas::get($this->idempresa)->cifnif) . '</' . self::NS_1 . ':NIF>
                    </' . self::NS_1 . ':IDEmisorFactura>
                    <' . self::NS_1 . ':NumSerieFacturaEmisor>' . $this->escape($invoice->codigo) . '</' . self::NS_1 . ':NumSerieFacturaEmisor>
                    <' . self::NS_1 . ':FechaExpedicionFacturaEmisor>' . $invoice->fecha . '</' . self::NS_1 . ':FechaExpedicionFacturaEmisor>
                </' . self::NS_2 . ':IDFactura>
                <' . self::NS_2 . ':FacturaExpedida>
                    <' . self::NS_1 . ':TipoFactura>' . $this->getTipoFactura($invoice) . '</' . self::NS_1 . ':TipoFactura>
                    ' . $this->getTipoRectificativa($invoice) . '
                    <' . self::NS_1 . ':ClaveRegimenEspecialOTrascendencia>' . $this->getClaveRegimenEspecial($invoice) . '</' . self::NS_1 . ':ClaveRegimenEspecialOTrascendencia>
                    <' . self::NS_1 . ':ImporteTotal>' . round($invoice->total, FS_NF0) . '</' . self::NS_1 . ':ImporteTotal>
                    <' . self::NS_1 . ':DescripcionOperacion>Venta de mercadería</' . self::NS_1 . ':DescripcionOperacion>
                    ' . $this->getContraparte($invoice) . '
                    <' . self::NS_1 . ':TipoDesglose>
                    ' . $this->getTipoDesglose($invoice) . '
                    </' . self::NS_1 . ':TipoDesglose>
                </' . self::NS_2 . ':FacturaExpedida>
            </' . self::NS_2 . ':RegistroLRFacturasEmitidas>';
        }

        return $xml;
    }

    protected function getTipoDesglose(BusinessDocument $invoice): string
    {
        // obtenemos la primera letra del cif de la factura
        if (substr(strtoupper($invoice->cifnif), 0, 1) === 'N' && $invoice->codpais === 'ESP') {
            return $this->getDesgloseOperacion($invoice);
        }

        if (in_array(Paises::get($invoice->codpais)->codiso, $this->paisesIntracomunitarios)) {
            return $this->getDesgloseOperacion($invoice);
        }

        return $this->getDesgloseFactura($invoice);
    }
}