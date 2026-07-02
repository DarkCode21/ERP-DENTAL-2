<?php
/**
 * Copyright (C) 2019-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PlantillasPDF\Lib\PlantillasPDF;

use DeepCopy\DeepCopy;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\AgenciaTransporte;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Dinamic\Lib\PlantillasPDF\Helper\PaymentMethodBankDataHelper;
use FacturaScripts\Dinamic\Lib\PlantillasPDF\Helper\ReceiptBankDataHelper;

/**
 * Description of Template3
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class Template3 extends BaseTemplate
{
    /**
     * @param BusinessDocument|FacturaCliente $model
     */
    public function addInvoiceFooter($model)
    {
        $i18n = Tools::lang();
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

                $trs .= '<tr>'
                    . '<td align="center">' . $receipt->numero . '</td>';
                if (!$this->get('hidepaymentmethods') && !$this->format->hidepaymentmethods) {
                    $trs .= '<td align="center">' . ReceiptBankDataHelper::get($receipt, $receipts) . $payLink . '</td>';
                }

                $trs .= '<td align="right">' . Tools::money($receipt->importe, $model->coddivisa) . '</td>';
                if (!$this->get('hideexpirationpayment')) {
                    $trs .= '<td align="right">' . $expiration . '</td>';
                }

                $trs .= '</tr>';
            }

            $this->writeHTML('<br/><table class="table-big table-list">' . $trs . '</table>');
        } elseif (isset($model->codcliente) && false === $this->format->hidetotals && !$this->get('hidepaymentmethods') && !$this->format->hidepaymentmethods) {
            $expiration = $model->finoferta ?? '';
            $trs = '<thead>'
                . '<tr>'
                . '<th align="left">' . $i18n->trans('payment-method') . '</th>';
            if (!$this->get('hideexpirationpayment') && !$this->get('hidereceipts') && !$this->format->hidereceipts) {
                $trs .= '<th align="right">' . $i18n->trans('expiration') . '</th>';
            }

            $trs .= '</tr>'
                . '</thead>'
                . '<tr>'
                . '<td align="left">' . PaymentMethodBankDataHelper::get($model) . '</td>';
            if (!$this->get('hideexpirationpayment') && !$this->get('hidereceipts') && !$this->format->hidereceipts) {
                $trs .= '<td align="right">' . $expiration . '</td>';
            }

            $trs .= '</tr>';
            $this->writeHTML('<br/><table class="table-big table-list">' . $trs . '</table>');
        }

        $this->writeHTML($this->getImageText());

        if (!empty($this->get('endtext'))) {
            $html = '<p class="end-text">' . nl2br($this->get('endtext')) . '</p>';
            $this->writeHTML($html);
        }
    }

    /**
     * @param BusinessDocument $model
     */
    public function addInvoiceHeader($model)
    {
        $html = $this->getInvoiceHeaderResume($model)
            . $this->getInvoiceHeaderBilling($model)
            . $this->getInvoiceHeaderShipping($model);
        $this->writeHTML('<table class="table-big"><tr>' . $html . '</tr></table><br/>');
    }

    /**
     * @param BusinessDocument $model
     */
    public function addInvoiceLines($model)
    {
        $lines = $model->getLines();
        $this->autoHideLineColumns($lines);

        $tHead = '<thead><tr>';
        foreach ($this->getInvoiceLineFields() as $field) {
            $tHead .= '<th class="' . $field['css'] . '" align="' . $field['align'] . '">' . $field['title'] . '</th>';
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
                $tBody .= '<td class="' . $field['css'] . '" align="' . $field['align'] . '" valign="top">' . $this->getInvoiceLineValue($model, $line, $field) . '</td>';
            }
            $tBody .= '</tr>';
            $numLinea++;

            if (property_exists($line, 'salto_pagina') && $line->salto_pagina) {
                $this->writeHTML('<div class="border1"><div class="table-lines"><table class="table-big table-list no-border">' . $tHead . $tBody . '</table></div></div>');
                $this->writeHTML($this->getInvoiceTotals($model, $tLines, 'mt-20'));
                $this->mpdf->AddPage();
                $tBody = '';
            }
        }

        $this->writeHTML('<div class="border1"><div class="table-lines"><table class="table-big table-list no-border">' . $tHead . $tBody . '</table></div>');
        if (!empty($this->getObservations($model))) {
            $this->writeHTML('<p class="p3"><b>' . Tools::lang()->trans('observations') . '</b><br/>' . $this->getObservations($model) . '</p>');
        }

        $this->writeHTML('</div>' . '<br/>');

        // clonamos el documento y añadimos los totales para ver si salta de página
        $copier = new DeepCopy();
        $clonedPdf = $copier->copy($this->mpdf);
        $clonedPdf->writeHTML($this->getInvoiceTotals($model, [], 'mt-20'));

        // comprobamos si clonedPdf tiene más páginas que el original
        if (count($clonedPdf->pages) > count($this->mpdf->pages)) {
            $this->mpdf->AddPage();
        }

        // si tiene las mismas páginas, añadimos los totales
        $this->writeHTML($this->getInvoiceTotals($model, [], 'mt-20'));

    }

    protected function css(): string
    {
        return parent::css()
            . '.title {border-bottom: 2px solid ' . $this->get('color1') . ';}'
            . '.td-window {border: 1px solid ' . $this->get('color1') . ';}'
            . '.th-window {background-color: ' . $this->get('color1') . '; color: ' . $this->get('color2') . '; font-weight: bold; padding: 5px; text-transform: uppercase;}'
            . '.table-list {border: 1px solid ' . $this->get('color1') . ';}'
            . '.table-list tr:nth-child(even) {background-color: ' . $this->get('color3') . ';}'
            . '.table-list th {background-color: ' . $this->get('color1') . '; color: ' . $this->get('color2') . '; padding: 5px; text-transform: uppercase;}'
            . '.table-list td {padding: 5px;}'
            . '.thanks-title {font-size: ' . $this->get('titlefontsize') . 'px; font-weight: bold; color: ' . $this->get('color1') . '; text-align: center;}'
            . '.thanks-text {text-align: center;}'
            . '.imagetext {margin-top: 15px; text-align: ' . $this->get('endalign') . ';}'
            . '.imagefooter {text-align: ' . $this->get('footeralign') . ';}';
    }

    protected function footer(): string
    {
        $html = '';
        $list = ['PresupuestoCliente', 'PedidoCliente', 'AlbaranCliente', 'FacturaCliente'];

        if ($this->headerModel && in_array($this->headerModel->modelClassName(), $list)) {
            $html .= empty($this->get('thankstitle')) ? '' : '<p class="thanks-title">' . $this->get('thankstitle') . '</p>'
                . '<p class="thanks-text">' . nl2br($this->get('thankstext')) . '</p>';

            if (!empty($this->get('thankstitle')) && !empty($this->get('footertext'))) {
                $html .= '<br/>';
            }
        }

        return $html . parent::footer();
    }

    /**
     * @param BusinessDocument $model
     *
     * @return string
     */
    protected function getInvoiceHeaderBilling($model): string
    {
        if ($this->format->hidebillingaddress) {
            return '';
        }

        $subject = $model->getSubject();
        $address = isset($model->codproveedor) && !isset($model->direccion) ? $subject->getDefaultAddress() : $model;
        $customerCode = $this->get('showcustomercode') ? $model->subjectColumnValue() : '';
        $customerEmail = $this->get('showcustomeremail') && !empty($subject->email) ? '<br>' . Tools::lang()->trans('email') . ': ' . $subject->email : '';
        $break = empty($model->cifnif) ? '' : '<br/>';
        return '<td class="td-window" valign="top">'
            . '<table class="table-big">'
            . '<tr><td class="th-window">' . $this->getSubjectTitle($model) . ' ' . $customerCode . '</td></tr>'
            . '<tr>'
            . '<td>' . $this->getSubjectName($model) . $break . $this->getSubjectIdFiscalStr($model) . '<br/>'
            . $this->combineAddress($address) . $this->getInvoiceHeaderBillingPhones($subject) . $customerEmail . '</td>'
            . '</tr>'
            . '</table>'
            . '</td>';
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

    /**
     * @param BusinessDocument $model
     *
     * @return string
     */
    protected function getInvoiceHeaderResume($model): string
    {
        $i18n = Tools::lang();

        // rectified invoice?
        $extra1 = '';
        $rectifyingInvoice = $this->getRectifyingInvoice();
        if ($rectifyingInvoice) {
            $extra1 .= '<tr>'
                . '<td><b>' . $i18n->trans('invoice') . ' ' . strtolower($i18n->trans('original')) . '</b>:</td>'
                . '<td align="right" valign="bottom">' . $rectifyingInvoice->codigo . '</td>'
                . '</tr>';
            $extra1 .= '<tr>'
                . '<td><b>' . $i18n->trans('date') . ' ' . strtolower($i18n->trans('original')) . '</b>:</td>'
                . '<td align="right" valign="bottom">' . $rectifyingInvoice->fecha . '</td>'
                . '</tr>';
        }

        // number2?
        $extra2 = '';
        if (isset($model->numero2) && !empty($model->numero2) && (bool)$this->get('shownumero2')) {
            $extra2 .= '<tr>'
                . '<td><b>' . $i18n->trans('number2') . '</b>:</td>'
                . '<td align="right" valign="bottom">' . $model->numero2 . '</td>'
                . '</tr>';
        }

        // numproveedor?
        if (isset($model->numproveedor) && !empty($model->numproveedor) && (bool)$this->get('shownumproveedor')) {
            $extra2 .= '<tr>'
                . '<td><b>' . $i18n->trans('numsupplier') . '</b>:</td>'
                . '<td align="right" valign="bottom">' . $model->numproveedor . '</td>'
                . '</tr>';
        }

        // carrier or tracking-code?
        $extra3 = '';
        if (isset($model->codtrans) && !empty($model->codtrans)) {
            $carrier = new AgenciaTransporte();
            $carrierName = $carrier->loadFromCode($model->codtrans) ? $carrier->nombre : '-';
            $extra3 .= '<tr>'
                . '<td><b>' . $i18n->trans('carrier') . '</b>:</td>'
                . '<td align="right" valign="bottom">' . $carrierName . '</td>'
                . '</tr>';
        }
        if (isset($model->codigoenv) && !empty($model->codigoenv)) {
            $extra3 .= '<tr>'
                . '<td><b>' . $i18n->trans('tracking-code') . '</b>:</td>'
                . '<td align="right" valign="bottom">' . $model->codigoenv . '</td>'
                . '</tr>';
        }

        // agent?
        $extra4 = '';
        if (isset($model->codagente) && !empty($model->codagente) && (bool)$this->get('showagent')) {
            $agent = new Agente();
            $agent->loadFromCode($model->codagente);
            $extra4 .= '<tr>'
                . '<td><b>' . $i18n->trans('agent') . '</b>:</td>'
                . '<td align="right" valign="bottom">' . $agent->nombre . '</td>'
                . '</tr>';
        }

        $html = '<td class="td-window" valign="top">'
            . '<table class="table-big">'
            . '<tr>'
            . '<td class="th-window" colspan="2">' . $this->get('headertitle') . '</td>'
            . '</tr>'
            . $extra1
            . '<tr>'
            . '<td><b>' . $i18n->trans('date') . '</b>:</td>'
            . '<td align="right" valign="bottom">' . $model->fecha . '</td>'
            . '</tr>';

        if (!$this->get('hidenumberinvoice')) {
            $html .= '<tr><td><b>' . $i18n->trans('number') . '</b>:</td>'
                . '<td align="right" valign="bottom">' . $model->numero . '</td></tr>';
        }

        $html .= $extra2;
        if (!$this->get('hideserieinvoice')) {
            $html .= '<tr><td><b>' . $i18n->trans('serie') . '</b>:</td>'
                . '<td align="right" valign="bottom">' . $model->codserie . '</td></tr>';
        }

        $classNCF = '\\FacturaScripts\\Dinamic\\Model\\NCFTipo';
        if (isset($model->numeroncf) && !empty($model->numeroncf) && class_exists($classNCF)) {
            $html .= '<tr><td><b>' . $i18n->trans('desc-ncf-number') . '</b>:</td>'
                . '<td align="right" valign="bottom">' . $model->numeroncf . '</td></tr>';
        }

        if (isset($model->tipocomprobante) && !empty($model->tipocomprobante) && class_exists($classNCF)) {
            $html .= '<tr><td><b>' . $i18n->trans('tipocomprobante') . '</b>:</td>'
                . '<td align="right" valign="bottom">' . $model->tipocomprobante . '</td></tr>';
        }

        $html .= $extra3
            . $extra4
            . '</table>'
            . '</td>';
        return $html;
    }

    /**
     * @param BusinessDocument $model
     *
     * @return string
     */
    protected function getInvoiceHeaderShipping($model): string
    {
        if ($this->format->hideshippingaddress || $this->get('hideshipping')
            || !isset($model->idcontactoenv) || $model->idcontactoenv == $model->idcontactofact) {
            return '';
        }

        $contacto = new Contacto();
        if (!empty($model->idcontactoenv) && $contacto->loadFromCode($model->idcontactoenv)) {
            return '<td class="td-window" valign="top">'
                . '<table class="table-big">'
                . '<tr><td class="th-window">' . Tools::lang()->trans('shipping-address') . '</td></tr>'
                . '<tr><td>' . $this->combineAddress($contacto, true) . '</td></tr>'
                . '</table>'
                . '</td>';
        }

        return '';
    }

    /**
     * @param BusinessDocument $model
     * @param array $lines
     * @param string $css
     *
     * @return string
     */
    protected function getInvoiceTotals($model, array $lines = [], string $css = ''): string
    {
        if ($this->format->hidetotals) {
            return '';
        }

        $i18n = Tools::lang();
        $ths = '<th align="center">' . $i18n->trans('currency') . '</th>';
        $tds = '<td align="center">' . $model->coddivisa . '</td>';
        $fields = [
            'netosindto' => $i18n->trans('subtotal'),
            'dtopor1' => $i18n->trans('global-dto'),
            'dtopor2' => $i18n->trans('global-dto-2'),
            'neto' => $i18n->trans('net'),
            'totaliva' => $i18n->trans('taxes'),
            'totalrecargo' => $i18n->trans('re'),
            'totalirpf' => $i18n->trans('retention'),
            'totalsuplidos' => $i18n->trans('supplied-amount'),
            'total' => $i18n->trans('total')
        ];

        $lines = empty($lines) ? $model->getLines() : $lines;
        $this->getTotalsModel($model, $lines);
        $irpfs = $this->format->hide_breakdowns ? [] : $this->getIrpfs($model, $lines);

        // pintamos los irpfs
        foreach ($irpfs as $irpf) {
            $ths .= '<th align="center">' . $irpf['name'] . '</th>';
            $tds .= '<td align="center">' . Tools::money($irpf['total'], $model->coddivisa) . '</td>';
        }

        // si tenemos marcada la opción de ocultar desgloses, eliminamos todos los campos excepto el total
        if ($this->format->hide_breakdowns) {
            $fields = ['total' => $i18n->trans('total')];
        }

        foreach ($fields as $key => $title) {
            if (empty($model->{$key}) || $key === 'totalirpf') {
                continue;
            }

            switch ($key) {
                case 'dtopor1':
                case 'dtopor2':
                    $ths .= '<th align="center">' . $title . '</th>';
                    $tds .= '<td class="nowrap" align="center">' . Tools::number($model->{$key}) . '%</td>';
                    break;

                case 'total':
                    $ths .= '<th class="text-center">' . $title . '</th>';
                    $tds .= '<td class="nowrap" class="font-big text-center"><b>' . Tools::money($model->{$key}, $model->coddivisa) . '</b></td>';
                    break;

                case 'netosindto':
                    if ($model->netosindto == $model->neto) {
                        break;
                    }
                // no break

                default:
                    $ths .= '<th align="center">' . $title . '</th>';
                    $tds .= '<td class="nowrap" align="center">' . Tools::money($model->{$key}, $model->coddivisa) . '</td>';
                    break;
            }
        }

        $html = '';
        $htmlTaxes = $this->getInvoiceTaxes($model, $lines, 'table-big table-list');
        if (!empty($htmlTaxes)) {
            $html .= '<br/>' . $htmlTaxes;
        }

        $htmlSummary = '<table class="table-big table-list" style="margin-top:5px;">'
            . '<thead><tr>'
            . '<th align="left">' . $i18n->trans('net') . '</th>'
            . '<th align="right">' . $i18n->trans('taxes') . '</th>'
            . '<th align="right">' . $i18n->trans('total') . '</th>'
            . '</tr></thead>'
            . '<tr>'
            . '<td align="left">' . Tools::money($model->neto, $model->coddivisa) . '</td>'
            . '<td align="right">' . Tools::money($model->totaliva, $model->coddivisa) . '</td>'
            . '<td align="right"><b>' . Tools::money($model->total, $model->coddivisa) . '</b></td>'
            . '</tr>'
            . '</table>';
        $html .= '<br/>' . $htmlSummary;

        return $html;
    }
}
