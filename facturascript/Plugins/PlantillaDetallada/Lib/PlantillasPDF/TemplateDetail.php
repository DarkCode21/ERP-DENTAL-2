<?php

/**
 * This file is part of PlantillaDetallada plugin for FacturaScripts
 * Copyright (C) 2022-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\PlantillaDetallada\Lib\PlantillasPDF;

use DeepCopy\DeepCopy;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\PlantillasPDF\Lib\PlantillasPDF\BaseTemplate;
use FacturaScripts\Plugins\PlantillasPDF\Lib\PlantillasPDF\Helper\PaymentMethodBankDataHelper;
use FacturaScripts\Plugins\PlantillasPDF\Lib\PlantillasPDF\Helper\ReceiptBankDataHelper;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class TemplateDetail extends BaseTemplate
{
    public function addDualColumnTable(array $data): void
    {
        $html = '';
        $num = 0;
        foreach ($data as $row) {
            if ($num === 0) {
                $html .= '<tr>';
            } elseif ($num % 2 == 0) {
                $html .= '</tr><tr>';
            }

            $html .= '<td width="50%"><b>' . $row['title'] . '</b>: ' . $row['value'] . '</td>';

            $num++;
        }

        $html .= '</tr>';
        $this->writeHTML('<div class="border-radius-10 border-color-font mb-10"><table class="table-big table-dual">' . $html . '</table></div>');
    }

    public function addInvoiceFooter($model)
    {
        $coins = $this->toolBox()->coins();
        $i18n = $this->toolBox()->i18n();
        $receipts = $model->modelClassName() === 'FacturaCliente' && !$this->get('hidereceipts') && !$this->format->hidetotals && !$this->format->hidereceipts
            ? $model->getReceipts() : [];

        if ($receipts) {
            $trs = '<thead>'
                . '<tr>'
                . '<th>' . $i18n->trans('receipt') . '</th>';
            if (!$this->get('hidepaymentmethods') && !$this->format->hidepaymentmethods) {
                $trs .= '<th>' . $i18n->trans('payment-method') . '</th>';
            }

            $trs .= '<th align="right">' . $i18n->trans('amount') . '</th>';
            if (!$this->get('hideexpirationpayment')) {
                $trs .= '<th align="right">' . $i18n->trans('expiration') . '</th>';
            }

            $trs .= '</tr>'
                . '</thead>';
            foreach ($receipts as $receipt) {
                $expiration = $receipt->pagado ? $i18n->trans('paid') : $receipt->vencimiento;
                $expiration .= $this->get('showpaymentdate') ? ' ' . $receipt->fechapago : '';

                $payLink = empty($receipt->url('pay')) ? '' :
                    ' <a href="' . $receipt->url('pay') . '&mpdf=.html">' . $i18n->trans('pay') . '</a>';

                $trs .= '<tr class="border-top-color-font">'
                    . '<td align="center">' . $receipt->numero . '</td>';
                if (!$this->get('hidepaymentmethods') && !$this->format->hidepaymentmethods) {
                    $trs .= '<td align="center">' . ReceiptBankDataHelper::get($receipt, $receipts) . $payLink . '</td>';
                }

                $trs .= '<td align="right">' . $coins->format($receipt->importe) . '</td>';
                if (!$this->get('hideexpirationpayment')) {
                    $trs .= '<td align="right">' . $expiration . '</td>';
                }

                $trs .= '</tr>';
            }

            $this->writeHTML('<div class="border-radius-10 border-color-font mb-10" style="page-break-inside: avoid;"><table class="table-big table-list">' . $trs . '</table></div>');
        } elseif (isset($model->codcliente) && false === $this->format->hidetotals && !$this->get('hidepaymentmethods') && !$this->format->hidepaymentmethods) {
            $expiration = isset($model->finoferta) ? $model->finoferta : '';
            $trs = '<thead>'
                . '<tr>'
                . '<th align="left">' . $i18n->trans('payment-method') . '</th>';

            if (!$this->get('hideexpirationpayment') && !$this->get('hidereceipts') && !$this->format->hidereceipts) {
                $trs .= '<th align="right">' . $i18n->trans('expiration') . '</th>';
            }

            $trs .= '</tr>'
                . '</thead>'
                . '<tr class="border-top-color-font">'
                . '<td align="left">' . PaymentMethodBankDataHelper::get($model) . '</td>';
            if (!$this->get('hideexpirationpayment') && !$this->get('hidereceipts') && !$this->format->hidereceipts) {
                $trs .= '<td align="right">' . $expiration . '</td>';
            }

            $trs .= '</tr>';
            $this->writeHTML('<div class="border-radius-10 border-color-font mb-10" style="page-break-inside: avoid;"><table class="table-big table-list">' . $trs . '</table></div>');
        }

        $this->writeHTML($this->getImageText());

        if (!empty($this->get('endtext'))) {
            $paragraph = '<p class="end-text">' . nl2br($this->get('endtext')) . '</p>';
            $this->writeHTML($paragraph);
        }
    }

    public function addInvoiceHeader($model) {}

    public function addInvoiceLines($model)
    {
        $lines = $model->getLines();
        $this->autoHideLineColumns($lines);

        $tHead = '<thead><tr>';
        foreach ($this->getInvoiceLineFields() as $field) {
            $tHead .= '<th align="' . $field['align'] . '">' . $field['title'] . '</th>';
        }
        $tHead .= '</tr></thead>';

        $tBody = '';
        $numLinea = 1;
        $tLines = [];
        foreach ($lines as $line) {
            $tLines[] = $line;
            $line->numlinea = $numLinea;
            $tBody .= '<tr>';
            foreach ($this->getInvoiceLineFields() as $field) {
                $tBody .= '<td align="' . $field['align'] . '" valign="top" class="uppercase">' . $this->getInvoiceLineValue($model, $line, $field) . '</td>';
            }
            $tBody .= '</tr>';
            $numLinea++;

            if (isset($line->salto_pagina) && $line->salto_pagina) {
                $this->writeHTML('<div class="table-lines"><div class="border-radius-10 border-color-font"><table class="table-big table-list">'
                    . $tHead . $tBody . '</table></div></div>');
                $this->writeHTML('<div class="border-radius-10 border-color-font mb-10 mt-10" style="page-break-inside: avoid;">' . $this->getInvoiceTotalsPartial($model, $tLines) . '</div>');
                $this->mpdf->AddPage();
                $tBody = '';
                $tLines = [];
            }
        }

        $this->writeHTML('<div class="table-lines"><div class="border-radius-10 border-color-font"><table class="table-big table-list">'
            . $tHead . $tBody . '</table></div></div>');

        // clonamos el documento y añadimos los totales para ver si salta de página
        $copier = new DeepCopy();
        $clonedPdf = $copier->copy($this->mpdf);
        $clonedPdf->writeHTML('<div class="border-radius-10 border-color-font mb-10 mt-10">' . $this->getInvoiceTotalsFinal($model) . '</div>');

        // comprobamos si clonedPdf tiene más páginas que el original
        if (count($clonedPdf->pages) > count($this->mpdf->pages)) {
            $this->mpdf->AddPage();
        }

        // si tiene las mismas páginas, añadimos los totales
        $this->writeHTML('<div class="border-radius-10 border-color-font mb-10 mt-10">' . $this->getInvoiceTotalsFinal($model) . '</div>');
    }

    public function addTable(array $rows, array $titles, array $alignments, array $css = []): void
    {
        $html = '<thead><tr>';
        foreach ($titles as $key => $title) {
            $html .= '<th align="' . $alignments[$key] . '">' . $title . '</th>';
        }
        $html .= '</tr></thead>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $key => $cell) {
                $html .= '<td align="' . $alignments[$key] . '">' . $cell . '</td>';
            }
            $html .= '</tr>';
        }

        $this->writeHTML('<div class="border-radius-10 border-color-font mb-10"><table class="table-big table-list">' . $html . '</table></div>');
    }

    public function initMpdf(): void
    {
        parent::initMpdf();

        $marginTop = $this->get('margintop');
        $qrImage = $this->pipe('qrImageHeader', $this->headerModel) ?: '';

        if (!empty($marginTop)) {
            $marginTop = 68;
        }

        // Aumentar el margen superior para evitar solapamiento con el header
        $this->mpdf->SetTopMargin(68);

        if ($this->logoPath) {
            $this->mpdf->SetWatermarkImage($this->logoPath, 0.1, 25, 'P');
            $this->mpdf->showWatermarkImage = true;
        }
    }

    protected function css(): string
    {
        return parent::css()
            . '.float-left { float: left; }'
            . '.float-right { float: right; }'
            . '.clearfix { clear: both; content: ""; display: block; }'
            . '.d-none { display: none; }'
            . '.w-50 {width: 50%;}'
            . '.w-33 {width: 33.3%;}'
            . '.mt-0 {margin-top: 0px;}'
            . '.mt-10 {margin-top: 10px;}'
            . '.ml-3 {margin-left: 3px;}'
            . '.mb-5 {margin-bottom: 5px;}'
            . '.mb-10 {margin-bottom: 10px;}'
            . '.border-radius-10 {border-radius: 10px;}'
            . '.bg1 {background-color: ' . $this->get('color1') . '; color: ' . $this->get('color2') . ';}'
            . '.border-color-font {border: 1px solid ' . $this->get('fontcolor') . ';}'
            . '.border-bottom-color-font {border-bottom: 1px solid ' . $this->get('fontcolor') . ';}'
            . '.color1 {color: ' . $this->get('color1') . ';}'
            . '.color-font {color: ' . $this->get('fontcolor') . ';}'
            . '.table-lines {margin-top: 20px;}'
            . '.table-list, .table-totals {border-spacing: 0px;}'
            . '.table-list th:nth-child(1) {border-left: 0px;}'
            . '.table-list th {border-color: ' . $this->get('color3') . '; border-style: solid; border-left-width: 1px; color: '
            . $this->get('color2') . '; padding: 5px; text-transform: uppercase;}'
            . '.table-list td:nth-child(1) {border-left: 0px;}'
            . '.table-list td {padding: 5px; border-color: ' . $this->get('color3') . '; border-style: solid; border-width: 1px 0px 0px 1px;}'
            . '.table-list thead { display: table-header-group; }'
            . '.table-list tr { page-break-inside: avoid; }'
            . '.table-subtotals {border-left-width: 1px; border-style: solid; border-color: ' . $this->get('color3') . '; padding-left: 5px;}'
            . '.thanks-title {font-size: ' . $this->get('titlefontsize') . 'px; font-weight: bold; color: ' . $this->get('color1') . '; '
            . 'text-align: center;}'
            . '.thanks-text {text-align: center;}'
            . '.title-font-size {font-size: ' . $this->get('titlefontsize') . 'px;}'
            . '.uppercase {text-transform: uppercase;}';
    }

    protected function footer(): string
    {
        $html = '';
        $list = ['PresupuestoCliente', 'PedidoCliente', 'AlbaranCliente', 'FacturaCliente'];

        if ($this->headerModel && in_array($this->headerModel->modelClassName(), $list)) {
            $html .= empty($this->get('thankstitle')) ? '' : '<p class="thanks-title">' . $this->get('thankstitle') . '</p>'
                . '<p class="thanks-text">' . nl2br($this->get('thankstext')) . '</p>';

            if (!empty($this->get('thankstitle')) && !empty($this->get('footertext'))) {
                $html .= $this->spacer();
            }
        }

        return $html . parent::footer();
    }

    /**
     * @param Cliente|Proveedor $subject
     *
     * @return string
     */
    protected function getInvoiceHeaderBillingPhones($subject): string
    {
        if (true !== $this->get('showcustomerphones')) {
            return '';
        }

        $strPhones = $this->getPhones($subject->telefono1, $subject->telefono2);
        return empty($strPhones) ?
            '' :
            '<br/>' . $strPhones;
    }

    protected function getInvoiceTotalsFinal($model): string
    {
        $i18n = $this->toolBox()->i18n();
        $observations = $this->getObservations($model);
        if (!empty($observations)) {
            $observations = '<div class="mb-10 p5"><b class="uppercase">' . $i18n->trans('observations') . '</b>'
                . '<hr class="mt-0" /><br/>' . $observations . '</div>';
        }

        return $this->format->hidetotals ?
            $observations :
            $observations . $this->getInvoiceTotalsPartial($model);
    }

    /**
     * @param BusinessDocument $model
     * @param array $lines
     *
     * @return string
     */
    protected function getInvoiceTotalsPartial($model, array $lines = []): string
    {
        if ($this->format->hidetotals) {
            return '';
        }

        $coins = $this->toolBox()->coins();
        $i18n = $this->toolBox()->i18n();
        $numbers = $this->toolBox()->numbers();
        $trs = '';
        $fields = [
            'netosindto' => $i18n->trans('subtotal'),
            'dtopor1' => $i18n->trans('global-dto'),
            'dtopor2' => $i18n->trans('global-dto-2'),
            'neto' => $i18n->trans('net'),
            'totaliva' => $i18n->trans('taxes'),
            'totalrecargo' => $i18n->trans('re'),
            'totalirpf' => $i18n->trans('irpf'),
            'totalsuplidos' => $i18n->trans('supplied-amount'),
            'total' => $i18n->trans('total')
        ];

        $lines = empty($lines) ? $model->getLines() : $lines;
        $this->getTotalsModel($model, $lines);
        $taxes = $this->getTaxesRows($model, $lines);
        // ocultamos el neto si no hay impuestos o si hay un impuesto y el neto es igual al neto sin dto
        if (empty($taxes) || (count($taxes) == 1 && $model->neto == $model->netosindto)) {
            unset($fields['neto']);
            unset($fields['totaliva']);
        }

        foreach ($fields as $key => $title) {
            if (empty($model->{$key})) {
                continue;
            }

            switch ($key) {
                case 'dtopor1':
                case 'dtopor2':
                    $trs .= '<tr>'
                        . '<th class="text-right"><b>' . $title . '</b>:</th>'
                        . '<td class="text-right">' . $numbers->format($model->{$key}) . '%</td>'
                        . '</tr>';
                    break;

                case 'total':
                    $trs .= '<tr>'
                        . '<th class="font-big text-right"><b>' . $title . '</b>:</th>'
                        . '<td class="font-big text-right"><b>' . $coins->format($model->{$key}) . '</b></td>'
                        . '</tr>';
                    break;

                case 'netosindto':
                    if ($model->netosindto == $model->neto) {
                        break;
                    }
                    // no break

                default:
                    $trs .= '<tr>'
                        . '<th class="text-right"><b>' . $title . '</b>:</th>'
                        . '<td class="text-right">' . $coins->format($model->{$key}) . '</td>'
                        . '</tr>';
                    break;
            }
        }

        return '<table class="table-big table-totals" style="page-break-inside: avoid;">'
            . '<tr>'
            . '<td>' . $this->getInvoiceTaxes($model, $model->getLines()) . '</td>'
            . '<td align="right" valign="top"><table class="table-subtotals">' . $trs . '</table></td>'
            . '</tr>'
            . '</table>';
    }

    protected function headerCenter(): string
    {
        $contactData = [];
        foreach (['telefono1', 'telefono2', 'email', 'web'] as $field) {
            if ($this->empresa->{$field}) {
                $contactData[] = $this->empresa->{$field};
            }
        }

        $html = '<div class="clearfix">'
            . '<div class="header-logo text-center">'
            . '<img src="' . $this->logoPath . '" height="' . $this->get('logosize') . '"/>'
            . '</div>';

        $widthCompany = '100 text-center';
        if (false === is_null($this->headerModel)) {
            $widthCompany = '50';
            $subject = $this->headerModel->getSubject();
            $address = isset($this->headerModel->codproveedor) && !isset($this->headerModel->direccion) ?
                $subject->getDefaultAddress() : $this->headerModel;
            $customerEmail = $this->get('showcustomeremail') && !empty($subject->email) ?
                '<br>' . ToolBox::i18n()->trans('email') . ': ' . $subject->email : '';
            $break = empty($this->headerModel->cifnif) ? '' : '<br/>';

            $labelCode = $this->headerModel->subjectColumn() === 'codcliente' ?
                ToolBox::i18n()->trans('number_customer') :
                ToolBox::i18n()->trans('number_supplier');

            $widthDiv = $this->get('showcustomercode') ? 33 : 50;

            $html .= '<div class="header-subject text-center mb-10">'
                . $this->getSubjectName($this->headerModel) . $break . $this->getSubjectIdFiscalStr($this->headerModel)
                . '<br/>' . $this->combineAddress($address) . $this->getInvoiceHeaderBillingPhones($subject)
                . $customerEmail
                . $this->getShippingName($this->headerModel)
                . '</div>';
        }

        $html .= '<div class="clearfix mb-10">'
            . '<div class="float-left w-' . $widthCompany . ' header-company">'
            . '<div class="bg1 border-radius-10 p10 border-color-font">'
            . '<b>' . $this->empresa->nombre . '</b>'
            . '<br/>' . $this->empresa->tipoidfiscal . ': ' . $this->empresa->cifnif
            . '<br/>' . $this->combineAddress($this->empresa)
            . '<br/>' . implode(' · ', $contactData)
            . '</div>'
            . '</div>';

        if (is_null($this->headerModel)) {
            return $html . '</div>';
        }

        $html .= '<div class="float-right w-50 header-document">';

        $headerTitle = '';
        if ($this->showHeaderTitle) {
            $headerTitle .= '<div class="ml-3 bg1 border-radius-10 p10 border-color-font text-center mb-5"><b>'
                . $this->get('headertitle') . '</b>' . $this->headerTitleRefund();
        }

        $headerTitle .= $this->headerNumber2();
        $headerTitle .= $this->headerNumProveedor();
        $headerTitle .= $this->headerNCF();

        if (!empty($headerTitle)) {
            $headerTitle .= '</div>';
        }

        $html .= $headerTitle;
        $html .= '<div class="ml-3">'
            . '<div class="w-' . $widthDiv . ' float-left">'
            . '<div class="border-radius-10 border-color-font text-center">'
            . '<div class="border-bottom-color-font p5">' . $this->toolBox()->i18n()->trans('page') . '</div>'
            . '<div class="p5">{PAGENO} / {nbpg}</div>'
            . '</div>'
            . '</div>'
            . '<div class="w-' . $widthDiv . ' float-left">'
            . '<div class="border-radius-10 border-color-font text-center">'
            . '<div class="border-bottom-color-font p5">' . $this->toolBox()->i18n()->trans('date') . '</div>'
            . '<div class="p5">' . $this->headerModel->fecha . '</div>'
            . '</div>'
            . '</div>';

        if ($this->get('showcustomercode')) {
            $html .= '<div class="w-' . $widthDiv . ' float-left">'
                . '<div class="border-radius-10 border-color-font text-center">'
                . '<div class="border-bottom-color-font p5">' . $labelCode . '</div>'
                . '<div class="p5">' . $this->headerModel->subjectColumnValue() . '</div>'
                . '</div>'
                . '</div>';
        }

        $html .= '</div>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '<div class="clearfix"></div>';

        return $html;
    }

    protected function headerFull(): string
    {
        return $this->headerCenter();
    }

    protected function headerLeft(): string
    {
        $contactData = [];
        foreach (['telefono1', 'telefono2', 'email', 'web'] as $field) {
            if ($this->empresa->{$field}) {
                $contactData[] = $this->empresa->{$field};
            }
        }

        $qrImage = $this->pipe('qrImageHeader', $this->model) ?: '';
        $qrTitle = $this->pipe('qrTitleHeader', $this->model) ?: '';

        $qrHtml = '';
        if (!empty($qrImage)) {
            $qrHtml = '<div class="float-left" style="width: 20%; padding-right: 15px;">'
                . '<div style="text-align:center;">'
                . '<img src="' . $qrImage . '" style="max-width:100%; height:auto;"/><br/>'
                . (!empty($qrTitle) ? '<span>' . $qrTitle . '</span>' : '')
                . '</div>'
                . '</div>';
        }

        $html = $qrHtml
            . '<div class="clearfix mb-10">'
            . '<div class="float-left w-50 header-company">'
            . '<div class="bg1 border-radius-10 p10 border-color-font">'
            . '<b class="title-font-size uppercase">' . $this->empresa->nombre . '</b>'
            . '<br/>' . $this->empresa->tipoidfiscal . ': ' . $this->empresa->cifnif
            . '<br/>' . $this->combineAddress($this->empresa)
            . '<br/>' . implode(' · ', $contactData)
            . '</div>'
            . '</div>';

        if (is_null($this->headerModel)) {
            return $html
                . '<div class="float-left w-50 header-logo text-right">'
                . '<img src="' . $this->logoPath . '" height="' . $this->get('logosize') . '"/>'
                . '</div>'
                . '</div>';
        }

        $labelCode = $this->headerModel->subjectColumn() === 'codcliente' ?
            $this->toolBox()->i18n()->trans('number_customer') :
            $this->toolBox()->i18n()->trans('number_supplier');

        $widthDiv = $this->get('showcustomercode') ? 33 : 50;

        $subject = $this->headerModel->getSubject();
        $address = isset($this->headerModel->codproveedor) && !isset($this->headerModel->direccion) ? $subject->getDefaultAddress() : $this->headerModel;
        $customerEmail = $this->get('showcustomeremail') && !empty($subject->email) ? '<br>' . $this->toolBox()->i18n()->trans('email') . ': ' . $subject->email : '';
        $break = empty($this->headerModel->cifnif) ? '' : '<br/>';

        $html .= '<div class="float-right w-50 header-document">';

        $headerTitle = '';
        if ($this->showHeaderTitle) {
            $headerTitle .= '<div class="ml-3 bg1 border-radius-10 p5 border-color-font text-center mb-5">'
                . '<b class="title-font-size uppercase">' . $this->get('headertitle') . '</b>'
                . $this->headerTitleRefund();
        }

        $headerTitle .= $this->headerNumber2();
        $headerTitle .= $this->headerNumProveedor();
        $headerTitle .= $this->headerNCF();

        if (!empty($headerTitle)) {
            $headerTitle .= '</div>';
        }

        $html .= $headerTitle;
        $html .= '<div class="ml-3">'
            . '<div class="w-' . $widthDiv . ' float-left">'
            . '<div class="border-radius-10 border-color-font text-center">'
            . '<div class="border-bottom-color-font uppercase p5">' . $this->toolBox()->i18n()->trans('page') . '</div>'
            . '<div class="p5">{PAGENO} / {nbpg}</div>'
            . '</div>'
            . '</div>'
            . '<div class="w-' . $widthDiv . ' float-left">'
            . '<div class="border-radius-10 border-color-font text-center">'
            . '<div class="border-bottom-color-font uppercase p5">' . $this->toolBox()->i18n()->trans('date') . '</div>'
            . '<div class="p5">' . $this->headerModel->fecha . '</div>'
            . '</div>'
            . '</div>';

        if ($this->get('showcustomercode')) {
            $html .= '<div class="w-' . $widthDiv . ' float-left">'
                . '<div class="border-radius-10 border-color-font text-center">'
                . '<div class="border-bottom-color-font p5">' . $labelCode . '</div>'
                . '<div class="p5">' . $this->headerModel->subjectColumnValue() . '</div>'
                . '</div>'
                . '</div>';
        }

        $html .= '</div>'
            . '</div>'
            . '</div>'
            . '<div style="clear: both; height: 10px;"></div>'
            . '<table class="table-big" style="margin-bottom: 15px;">'
            . '<tr>'
            . '<td valign="top" class="header-logo"><img src="' . $this->logoPath . '" height="' . $this->get('logosize') . '"/></td>'
            . '<td align="right" valign="middle" class="header-subject">'
            . $this->getSubjectName($this->headerModel) . $break . $this->getSubjectIdFiscalStr($this->headerModel)
            . '<br/>' . $this->combineAddress($address) . $this->getInvoiceHeaderBillingPhones($subject)
            . $customerEmail
            . $this->getShippingName($this->headerModel)
            . '</td>'
            . '</tr>'
            . '</table>'
            . '<div class="clearfix"></div>';

        return $html;
    }

    protected function headerNCF(): string
    {
        if (false === Plugins::isEnabled('fsRepublicaDominicana')) {
            return '';
        }

        $html = '';
        if (isset($this->headerModel->numeroncf) && !empty($this->headerModel->numeroncf)) {
            $html .= '<b>' . $this->get('desc-ncf-number') . ': </b> ' . $this->headerModel->numeroncf;
        }

        if (isset($this->headerModel->tipocomprobante) && !empty($this->headerModel->tipocomprobante)) {
            $html .= '<b>' . $this->get('tipocomprobante') . ': </b> ' . $this->headerModel->tipocomprobante;
        }

        if (empty($html)) {
            return '';
        }

        return '<div>' . $html . '</div>';
    }

    protected function headerNumber2(): string
    {
        if (isset($this->headerModel->numero2) && !empty($this->headerModel->numero2) && (bool)$this->get('shownumero2')) {
            return '<div>'
                . '<b>' . $this->toolBox()->i18n()->trans('number2') . ': </b> ' . $this->headerModel->numero2
                . '</div>';
        }
        return '';
    }

    protected function headerNumProveedor(): string
    {
        if (isset($this->headerModel->numproveedor) && !empty($this->headerModel->numproveedor) && (bool)$this->get('shownumproveedor')) {
            return '<div>'
                . '<b>' . $this->toolBox()->i18n()->trans('numsupplier') . ': </b> ' . $this->headerModel->numproveedor
                . '</div>';
        }
        return '';
    }

    protected function headerRight(): string
    {
        $contactData = [];
        foreach (['telefono1', 'telefono2', 'email', 'web'] as $field) {
            if ($this->empresa->{$field}) {
                $contactData[] = $this->empresa->{$field};
            }
        }

        $html = '<div class="clearfix mb-10">'
            . '<div class="float-left w-50 header-company">'
            . '<div class="bg1 border-radius-10 p10 border-color-font">'
            . '<b>' . $this->empresa->nombre . '</b>'
            . '<br/>' . $this->empresa->tipoidfiscal . ': ' . $this->empresa->cifnif
            . '<br/>' . $this->combineAddress($this->empresa)
            . '<br/>' . implode(' · ', $contactData)
            . '</div>'
            . '</div>';

        if (is_null($this->headerModel)) {
            return $html
                . '<div class="float-left w-50 header-logo text-right">'
                . '<img src="' . $this->logoPath . '" height="' . $this->get('logosize') . '"/>'
                . '</div>'
                . '</div>';
        }

        $labelCode = $this->headerModel->subjectColumn() === 'codcliente' ?
            $this->toolBox()->i18n()->trans('number_customer') :
            $this->toolBox()->i18n()->trans('number_supplier');

        $widthDiv = $this->get('showcustomercode') ? 33 : 50;

        $subject = $this->headerModel->getSubject();
        $address = isset($this->headerModel->codproveedor) && !isset($this->headerModel->direccion) ? $subject->getDefaultAddress() : $this->headerModel;
        $customerEmail = $this->get('showcustomeremail') && !empty($subject->email) ? '<br>' . $this->toolBox()->i18n()->trans('email') . ': ' . $subject->email : '';
        $break = empty($this->headerModel->cifnif) ? '' : '<br/>';

        $html .= '<div class="float-right w-50 header-document">';

        $headerTitle = '';
        if ($this->showHeaderTitle) {
            $headerTitle .= '<div class="ml-3 bg1 border-radius-10 p10 border-color-font text-center mb-5">'
                . '<b class="title-font-size uppercase">' . $this->get('headertitle') . '</b>'
                . $this->headerTitleRefund();
        }

        $headerTitle .= $this->headerNumber2();
        $headerTitle .= $this->headerNumProveedor();
        $headerTitle .= $this->headerNCF();

        if (!empty($headerTitle)) {
            $headerTitle .= '</div>';
        }

        $html .= $headerTitle;
        $html .= '<div class="ml-3">'
            . '<div class="w-' . $widthDiv . ' float-left">'
            . '<div class="border-radius-10 border-color-font text-center">'
            . '<div class="border-bottom-color-font p5">' . $this->toolBox()->i18n()->trans('page') . '</div>'
            . '<div class="p5">{PAGENO} / {nbpg}</div>'
            . '</div>'
            . '</div>'
            . '<div class="w-' . $widthDiv . ' float-left">'
            . '<div class="border-radius-10 border-color-font text-center">'
            . '<div class="border-bottom-color-font p5">' . $this->toolBox()->i18n()->trans('date') . '</div>'
            . '<div class="p5">' . $this->headerModel->fecha . '</div>'
            . '</div>'
            . '</div>';

        if ($this->get('showcustomercode')) {
            $html .= '<div class="w-' . $widthDiv . ' float-left">'
                . '<div class="border-radius-10 border-color-font text-center">'
                . '<div class="border-bottom-color-font p5">' . $labelCode . '</div>'
                . '<div class="p5">' . $this->headerModel->subjectColumnValue() . '</div>'
                . '</div>'
                . '</div>';
        }

        $html .= '</div>'
            . '</div>'
            . '</div>'
            . '<div style="clear: both; height: 10px;"></div>'
            . '<table class="table-big" style="margin-bottom: 15px;">'
            . '<tr>'
            . '<td valign="middle" class="header-subject">'
            . $this->getSubjectName($this->headerModel) . $break . $this->getSubjectIdFiscalStr($this->headerModel)
            . '<br/>' . $this->combineAddress($address) . $this->getInvoiceHeaderBillingPhones($subject)
            . $customerEmail
            . $this->getShippingName($this->headerModel)
            . '</td>'
            . '<td align="right" valign="top" class="header-logo"><img src="' . $this->logoPath . '" height="' . $this->get('logosize') . '"/></td>'
            . '</tr>'
            . '</table>'
            . '<div class="clearfix"></div>';

        return $html;
    }

    protected function getShippingName($model): string
    {
        if (!isset($model->idcontactoenv) ||
            empty($model->idcontactoenv) ||
            $model->idcontactoenv == $model->idcontactofact) {
            return '';
        }

        $contacto = new Contacto();
        if (false === $contacto->loadFromCode($model->idcontactoenv)) {
            return '';
        }

        $nombre = trim($contacto->nombre . ' ' . $contacto->apellidos);
        $descripcion = trim($contacto->descripcion ?? '');
        $texto = !empty($descripcion) ? $descripcion : $nombre;
        if (empty($texto)) {
            return '';
        }

        return '<br/><b>' . ToolBox::i18n()->trans('shipping-address') . ':</b> ' . ToolBox::utils()->fixHtml($texto);
    }

    protected function headerTitleRefund(): string
    {
        if (false === property_exists($this->headerModel, 'idfacturarect')) {
            return '';
        }

        $rectified = $this->headerModel->get($this->headerModel->idfacturarect);
        if (empty($rectified)) {
            return '';
        }

        return '<div>'
            . $this->toolBox()->i18n()->trans('invoice') . ' '
            . strtolower($this->toolBox()->i18n()->trans('original')) . ': ' . $rectified->codigo
            . ' - ' . $rectified->fecha
            . '</div>';
    }
}
