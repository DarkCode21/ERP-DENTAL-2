<?php

/**
 * Copyright (C) 2022-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TPVneo\Lib\TPVneo;

use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\DivisaTools;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Base\Translator;
use FacturaScripts\Core\DataSrc\Agentes;
use FacturaScripts\Core\DataSrc\FormasPago;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Model\Base\SalesDocumentLine;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Divisa;
use FacturaScripts\Dinamic\Model\PresupuestoCliente;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Dinamic\Model\TpvCaja;
use FacturaScripts\Dinamic\Model\TpvPago;
use FacturaScripts\Dinamic\Model\TpvTerminal;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\PrePagos\Model\PrePago;

use FacturaScripts\Dinamic\Model\Empresa; #ADD ERICK
use FacturaScripts\Dinamic\Model\LineaFactura; #ADD ERICK
use FacturaScripts\Dinamic\Model\FormatoDocumento; #ADD ERICK
use FacturaScripts\Dinamic\Model\FormaPago; #ADD ERICK
use FacturaScripts\Dinamic\Model\Producto; #ADD ERICK

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class SaleForm
{
    use TPVTrait;

    /** @var SalesDocument */
    protected static $doc;

    /** @var SalesDocumentLine[] */
    protected static $lines = [];

    protected static $lastDocSave;

    public static function amount(TpvTerminal $tpv): string
    {
        self::changeDivisa($tpv->coddivisa);
        return ToolBox::coins()::format(self::$doc->total ?? 0);
    }

    public static function apply(array $formData, User $user, TpvTerminal $tpv, ?string $codagente, bool $changePrice)
    {
        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $tpv->doctype;
        self::$doc = new $modelClass();
        self::$doc->total = 0;
        $user->codagente = $codagente;
        self::$doc->setAuthor($user);

        $cliente = new Cliente();
        $codcliente = empty($formData['codcliente']) ? $tpv->codcliente : $formData['codcliente'];
        $cliente->loadFromCode($codcliente);
        self::$doc->setSubject($cliente);

        // lines
        $linesCart = $formData['linesCart'] ?? 100;
        for ($num = 1; $num <= $linesCart; $num++) {
            if (!isset($formData['descripcion_' . $num])) {
                continue;
            }

            if ($formData['action'] === 'rm-line' && $formData['action-code'] == $num) {
                continue;
            }

            $newLine = empty($formData['referencia_' . $num]) ?
                self::$doc->getNewLine() :
                self::$doc->getNewProductLine($formData['referencia_' . $num]);

            $newLine->cantidad = (float)$formData['cantidad_' . $num];
            $newLine->descripcion = $formData['descripcion_' . $num];
            $newLine->idlinea = $formData['idlinea_' . $num];

            #INI MOD ERICK CON PLUGIN TARIFAS-VARIANTES-AVANZADAS
            $flagVariante = false;
            #echo $changePrice . ' | ' . $formData['precio_' . $num]; exit;
            if ($changePrice && is_null($cliente->codtarifa)) { #MOD ERICK
                $variante = new Variante();
                $whereVariante = [
                    new DataBaseWhere('referencia', $formData['referencia_' . $num]),
                ];
                if ($variante->loadFromCode('', $whereVariante)) {
                    if ($variante->pricecount) {
                        if (!is_null($variante->pricecount)) {
                            $list = explode(';', $variante->pricecount);
                            foreach ($list as $item) {
                                $it = explode(':', $item);
                                if ($it != null) {
                                    if ($it[0] == $newLine->cantidad) {
                                        $newLine->pvpunitario = round((100 * floatval($it[1])) / (100 + floatval($newLine->iva)), 5);
                                        $flagVariante = true;
                                        $formData['precio_' . $num] = floatval($it[1]);
                                    }
                                }
                            }
                        }
                        if (!$flagVariante) {
                            $dataBase = new DataBase();
                            $sql = 'SELECT p.tpvsort, p.referencia, p.descripcion, v.precio, i.iva, p.nostock, p.observaciones, 
										v.idvariante, v.idproducto'
                                . ' FROM variantes as v'
                                . ' LEFT JOIN productos as p ON v.idproducto = p.idproducto'
                                . ' LEFT JOIN impuestos as i ON p.codimpuesto = i.codimpuesto'
                                . ' WHERE v.referencia = ' . $dataBase->var2str($formData['referencia_' . $num])
                                . ' LIMIT 1';

                            $result = $dataBase->select($sql);
                            $price = floatval($result[0]['precio']) * (100 + floatval($result[0]['iva'])) / 100;

                            $formData['precio_' . $num] = $price;
                        }
                    }
                }
            }
            #FIN MOD ERICK CON PLUGIN TARIFAS-VARIANTES-AVANZADAS

            if ($tpv->changeprice || empty($formData['referencia_' . $num])) {
                $newLine->pvpunitario = round((100 * floatval($formData['precio_' . $num])) / (100 + floatval($newLine->iva)), 5);
            }

            if ($tpv->adddiscount) {
                $newLine->dtopor = (float)$formData['dtopor_' . $num];
            }

            self::$lines[] = $newLine;
        }

        // new line
        switch ($formData['action']) {
            case 'add-product':
                self::$lines[] = self::$doc->getNewProductLine($formData['action-code']);
                break;

            case 'add-barcode':
                $variant = new Variante();
                $where = [new DataBaseWhere('codbarras', $formData['action-code'])];
                if ($variant->loadFromCode('', $where)) {
                    self::$lines[] = self::$doc->getNewProductLine($formData['action-code']); #MOD ERICK
                }
                break;

            case 'new-line':
                $line = self::$doc->getNewLine();
                $line->descripcion = $formData['new-desc'];
                self::$lines[] = $line;
                break;
        }

        Calculator::calculate(self::$doc, self::$lines, false);
    }

    public static function clearCart(TpvTerminal $tpv)
    {
        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $tpv->doctype;
        self::$doc = new $modelClass();
        self::$doc->total = 0;
        self::$lines = [];
    }

    public static function getLastDocSave()
    {
        return self::$lastDocSave;
    }

    public static function loadDocPrint($idDoc, TpvTerminal $tpv): string
    {
        $html = '';
        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $tpv->doctype;
        $doc = new $modelClass();

        if (!$doc->loadFromCode($idDoc)) {
            return $html;
        }

        $isGeneralInvoice = strtoupper((string)$doc->codserie) === 'A';

        $cliente = new Cliente();
        $cliente->loadFromCode($doc->codcliente);

        $html .= '<div class="text-center h4">' . $doc->codigo . '</div>'
            . '<hr/>'
            . '<div class="input-group input-group-lg pt-3 pb-3">'
            . '<input id="emailInput" class="form-control text-center" aria-describedby="ticket-send" placeholder="'
            . ToolBox::i18n()->trans('email') . '" value="' . $cliente->email . '" />'
            . '<div class="input-group-append">'
            . '<button class="btn btn-secondary btn-spin-action" type="button" id="doc-send" onclick="sendDoc('
            . $doc->primaryColumnValue() . ')">' . ToolBox::i18n()->trans('send-' . strtolower($doc->modelClassName())) . '</button>'
            . '</div>'
            . '</div>';

        if ($tpv->idprinter && false === $isGeneralInvoice) {
            $options = '';
            foreach (SaleTicket::loadFormats() as $fileName) {
                $options .= '<option value="' . $fileName . '"' . ($tpv->ticketformat == $fileName ? ' selected' : '') . '>'
                    . ToolBox::i18n()->trans(strtolower($fileName)) . '</option>';
            }

            $html .= '<hr/>'
                . '<div class="input-group input-group-lg pt-3 pb-3">'
                . '<select id="ticketformat" class="form-control" aria-describedby="ticket-print">'
                . $options
                . '</select>'
                . '<div class="input-group-append">'
                . '<button class="btn btn-primary btn-spin-action" type="button" id="ticket-print" onclick="printTicket('
                . $doc->primaryColumnValue() . ')">' . ToolBox::i18n()->trans('print-ticket') . '</button>'
                . '</div>'
                . '</div>';
        }

        $printLabel = $isGeneralInvoice ? ToolBox::i18n()->trans('print') : ToolBox::i18n()->trans('print-' . strtolower($doc->modelClassName()));

        $html .= '<a class="btn btn-primary btn-block btn-lg btn-spin-action" target="_blank" href="' . FS_ROUTE . '/Edit'
            . $doc->modelClassName() . '?code=' . $idDoc . '&action=export&option=PDF' . '">'
            . $printLabel
            . '</a>';

        return $html;
    }

    #INI MOD ERICK
    public static function loadDocPreviewPrint($idDoc, TpvTerminal $tpv): string
    {
        $html = '';
        $flagFooter = true;
        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $tpv->doctype;
        $doc = new $modelClass();

        if (!$doc->loadFromCode($idDoc)) {
            return $html;
        }
        $cliente = new Cliente();
        $cliente->loadFromCode($doc->codcliente);

        $empresa = new Empresa();
        $empresa->loadFromCode($doc->idempresa);

        $formato = new FormatoDocumento();
        $whereFormato  = [
            new DataBaseWhere('codserie', 'S'),
            new DataBaseWhere('idempresa', $doc->idempresa)
        ];
        if (false == $formato->loadFromCode('', $whereFormato)) {
            $flagFooter = false;
        }

        $formaPago = new FormaPago();
        $whereFormaP  = [
            new DataBaseWhere('codpago', $doc->codpago),
            new DataBaseWhere('idempresa', $doc->idempresa)
        ];

        $formaPago->loadFromCode('', $whereFormaP);

        #die(var_dump($doc->getLines()));
        #$detalles = new LineaFactura();
        #$detalles->loadFromCode('', [ new DataBaseWhere('idfactura', $doc->idfactura) ] );

        $html .= '<div id="wrapper">'
            . '<div id="receipt-data">'
            . '<div class="text-center">';

        if (Plugins::isEnabled('Verifactu')) {
            $qrDoc = new \FacturaScripts\Dinamic\Model\FacturaCliente();
            if ($qrDoc->modelClassName() === 'FacturaCliente' && $qrDoc->verifactuCheckAlta()) {
                $qrDoc->loadFromCode($idDoc); // carga la factura

                // Obtenemos el closure
                $closure = $qrDoc->verifactuGetQr();

                // Ejecutamos el closure si es un Closure
                if ($closure instanceof \Closure) {
                    $qrContent = $closure();
                } else {
                    $qrContent = $closure; // en caso sea un string directo
                }

                // Convertimos a Data URI si es binario
                if (!empty($qrContent) && strpos($qrContent, 'data:image') !== 0) {
                    $qrContent = 'data:image/png;base64,' . base64_encode((string)$qrContent);
                }

                // Mostramos en HTML
                if (!empty($qrContent)) {
                    $html .= '<p style="text-align:center; margin: 0; padding-bottom: 0px;">QR tributario:</p>';
                    $html .= '<img style="width:40mm;height:40mm;"  src="' . $qrContent . '" alt="QR" />';
                    $html .= '<p style="text-align:center;">Factura verificable en la sede electrónica de la AEAT</p>';
                }
            }
        }

        $html .= '<h4 style="text-transform:uppercase;">' . $empresa->administrador . '</h4>'
            . '<p>' . $empresa->nombrecorto . '<br />'
            . $empresa->direccion . '<br />'
            . 'Tel: ' . $empresa->telefono1 . '<br />'
            . 'NIF/CIF: ' . $empresa->cifnif . '<br />'
            . '</p>'
            . '</div>'
            . '<div class="text-center">'
            . '<h6 style="font-weight:bold;">Factura Simplificada</h6>'
            . '</div>'
            . '<div class="sec-doc" style="text-align:left !important;padding-right: 15px;padding-left: 15px;">'
            . '<p>Fecha: ' . $doc->fecha . ' ' . date('H:i:s', strtotime($doc->hora)) . '<br/>'
            . 'Documento: ' . $doc->codigo . '<br/>'
            #.'NIF: '. $doc->cifnif .'<br/>'
            . '</p>'
            . '</div>'
            . '<table style="margin-right: 15px;margin-left: 15px;width: 93.5%;">'
            . '<thead style="background-color: #3498db;color: #fff">'
            . '<tr>'
            . '<th style="padding-top: 3px;padding-bottom: 3px;">Descripción</th>'
            . '<th style="padding-top: 3px;padding-bottom: 3px;">IMP</th>'
            #.'<th style="padding-top: 3px;padding-bottom: 3px;padding-right: 8px;">Neto</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>';
        $ivaGroups = [];
        foreach ($doc->getLines() as $item) {
            $html .= '<tr>'
                . '<td style="text-align:left;"><p>' . $item->descripcion . '<br/> '
                . ($item->cantidad > 1 ? number_format($item->cantidad, 2, '.', ',') . ' x ' . number_format($item->pvpunitario + ($item->pvpunitario * $item->iva / 100), 2, '.', ',') : '')
                #.' <small>(IVA: '.$item->iva.'%)</small>'
                . '</p></td>'
                #.'<td>'.number_format($item->pvpunitario + ($item->pvpunitario * $item->iva/100),2,'.',',').'</td>'
                . '<td>' . number_format($item->pvptotal + ($item->pvptotal * $item->iva / 100), 2, '.', ',') . '</td>'
                . '</tr>';
            if (!isset($ivaGroups[$item->iva])) {
                $ivaGroups[$item->iva] = 0;
            }
            $ivaGroups[$item->iva] += round($item->pvptotal * $item->iva / 100, 2);
        }
        $ivaRows = '';
        foreach ($ivaGroups as $rate => $amount) {
            $ivaRows .= '<tr><td style="text-align:right;font-weight:bold;">IVA (' . $rate . '%):  </td>'
                . '<td class="text-right">' . number_format($amount, 2, '.', ',') . '</td></tr>';
        }
        $html .= '<tr style="height:25px;"></tr><tr>'
            . '<td style="text-align:right;font-weight:bold;">TOTAL: </td>'
            . '<td class="text-right">' . number_format($doc->total, 2, '.', ',') . '</td></tr>'
            . '<tr><td style="text-align:right;font-weight:bold;">BASE: </td>'
            . '<td class="text-right">' . number_format($doc->neto, 2, '.', ',') . '</td></tr>'
            . $ivaRows
            . '<tr><td style="text-align:right;font-weight:bold;text-transform:uppercase;">' . $formaPago->descripcion . ':  </td>'
            . '<td class="text-right">' . number_format($doc->tpv_efectivo, 2, '.', ',') . '</td></tr>'
            . '<tr><td style="text-align:right;font-weight:bold;">CAMBIO:  </td>'
            . '<td class="text-right">' . number_format($doc->tpv_cambio, 2, '.', ',') . '</td></tr>'
            . '</tbody>'
            . '</table>'
            . '</div>';

        if ($flagFooter) {
            $html .= '<p class="sec-doc" style="text-align:center; margin-top:25px;margin-left:20px; margin-right:20px;"><span style="font-weight:bold;">Gracias por su compra.</span><br>' . $formato->texto . '</p></div>';
        } else {
            $html .= '<p class="sec-doc" style="text-align:center; margin-top:25px;margin-left:20px; margin-right:20px;"><span style="font-weight:bold;">Gracias por su compra.</span></p>'
                . '</div>';
        }

        #.'<div class="col-12">'
        #.'<button type="button" onclick="imprimirTicket();" class="btn btn-block btn-primary">'.ToolBox::i18n()->trans('print').'</button>'
        #.'</div>'
        #.'</div>';


        return $html;
    }

    public static function loadDocPreviewPrintPDF($idDoc, TpvTerminal $tpv): string
    {
        $html = '';
        $flagFooter = true;
        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $tpv->doctype;
        $doc = new $modelClass();

        if (!$doc->loadFromCode($idDoc)) {
            return $html;
        }
        $cliente = new Cliente();
        $cliente->loadFromCode($doc->codcliente);

        $empresa = new Empresa();
        $empresa->loadFromCode($doc->idempresa);

        $formato = new FormatoDocumento();
        $whereFormato  = [
            new DataBaseWhere('codserie', 'S'),
            new DataBaseWhere('idempresa', $doc->idempresa)
        ];
        if (false == $formato->loadFromCode('', $whereFormato)) {
            $flagFooter = false;
        }

        $formaPago = new FormaPago();
        $whereFormaP  = [
            new DataBaseWhere('codpago', $doc->codpago),
            new DataBaseWhere('idempresa', $doc->idempresa)
        ];

        $formaPago->loadFromCode('', $whereFormaP);


        #die(var_dump($doc->getLines()));
        #$detalles = new LineaFactura();
        #$detalles->loadFromCode('', [ new DataBaseWhere('idfactura', $doc->idfactura) ] );

        $html .= '<div id="receipt-data">'
            . '<div style="text-align:center;">';

        if (Plugins::isEnabled('Verifactu')) {
            $qrDoc = new \FacturaScripts\Dinamic\Model\FacturaCliente();
            if ($qrDoc->modelClassName() === 'FacturaCliente' && $qrDoc->verifactuCheckAlta()) {
                $qrDoc->loadFromCode($idDoc); // carga la factura

                // Obtenemos el closure
                $closure = $qrDoc->verifactuGetQr();

                // Ejecutamos el closure si es un Closure
                if ($closure instanceof \Closure) {
                    $qrContent = $closure();
                } else {
                    $qrContent = $closure; // en caso sea un string directo
                }

                // Convertimos a Data URI si es binario
                if (!empty($qrContent) && strpos($qrContent, 'data:image') !== 0) {
                    $qrContent = 'data:image/png;base64,' . base64_encode((string)$qrContent);
                }

                // Mostramos en HTML
                if (!empty($qrContent)) {
                    $html .= '<p style="text-align:center; margin: 0; padding-bottom: 0px;">QR tributario:</p>';
                    $html .= '<img style="width:40mm;height:40mm;"  src="' . $qrContent . '" alt="QR" />';
                    $html .= '<p style="text-align:center;">Factura verificable en la sede electrónica de la AEAT</p>';
                }
            }
        }

        $html .= '<h3 style="text-transform:uppercase;">' . $empresa->administrador . '</h3>'
            . '<p>' . $empresa->nombrecorto . '<br />'
            . $empresa->direccion . '<br />'
            . 'Tel: ' . $empresa->telefono1 . '<br />'
            . 'NIF/CIF: ' . $empresa->cifnif . '<br />'
            . '</p>'
            . '</div>'
            . '<div style="text-align:center;">'
            . '<h4 style="font-weight:bold;">Factura Simplificada</h4>'
            . '</div>'
            . '<div class="sec-doc" style="text-align:left !important;padding-right: 5px;padding-left: 10px;">'
            . '<p>Fecha: ' . date('d-m-Y', strtotime($doc->fecha)) . ' ' . date('H:i:s', strtotime($doc->hora)) . '<br/>'
            . 'Documento: ' . $doc->codigo . '<br/>'
            #.'NIF: '. $doc->cifnif .'<br/>'
            . '</p>'
            . '</div>'
            . '<table class="sec-doc" style="margin-right: 15px;margin-left: 5px;width: 93.5%;">'
            . '<thead class="sec-doc" style="background-color: #f2f2f2;color: #000">'
            . '<tr>'
            . '<th style="padding-top: 3px;padding-bottom: 3px; text-align:left;">Descripción</th>'
            . '<th style="padding-top: 3px;padding-bottom: 3px; text-align:right;">IMP</th>'
            #.'<th style="padding-top: 3px;padding-bottom: 3px; text-align:right;">Neto</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody  class="sec-doc">';
        $ivaGroupsPDF = [];
        foreach ($doc->getLines() as $item) {
            $html .= '<tr>'
                . '<td style="text-align:left;"><p>' . substr($item->descripcion, 0, 35) . ' '
                . ($item->cantidad > 1 ? '(' . number_format($item->cantidad, 2, '.', ',') . ' x ' . number_format($item->pvpunitario + ($item->pvpunitario * $item->iva / 100), 2, '.', ',') . ')' : '')
                #.' <small>(IVA: '.$item->iva.'%)</small>'
                . '</p></td>'
                #.'<td>'.number_format($item->pvpunitario + ($item->pvpunitario * $item->iva/100),2,'.',',').'</td>'
                . '<td style="text-align: right;">' . number_format($item->pvptotal + ($item->pvptotal * $item->iva / 100), 2, '.', ',') . '</td>'
                . '</tr>';
            if (!isset($ivaGroupsPDF[$item->iva])) {
                $ivaGroupsPDF[$item->iva] = 0;
            }
            $ivaGroupsPDF[$item->iva] += round($item->pvptotal * $item->iva / 100, 2);
            # €
        }
        $ivaRowsPDF = '';
        foreach ($ivaGroupsPDF as $rate => $amount) {
            $ivaRowsPDF .= '<tr>'
                . '<td style="text-align:left;margin-left:5px;font-weight:bold;">IVA (' . $rate . '%) :    </td>'
                . '<td style="text-align:right;">' . number_format($amount, 2, '.', ',') . '</td>'
                . '</tr>';
        }
        $html .= '<tr style="height:35px;"></tr>'
            . '<tr>'
            . '<td style="text-align:left;margin-left:5px;font-weight:bold; font-size: 17px;">TOTAL :   </td>'
            . '<td style="text-align:right;font-size: 17px;">' . number_format($doc->total, 2, '.', ',') . '</td></tr>'
            . '<tr style="height:15px;"></tr>'
            . '<tr>'
            . '<td style="text-align:left;margin-left:5px;font-weight:bold;">BASE : </td>'
            . '<td style="text-align:right;">' . number_format($doc->neto, 2, '.', ',') . '</td></tr>'
            . $ivaRowsPDF
            . '<tr>'
            . '<td style="text-align:left;margin-left:5px;font-weight:bold;text-transform:uppercase;">' . $formaPago->descripcion . ' :    </td>'
            . '<td style="text-align:right;">' . number_format($doc->tpv_efectivo, 2, '.', ',') . '</td>'
            . '</tr>'
            . '<tr>'
            . '<td style="text-align:left;margin-left:5px;font-weight:bold;text-transform:uppercase;">CAMBIO :    </td>'
            . '<td style="text-align:right;">' . number_format($doc->tpv_cambio, 2, '.', ',') . '</td>'
            . '</tr>'
            . '</tbody>'
            . '</table>';
        if ($flagFooter) {
            $html .= '<p class="sec-doc" style="text-align:center; margin-top:25px;margin-left:20px; margin-right:20px;"><span style="font-weight:bold;">Gracias por su compra.</span><br>' . $formato->texto . '</p></div>';
        } else {
            $html .= '<p class="sec-doc" style="text-align:center; margin-top:25px;margin-left:20px; margin-right:20px;"><span style="font-weight:bold;">Gracias por su compra.</span></p>'
                . '</div>';
        }
        return $html;
    }

    #FIN MOD ERICK

    public static function map(): array
    {
        $num = 0;
        $map = [];
        foreach (self::$lines as $line) {
            $num++;

            // total
            $map['linetotal_' . $num] = ToolBox::coins()::format($line->pvptotal * (100 + $line->iva + $line->recargo - $line->irpf) / 100);
        }

        return $map;
    }

    public static function recalculate()
    {
        Calculator::calculate(self::$doc, self::$lines, false);
    }

    public static function render(TpvTerminal $tpv): string
    {
        $i18n = ToolBox::i18n();
        return '<form method="post" name="saleForm">'
            . '<input type="hidden" name="action">'
            . '<input type="hidden" name="action-code">'
            . '<input type="hidden" name="codcliente">'
            . '<input type="hidden" name="codpark">'
            . '<input type="hidden" name="new-desc">'
            . '<div id="linesCart" class="bg-white">'
            . self::renderLines($i18n, $tpv)
            . '</div>'
            . '</form>';
    }

    public static function renderModalTickets(TpvCaja $caja, string $codigo = ''): string
    {
        $html = '';
        $tpv = $caja->getTerminal();
        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $tpv->doctype;
        $docModel = new $modelClass();
        $where = [];
        if (empty($codigo)) {
            $where[] = new DataBaseWhere('idcaja', $caja->idcaja);
        } else {
            $where[] = new DataBaseWhere('codigo', $codigo, 'LIKE');
            $where[] = new DataBaseWhere('idtpv', $tpv->idtpv);
        }

        foreach ($docModel->all($where, ['fecha' => 'DESC', 'hora' => 'DESC']) as $doc) {
            self::changeDivisa($doc->coddivisa);

            $cssTr = $doc->total > 0 ? 'table-success' : 'table-danger';
            $isGeneralInvoice = strtoupper((string)$doc->codserie) === 'A';

            $html .= '<tr class="' . $cssTr . '">'
                . '<td class="align-middle">' . Agentes::get($doc->codagente)->nombre . '</td>'
                . '<td class="align-middle text-nowrap">' . $doc->codigo . '</td>'
                . '<td class="align-middle text-nowrap">' . $doc->numero2 . '</td>'
                . '<td class="align-middle">' . $doc->nombrecliente . '</td>'
                . '<td class="align-middle">' . FormasPago::get($doc->codpago)->descripcion . '</td>'
                . '<td class="align-middle">' . $doc->observaciones . '</td>'
                . '<td class="align-middle text-right text-nowrap">' . ToolBox::coins()::format($doc->total) . '</td>'
                . '<td class="align-middle text-right">' . $doc->fecha . ' ' . $doc->hora . '</td>'
                . '<td class="align-middle text-center">'
                . '<div class="btn-group" role="group">';

            if ($isGeneralInvoice) {
                $html .= '<a target="_blank" href="' . FS_ROUTE . '/Edit' . $doc->modelClassName() . '?code='
                    . $doc->primaryColumnValue() . '&action=export&option=PDF" title="' . ToolBox::i18n()->trans('print')
                    . '" class="btnPrintTicket btn btn-primary btn-spin-action"><i class="fas fa-print fa-fw"></i></a>';
            } else {
                $html .= '<button type="button" onclick="return btnPrintTicket(\'' . $doc->primaryColumnValue() . '\')" title="' . ToolBox::i18n()->trans('print')
                    . '" data-escpos-tipo="' . ToolBox::appSettings()::get('tpvneo', 'escpos_tipo', '') . '"'
                    . ' data-escpos-relay="' . ToolBox::appSettings()::get('tpvneo', 'escpos_relay_url', '') . '"'
                    . ' class="btnPrintTicket btn btn-primary btn-spin-action"><i class="fas fa-print fa-fw"></i></button>';
            }

            if (false === $isGeneralInvoice) {
                $html .= '<button onclick="return btnPreviewTicket(\'' . $doc->primaryColumnValue() . '\')" title="' . ToolBox::i18n()->trans('preview')
                    . '" data-escpos-tipo="' . ToolBox::appSettings()::get('tpvneo', 'escpos_tipo', '') . '"'
                    . ' class="btnPreviewTicket btn btn-danger btn-block btn-spin-action"><i class="fas fa-eye fa-fw"></i></button>';
            }

            if ($doc->total > 0 && false === $isGeneralInvoice) {
                $html .= '<button type="button" onclick="return modalReturn(\'' . $doc->primaryColumnValue() . '\', this)" title="' . ToolBox::i18n()->trans('returns')
                    . '" class="btnReturnTicket btn btn-warning btn-spin-action"><i class="fas fa-exchange-alt fa-fw"></i></button>';
            }

            $html .= '</div>'
                . '</td>'
                . '</tr>';
        }

        if (empty($html)) {
            $html .= '<tr class="table-warning">'
                . '<td colspan="7">' . ToolBox::i18n()->trans('no-data') . '</td>'
                . '</tr>';
        }

        return $html;
    }

    public static function saveDoc(array $formData, User $user, TpvCaja $caja, ?string $codagente): bool
    {
        $dataBase = new DataBase();
        $dataBase->beginTransaction();

        $tpv = $caja->getTerminal();
        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $tpv->doctype;
        $doc = new $modelClass();

        $cliente = new Cliente();
        $codcliente = empty($formData['codcliente']) ? $tpv->codcliente : $formData['codcliente'];
        $cliente->loadFromCode($codcliente);
        $doc->setSubject($cliente);
        $user->codagente = $codagente;
        $doc->setAuthor($user);

        // establecemos la forma de pago
        if ($formData['action'] == 'save-cart' && $formData['formasPagos'] >= 1) {
            foreach (FormasPago::all() as $pago) {
                if (!isset($formData[$pago->codpago])) {
                    continue;
                }

                $doc->codpago = $pago->codpago;
                break;
            }
        } else {
            $doc->codpago = $tpv->codpago;
        }
        if (!empty($formData['codpago'])) {
            $doc->codpago = $formData['codpago'];
        }

        $doc->idtpv = $tpv->idtpv;
        $doc->idcaja = $caja->idcaja;
        $doc->codserie = ($tpv->doctype === 'FacturaCliente' && !empty($codcliente) && $codcliente !== $tpv->codcliente)
            ? 'A'
            : $tpv->codserie;
        $doc->coddivisa = $tpv->coddivisa;
        $doc->observaciones = $formData['observations'] ?? '';
        if ($doc->save() === false) {
            $dataBase->rollback();
            return false;
        }

        // añadimos las líneas
        $linesCart = $formData['linesCart'] ?? 100;
        for ($num = 1; $num <= $linesCart; $num++) {
            if (!isset($formData['descripcion_' . $num])) {
                continue;
            }

            $newLine = empty($formData['referencia_' . $num]) ?
                $doc->getNewLine() :
                $doc->getNewProductLine($formData['referencia_' . $num]);

            $newLine->cantidad = (float)$formData['cantidad_' . $num];
            $newLine->descripcion = $formData['descripcion_' . $num];

            if ($tpv->adddiscount) {
                $newLine->dtopor = (float)$formData['dtopor_' . $num];
            }

            if ($tpv->changeprice || empty($formData['referencia_' . $num])) {
                $newLine->pvpunitario = round((100 * floatval($formData['precio_' . $num])) / (100 + floatval($newLine->iva)), 5);
            }

            if ($newLine->save() === false) {
                $dataBase->rollback();
                return false;
            }
        }

        // actualizamos los totales
        $lines = $doc->getLines();
        if (Calculator::calculate($doc, $lines, true) === false) {
            $dataBase->rollback();
            return false;
        }

        // guardamos el dinero recibido en metálico y el cambio
        if (isset($formData[$tpv->codpago]) && $formData[$tpv->codpago] > 0) {
            $doc->tpv_efectivo = $formData[$tpv->codpago];
            $doc->tpv_cambio = max($doc->tpv_efectivo - $doc->total, 0);
            $doc->save();

            // si se ha pagado en efectivo, marcamos el recibo como pagado
            if ($tpv->doctype === 'FacturaCliente' && $formData['formasPagos'] == 1) {
                foreach ($doc->getReceipts() as $receipt) {
                    $receipt->pagado = true;
                    $receipt->save();
                    break;
                }
            }
        }

        // si hay varias formas de pago, guardamos los recibos
        if ($formData['action'] == 'save-cart' && $formData['formasPagos'] > 1) {
            $tpvPago = false;
            $totalRecibos = 0;

            if ($tpv->doctype === 'FacturaCliente') {
                // eliminamos los recibos anteriores
                foreach ($doc->getReceipts() as $recibo) {
                    $recibo->delete();
                }

                foreach (FormasPago::all() as $pago) {
                    if (!isset($formData[$pago->codpago])) {
                        continue;
                    }

                    if ($pago->codpago === $tpv->codpago) {
                        $tpvPago = true;
                        continue;
                    }

                    $totalRecibos += $formData[$pago->codpago];
                    $newRecibo = new ReciboCliente();
                    $newRecibo->codigofactura = $doc->codigo;
                    $newRecibo->idfactura = $doc->primaryColumnValue();
                    $newRecibo->codcliente = $doc->codcliente;
                    $newRecibo->nick = $doc->nick;
                    $newRecibo->codpago = $pago->codpago;
                    $newRecibo->importe = $formData[$pago->codpago];
                    $newRecibo->save();
                }

                if ($tpvPago) {
                    $lastRecibo = new ReciboCliente();
                    $lastRecibo->codigofactura = $doc->codigo;
                    $lastRecibo->idfactura = $doc->primaryColumnValue();
                    $lastRecibo->codcliente = $doc->codcliente;
                    $lastRecibo->nick = $doc->nick;
                    $lastRecibo->codpago = $tpv->codpago;
                    $lastRecibo->importe = $doc->total - $totalRecibos;
                    $lastRecibo->pagado = true;
                    $lastRecibo->save();
                }
            } elseif (class_exists("\\FacturaScripts\\Dinamic\\Model\\PrePago")) {
                foreach (FormasPago::all() as $pago) {
                    if (!isset($formData[$pago->codpago])) {
                        continue;
                    }

                    if ($pago->codpago === $tpv->codpago) {
                        $tpvPago = true;
                        continue;
                    }

                    $totalRecibos += $formData[$pago->codpago];
                    $newRecibo = new PrePago();
                    $newRecibo->amount = $formData[$pago->codpago];
                    $newRecibo->codcliente = $doc->codcliente;
                    $newRecibo->codpago = $pago->codpago;
                    $newRecibo->modelname = $doc->modelClassName();
                    $newRecibo->modelid = $doc->primaryColumnValue();
                    $newRecibo->save();
                }

                if ($tpvPago) {
                    $lastRecibo = new PrePago();
                    $lastRecibo->amount = $doc->total - $totalRecibos;
                    $lastRecibo->codcliente = $doc->codcliente;
                    $lastRecibo->codpago = $tpv->codpago;
                    $lastRecibo->modelname = $doc->modelClassName();
                    $lastRecibo->modelid = $doc->primaryColumnValue();
                    $lastRecibo->save();
                }
            }
        }

        // recargamos el documento
        $doc->loadFromCode($doc->primaryColumnValue());

        // ponemos la factura en emitida
        if ($formData['action'] == 'save-cart' && $tpv->doctype === 'FacturaCliente' && $doc->getStatus()->editable) {
            // cambiamos el estado de la factura si su estado actual es editable
            foreach ($doc->getAvailableStatus() as $stat) {
                if (false === $stat->editable) {
                    $doc->idestado = $stat->idestado;
                    $doc->save();
                    break;
                }
            }
        }

        // buscamos el presupuesto relacionado y lo eliminamos
        if ($formData['action'] == 'save-cart' && isset($formData['codpark']) && $formData['codpark'] != '') {
            $pr = new PresupuestoCliente();
            $pr->loadFromCode($formData['codpark']);
            $pr->delete();
        }

        // actualizamos la caja
        if ($formData['action'] == 'save-cart') {
            $caja->ingresos += $doc->tpv_efectivo - $doc->tpv_cambio;
            $caja->numtickets++;
            $caja->save();
        }

        $dataBase->commit();
        $doc->code = $doc->PrimaryColumnValue();
        self::$lastDocSave = $doc;
        self::clearCart($tpv);
        return true;
    }

    public static function setDoc($doc)
    {
        self::$doc = $doc;
    }

    public static function setLines($lines)
    {
        self::$lines = $lines;
    }

    public static function totalCart(): string
    {
        return self::$doc->total ?? 0;
    }

    protected static function changeDivisa(string $coddivisa)
    {
        $divisa = new Divisa();
        $divisa->loadFromCode($coddivisa);

        $divisaTools = new DivisaTools();
        $divisaTools->findDivisa($divisa);
    }

    protected static function renderLines(Translator $i18n, TpvTerminal $tpv): string
    {
        $html = '';
        $num = 1;
        foreach (self::$lines as $line) {
            $price = floatval($line->pvpunitario);

            if ($line->dtopor > 0) {
                $price = $price - ($price * ($line->dtopor / 100));
            }

            $pvpunitario = floatval($line->pvpunitario) * (100 + floatval($line->iva)) / 100;
            $total = (floatval($line->cantidad) * $price) * (100 + floatval($line->iva)) / 100;
            $descripcion = strlen($line->descripcion) > 40 ? substr($line->descripcion, 0, 40) . '…' : $line->descripcion;

            // hidden inputs
            $html .= '<div class="px-3 py-2 border-bottom line line' . $num . '" referencia="' . $line->referencia . '">'
                . '<input type="hidden" class="idlinea" name="idlinea_' . $num . '" value="' . $line->idlinea . '">'
                . '<input type="hidden" class="referencia" name="referencia_' . $num . '" value="' . $line->referencia . '">'
                . '<input type="hidden" class="descripcion" name="descripcion_' . $num . '" value="' . $line->descripcion . '">'
                // single row: qty | price | discount | total | name | trash
                . '<div class="d-flex align-items-center gap-2" style="gap:8px;">'
                // --- qty buttons ---
                . '<div class="d-flex align-items-center flex-shrink-0" style="gap:4px;">';

            if ($line->cantidad > 1) {
                $newCantidad = $line->cantidad - 1;
                $html .= '<button type="button" class="minusQty btn btn-outline-danger btn-spin-action" style="padding:6px; display:flex;" '
                    . 'onclick="return lineQuantity(\'' . $num . '\',\'' . $newCantidad . '\')">'
                    . '<i class="fas fa-minus"></i></button>';
            } else {
                $html .= '<button type="button" class="minusQty btn btn-outline-danger btn-spin-action" style="padding:6px; display:flex;" disabled>'
                    . '<i class="fas fa-minus"></i></button>';
            }

            $newCantidad = $line->cantidad + 1;
            $html .= '<input type="hidden" name="cantidad_' . $num . '" value="' . $line->cantidad . '">'
                . '<span class="text-center font-weight-bold" style="min-width:32px;cursor:pointer;font-size:1rem;" onclick="return changeQtyPrompt(\'' . $num . '\')">'
                . $line->cantidad . '</span>'
                . '<button type="button" class="plusQty btn btn-outline-success btn-spin-action" style="padding:6px; display:flex;" '
                . 'onclick="return lineQuantity(\'' . $num . '\',\'' . $newCantidad . '\')">'
                . '<i class="fas fa-plus"></i></button>'
                . '</div>';

            // --- price ---
            if ($tpv->changeprice || empty($line->referencia)) {
                $html .= '<div class="input-group flex-shrink-0" style="max-width:105px;">'
                    . '<input step="0.1" name="precio_' . $num . '" type="number" class="form-control text-right precio" min="0" value="'
                    . round($pvpunitario, FS_NF0) . '" />'
                    . '<div class="input-group-append"><span class="input-group-text">€</span></div>'
                    . '</div>';
            } else {
                $html .= '<span class="flex-shrink-0 text-right" style="min-width:60px;">'
                    . ToolBox::coins()::format($pvpunitario) . '</span>';
            }

            // --- discount ---
            if ($tpv->adddiscount) {
                $html .= '<div class="input-group flex-shrink-0" style="max-width:80px;">'
                    . '<input step="1" name="dtopor_' . $num . '" type="number" class="form-control text-right descuento" min="0" max="100" value="'
                    . $line->dtopor . '" />'
                    . '<div class="input-group-append"><span class="input-group-text">%</span></div>'
                    . '</div>';
            }

            // --- total ---
            $html .= '<span id="linetotal_' . $num . '" class="flex-shrink-0 text-right font-weight-bold text-nowrap" style="margin-left:8px;" style="font-size:0.85rem;">'
                . ToolBox::coins()::format($total)
                . '</span>';

            // --- ref + short name ---
            $html .= '<div class="flex-grow-1 text-truncate" style="font-size:0.85rem;color:#868ba1;">';

            $html .= '<span class="text-muted mr-1" title="' . $descripcion . '">' . $descripcion . '</span>';
            if (false === empty($line->referencia)) {
                $html .= '<span class="font-weight-bold" style="color:#868ba1;" title="' . $line->referencia . '">' . $line->referencia . '</span>';
            }
            $html .= '</div>';

            // --- trash ---
            $html .= '<button type="button" class="deleteProduct btn btn-outline-danger btn-spin-action flex-shrink-0" style="padding:4px 8px;" onclick="return rmLine(\'' . $num . '\')">'
                . '<i class="fas fa-trash-alt"></i></button>'
                . '</div>'
                . '</div>';

            $num++;
        }

        return $html;
    }
}
