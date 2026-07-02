<?php
/**
 * Copyright (C) 2020-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PlantillasPDF\Lib\PlantillasPDF;

use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\AgenciaTransporte;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Dinamic\Lib\PlantillasPDF\Helper\PaymentMethodBankDataHelper;
use FacturaScripts\Dinamic\Lib\PlantillasPDF\Helper\ReceiptBankDataHelper;

/**
 * Description of Template4
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class Template4 extends BaseTemplate
{
    /** @var array */
    protected $tLines = [];

    /**
     * @param BusinessDocument $model
     */
    public function addInvoiceFooter($model)
    {
        $html = $this->getInvoiceTotalsFinal($model) . $this->getImageText();

        if (!empty($this->get('endtext'))) {
            $html .= '<p class="end-text">' . nl2br($this->get('endtext')) . '</p>';
        } elseif ($this->format->hidetotals) {
            return;
        }

        $this->writeHTML('<br/><div class="border1">' . $html . '</div>');
    }

    /**
     * @param BusinessDocument $model
     */
    public function addInvoiceHeader($model)
    {
        $html = '<table id="tableHeader" class="table-big border1">'
            . '<tr>'
            . $this->getInvoiceHeaderResume($model)
            . $this->getInvoiceHeaderShipping($model)
            . $this->getInvoiceHeaderBilling($model)
            . '</tr>'
            . '</table>'
            . '<br/>';

        $this->writeHTML($html);
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
        foreach ($lines as $line) {
            $this->tLines[] = $line;
            $line->numlinea = $numLinea;
            $tBody .= '<tr>';
            foreach ($this->getInvoiceLineFields() as $field) {
                $tBody .= '<td class="' . $field['css'] . '" align="' . $field['align'] . '" valign="top">' . $this->getInvoiceLineValue($model, $line, $field) . '</td>';
            }
            $tBody .= '</tr>';
            $numLinea++;

            if (property_exists($line, 'salto_pagina') && $line->salto_pagina) {
                $this->writeHTML('<div class="border1"><div class="table-lines"><table class="table-big table-list">' . $tHead . $tBody . '</table></div></div>');
                $this->writeHTML('<br/><div class="border1">' . $this->getInvoiceTotalsPartial($model) . '</div>');
                $this->mpdf->AddPage();
                $tBody = '';
            }
        }
        $this->writeHTML('<div class="border1"><div class="table-lines"><table class="table-big table-list">' . $tHead . $tBody . '</table></div>');

        $observations = $this->getObservations($model);
        if (!empty($observations)) {
            $this->writeHTML('<p class="p5"><b>' . Tools::lang()->trans('observations') . '</b><br/>' . $observations . '</p>');
        }

        $this->writeHTML('</div>');
    }

    /**
     * @param BusinessDocument $model
     *
     * @return string
     */
    protected function addInvoiceFooterReceipts($model)
    {
        $i18n = Tools::lang();

        $receipts = $model->modelClassName() === 'FacturaCliente' && !$this->get('hidereceipts') && !$this->format->hidetotals && !$this->format->hidereceipts
            ? $model->getReceipts() : [];

        $trs = '';
        if ($receipts) {
            $trs .= '<thead>'
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

            return '<table class="table-big table-list">' . $trs . '</table>';
        }

        if (!isset($model->codcliente)) {
            return '';
        }

        if (!$this->get('hidepaymentmethods') && !$this->format->hidetotals && !$this->format->hidepaymentmethods) {
            $expiration = $model->finoferta ?? '';
            $trs .= '<thead>'
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
        }

        return '<table class="table-big table-list">' . $trs . '</table>';
    }

    /**
     * @param BusinessDocument $model
     *
     * @return string
     */
    protected function addInvoiceFooterTaxes($model)
    {
        return $this->format->hidetotals ? '' :
            '<td valign="top">' . $this->getInvoiceTaxes($model, $this->tLines) . '</td>';
    }

    /**
     * @param BusinessDocument $model
     *
     * @return string
     */
    protected function addInvoiceFooterTotals($model)
    {
        if ($this->format->hidetotals) {
            return '';
        }

        $i18n = Tools::lang();
        $trs = '<tr>'
            . '<td align="right" colspan="2" class="primary-box">'
            . $i18n->trans('total') . ': ' . str_replace(' ', '&nbsp;', Tools::money($model->total, $model->coddivisa)) . '</td>'
            . '</tr>';

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

        $taxes = $this->getTaxesRows($model, $this->tLines);
        $irpfs = $this->format->hide_breakdowns ? [] : $this->getIrpfs($model, $this->tLines);

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
        } else {
            $trs .= '<tr><td colspan="2"><br/></td></tr>';
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
                    break;
            }
        }

        return '<td align="right" valign="top" width="35%"><table>' . $trs . '</table></td>';
    }

    protected function css(): string
    {
        return parent::css()
            . '.title {border-bottom: 2px solid ' . $this->get('color1') . ';}'
            . '.end-text {padding: 2px 5px 2px 5px;}'
            . '.table-list tr:nth-child(even) {background-color: ' . $this->get('color3') . ';}'
            . '.table-list th {background-color: ' . $this->get('color1') . '; color: ' . $this->get('color2') . '; padding: 5px; text-transform: uppercase;}'
            . '.table-list td {padding: 5px;}'
            . '.thanks-title {font-size: ' . $this->get('titlefontsize') . 'px; font-weight: bold; color: ' . $this->get('color1') . '; text-align: center;}'
            . '.thanks-text {text-align: center;}'
            . '.imagetext {margin-top: 15px; text-align: ' . $this->get('endalign') . ';}'
            . '.imagefooter {text-align: ' . $this->get('footeralign') . ';}'
            . '#tableHeader tr tr td:nth-child(1) {white-space: nowrap;}';
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

        $address = isset($model->codproveedor) && !isset($model->direccion) ?
            $model->getSubject()->getDefaultAddress() : $model;

        return '<td class="p3 text-right" valign="top">'
            . $this->getSubjectName($model) . '<br/>' . $this->combineAddress($address)
            . '</td>';
    }

    /**
     * @param BusinessDocument $model
     *
     * @return string
     */
    protected function getInvoiceHeaderResume($model): string
    {
        $i18n = Tools::lang();

        $extra1 = '';
        if ($this->get('logoalign') === 'full-size') {
            $title = empty($this->format->titulo) ? $i18n->trans($model->modelClassName() . '-min') : $this->format->titulo;
            $extra1 .= '<tr>'
                . '<td><b>' . $title . '</b>:</td>'
                . '<td>' . $model->codigo . '</td>'
                . '</tr>';
        }

        // rectified invoice?
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
                . '<td>' . $model->numero2 . '</td>'
                . '</tr>';
        }

        // numproveedor?
        if (isset($model->numproveedor) && !empty($model->numproveedor) && (bool)$this->get('shownumproveedor')) {
            $extra2 .= '<tr>'
                . '<td><b>' . $i18n->trans('numsupplier') . '</b>:</td>'
                . '<td>' . $model->numproveedor . '</td>'
                . '</tr>';
        }

        // cif/nif?
        $extra3 = empty($model->cifnif) ? '' : '<tr>'
            . '<td><b>' . $model->getSubject()->tipoidfiscal . '</b>:</td>'
            . '<td>' . $model->cifnif . '</td>'
            . '</tr>';

        // carrier or tracking-code?
        if (isset($model->codtrans) && !empty($model->codtrans)) {
            $carrier = new AgenciaTransporte();
            $carrierName = $carrier->loadFromCode($model->codtrans) ? $carrier->nombre : '-';
            $extra3 .= '<tr>'
                . '<td><b>' . $i18n->trans('carrier') . '</b>:</td>'
                . '<td>' . $carrierName . '</td>'
                . '</tr>';
        }
        if (isset($model->codigoenv) && !empty($model->codigoenv)) {
            $extra3 .= '<tr>'
                . '<td><b>' . $i18n->trans('tracking-code') . '</b>:</td>'
                . '<td>' . $model->codigoenv . '</td>'
                . '</tr>';
        }

        // agent?
        $extra4 = '';
        if (isset($model->codagente) && !empty($model->codagente) && $this->get('showagent')) {
            $agent = new Agente();
            $agent->loadFromCode($model->codagente);
            $extra4 .= '<tr>'
                . '<td><b>' . $i18n->trans('agent') . '</b>:</td>'
                . '<td>' . $agent->nombre . '</td>'
                . '</tr>';
        }

        $size = empty($extra2) && empty($extra3) ? 170 : 200;
        $html = '<td valign="top" width="' . $size . '">'
            . '<table class="table-big">'
            . $extra1
            . '<tr>'
            . '<td><b>' . $i18n->trans('date') . '</b>:</td>'
            . '<td>' . $model->fecha . '</td>'
            . '</tr>';

        if (!$this->get('hidenumberinvoice')) {
            $html .= '<tr><td><b>' . $i18n->trans('number') . '</b>:</td>'
                . '<td>' . $model->numero . '</td></tr>';
        }

        $html .= $extra2;
        if (!$this->get('hideserieinvoice')) {
            $html .= '<tr><td><b>' . $i18n->trans('serie') . '</b>:</td>'
                . '<td>' . $model->codserie . '</td></tr>';
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
            . $this->getInvoiceHeaderResumePhones($model->getSubject());

        if ($this->get('showcustomeremail') && !empty($model->getSubject()->email)) {
            $html .= '<tr><td><b>' . $i18n->trans('email') . '</b>:</td><td>' . $model->getSubject()->email . '</td></tr>';
        }

        $html .= '</table>'
            . '</td>';

        return $html;
    }

    /**
     * @param Cliente|Proveedor $subject
     *
     * @return string
     */
    protected function getInvoiceHeaderResumePhones($subject): string
    {
        $phone1 = str_replace(' ', '', $subject->telefono1);
        $phone2 = str_replace(' ', '', $subject->telefono2);
        if (true !== $this->get('showcustomerphones')) {
            return '';
        } elseif (empty($subject->telefono1) && empty($subject->telefono2)) {
            return '';
        } elseif (false === empty($subject->telefono1) && empty($subject->telefono2)) {
            return '<tr><td><b>' . Tools::lang()->trans('phone') . '</b>:</td><td>' . $phone1 . '</td></tr>';
        } elseif (false === empty($subject->telefono2) && empty($subject->telefono1)) {
            return '<tr><td><b>' . Tools::lang()->trans('phone') . '</b>:</td><td>' . $phone2 . '</td></tr>';
        }

        return '<tr><td><b>' . Tools::lang()->trans('phone') . '</b>:</td><td>' . $phone1 . '</td></tr>'
            . '<tr><td><b>' . Tools::lang()->trans('phone2') . '</b>:</td><td>' . $phone2 . '</td></tr>';
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
            return '<td class="p3" valign="top">'
                . '<b>' . Tools::lang()->trans('shipping-address') . '</b><br/>'
                . $this->combineAddress($contacto, true) . '</td>';
        }

        return '';
    }

    protected function getInvoiceTotalsFinal($model): string
    {
        $this->tLines = $model->getLines();
        $this->getTotalsModel($model, $this->tLines);
        return '<table class="table-big">'
            . '<tr>'
            . $this->addInvoiceFooterTaxes($model)
            . $this->addInvoiceFooterTotals($model)
            . '</tr>'
            . '<tr>'
            . '<td colspan="2">' . $this->addInvoiceFooterReceipts($model) . '</td>'
            . '</tr>'
            . '</table>';
    }

    protected function getInvoiceTotalsPartial($model): string
    {
        $this->getTotalsModel($model, $this->tLines);
        return '<table class="table-big">'
            . '<tr>'
            . $this->addInvoiceFooterTaxes($model)
            . $this->addInvoiceFooterTotals($model)
            . '</tr>'
            . '</table>';
    }
}
