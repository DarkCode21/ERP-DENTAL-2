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
 * Description of Template2
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class Template2 extends BaseTemplate
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

        if (!empty($this->get('endtext')) && !empty($this->getObservations($model))) {
            $html = '<p class="end-text">' . nl2br($this->get('endtext')) . '</p>';
            $this->writeHTML($html);
        }
    }

    /**
     * @param BusinessDocument $model
     */
    public function addInvoiceHeader($model)
    {
        $this->showHeaderTitle = false;

        $html = '<td class="primary-box">' . $this->get('headertitle') . '</td>'
            . '<td align="center" class="seccondary-box">' . $model->fecha . '</td>';

        $html .= $this->format->hidetotals ? '' :
            '<td align="right" class="seccondary-box">' . Tools::money($model->total, $model->coddivisa) . '</td>';

        $this->writeHTML('<br/><table class="table-big table-boxes"><tr>' . $html . '</tr></table>');

        $html2 = $this->getInvoiceHeaderBilling($model)
            . $this->getInvoiceHeaderShipping($model)
            . $this->getInvoiceHeaderResume($model);
        $this->writeHTML('<table class="table-big"><tr>' . $html2 . '</tr></table><br/>');
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
                $this->writeHTML('<div class="table-lines"><table class="table-big table-list">' . $tHead . $tBody . '</table></div>');
                $this->writeHTML($this->getInvoiceTotalsPartial($model, $tLines, 'mt-20'));
                $this->mpdf->AddPage();
                $tBody = '';
            }
        }

        $this->writeHTML('<div class="table-lines"><table class="table-big table-list">' . $tHead . $tBody . '</table></div>');

        // clonamos el documento y añadimos los totales para ver si salta de página
        $copier = new DeepCopy();
        $clonedPdf = $copier->copy($this->mpdf);
        $clonedPdf->writeHTML($this->getInvoiceTotalsFinal($model, 'mt-20'));

        // comprobamos si clonedPdf tiene más páginas que el original
        if (count($clonedPdf->pages) > count($this->mpdf->pages)) {
            $this->mpdf->AddPage();
        }

        // si tiene las mismas páginas, añadimos los totales
        $this->writeHTML($this->getInvoiceTotalsFinal($model, 'mt-20'));
    }

    protected function css(): string
    {
        return parent::css()
            . '.footer-text {background-color: ' . $this->get('color3') . '; padding: 10px;}'
            . '.thanks-title {font-size: ' . $this->get('titlefontsize') . 'px; font-weight: bold; color: ' . $this->get('color1') . '; '
            . 'text-align: center;}'
            . '.thanks-text {text-align: center;}'
            . '.table-border {border-top: 1px solid ' . $this->get('color1') . '; border-bottom: 1px solid ' . $this->get('color1') . ';}'
            . '.invoice-total {font-size: ' . $this->get('titlefontsize') . 'px; font-weight: bold; color: ' . $this->get('color1') . ';}'
            . '.table-boxes {border-spacing: 3px;}'
            . '.table-dual {border-top: 1px solid ' . $this->get('color1') . '; border-bottom: 1px solid ' . $this->get('color1') . ';}'
            . '.table-list {border-spacing: 2px; border-bottom: 1px solid ' . $this->get('color1') . ';}'
            . '.table-list tr:nth-child(even) {background-color: ' . $this->get('color3') . ';}'
            . '.table-list th {background-color: ' . $this->get('color1') . '; color: ' . $this->get('color2') . '; padding: 5px; text-transform: uppercase;}'
            . '.table-list td {padding: 5px;}'
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
                $html .= $this->spacer();
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
        $customerEmail = $this->get('showcustomeremail') && !empty($subject->email) ?
            '<br>' . Tools::lang()->trans('email') . ':
            ' . $subject->email : '';
        $break = empty($model->cifnif) ? '' : '<br/>';
        return '<td>'
            . '<b>' . $this->getSubjectTitle($model) . '</b> ' . $customerCode
            . '<br/>' . $this->getSubjectName($model) . $break . $this->getSubjectIdFiscalStr($model)
            . '<br/>' . $this->combineAddress($address) . $this->getInvoiceHeaderBillingPhones($subject)
            . $customerEmail
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

        $size = empty($extra2) ? 170 : 200;
        $html = '<td width="' . $size . '">'
            . '<table class="table-big">'
            . $extra1;

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
            return '<td><b>' . Tools::lang()->trans('shipping-address') . '</b>'
                . '<br/>' . $this->combineAddress($contacto, true) . '</td>';
        }

        return '';
    }

    /**
     * @param BusinessDocument $model
     * @param string $css
     *
     * @return string
     */
    protected function getInvoiceTotalsFinal($model, string $css = ''): string
    {
        $observations = '';
        if (!empty($this->getObservations($model))) {
            $observations .= '<p><b>' . Tools::lang()->trans('observations') . '</b><br/>'
                . $this->getObservations($model) . '</p>&nbsp;';
        }

        if ($this->format->hidetotals) {
            return $observations;
        }

        $text = empty($observations) ? '<p class="end-text">' . $this->get('endtext') . '</p>' : $observations;
        return $this->getInvoiceTotalsPartial($model, [], $css) . $text;
    }

    protected function getInvoiceTotalsPartial($model, array $lines = [], string $css = ''): string
    {
        if ($this->format->hidetotals) {
            return '';
        }

        $i18n = Tools::lang();
        $trs = '';
        $fields = [
            'netosindto' => $i18n->trans('subtotal'),
            'dtopor1' => $i18n->trans('global-dto'),
            'dtopor2' => $i18n->trans('global-dto-2'),
            'neto' => $i18n->trans('net'),
            'totaliva' => $i18n->trans('taxes'),
            'totalrecargo' => $i18n->trans('re'),
            'totalirpf' => $i18n->trans('retention'),
            'totalsuplidos' => $i18n->trans('supplied-amount')
        ];

        $lines = empty($lines) ? $model->getLines() : $lines;
        $this->getTotalsModel($model, $lines);
        $taxes = $this->getTaxesRows($model, $lines);
        $irpfs = $this->format->hide_breakdowns ? [] : $this->getIrpfs($model, $lines);

        // pintamos los irpfs
        foreach ($irpfs as $irpf) {
            $trs .= '<tr>'
                . '<td align="right"><b>' . $irpf['name'] . '</b>:</td>'
                . '<td class="nowrap" align="right">' . Tools::money($irpf['total'], $model->coddivisa) . '</td>'
                . '</tr>';
        }

        // ocultamos el neto si no hay impuestos o si hay un impuesto y el neto es igual al neto sin dto
        if (empty($taxes['iva']) || (count($taxes['iva']) == 1 && $model->neto == $model->netosindto)) {
            unset($fields['neto']);
            unset($fields['totaliva']);
        }

        // si tenemos marcada la opción de ocultar desgloses, eliminamos todos los campos
        if ($this->format->hide_breakdowns) {
            $fields = [];
        }

        foreach ($fields as $key => $title) {
            if (empty($model->{$key}) || $key === 'totalirpf') {
                continue;
            }

            switch ($key) {
                case 'dtopor1':
                case 'dtopor2':
                    $trs .= '<tr>'
                        . '<td align="right"><b>' . $title . '</b>:</td>'
                        . '<td class="nowrap" align="right">' . Tools::number($model->{$key}) . '%</td>'
                        . '</tr>';
                    break;

                case 'netosindto':
                    if ($model->netosindto == $model->neto) {
                        break;
                    }
                // no break

                default:
                    $trs .= '<tr>'
                        . '<td align="right"><b>' . $title . '</b>:</td>'
                        . '<td class="nowrap" align="right">' . Tools::money($model->{$key}, $model->coddivisa) . '</td>'
                        . '</tr>';
            }
        }

        $trs .= '<tr>'
            . '<td class="nowrap" align="right" class="primary-box" colspan="2">'
            . Tools::lang()->trans('total') . ': ' . str_replace(' ', '&nbsp;', Tools::money($model->total, $model->coddivisa))
            . '</td>'
            . '</tr>';

        return '<table class="table-big ' . $css . '">'
            . '<tr>'
            . '<td valign="top">' . $this->getInvoiceTaxes($model, $lines) . '</td>'
            . '<td align="right" valign="top" width="35%"><table>' . $trs . '</table></td>'
            . '</tr>'
            . '</table>';
    }
}
