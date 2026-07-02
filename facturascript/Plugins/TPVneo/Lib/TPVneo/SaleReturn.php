<?php
/**
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TPVneo\Lib\TPVneo;

use FacturaScripts\Core\App\AppSettings;
use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\DivisaTools;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Divisa;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\TpvCaja;
use FacturaScripts\Dinamic\Model\TpvTerminal;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Dinamic\Model\Variante;

/**
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class SaleReturn
{

    use TPVTrait;

    public static $lastDocSave;

    public static function getMethodReturn($idDoc, $tpv): string
    {
        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $tpv->doctype;
        $doc = new $modelClass();

        if (!$doc->loadFromCode($idDoc)) {
            return '';
        }

        $pago = new FormaPago();
        $pago->loadFromCode($doc->codpago);
        return $pago->descripcion;
    }

    public static function loadDocReturn($idDoc, TpvTerminal $tpv): string
    {
        $html = '';
        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $tpv->doctype;
        $doc = new $modelClass();

        if (!$doc->loadFromCode($idDoc)) {
            return $html;
        }

        self::changeDivisa($doc->coddivisa);
        $html .= '<div class="text-center h4 mt-3">' . $doc->codigo . '</div>'
            . '<div class="table-responsive">'
            . '<table class="table table-striped mb-0">'
            . '<thead>'
            . '<th>' . ToolBox::i18n()->trans('image') . '</th>'
            . '<th>' . ToolBox::i18n()->trans('product') . '</th>'
            . '<th class="text-center">' . ToolBox::i18n()->trans('quantity') . '</th>'
            . '<th class="text-right">' . ToolBox::i18n()->trans('price') . '</th>'
            . '<th class="text-right">' . ToolBox::i18n()->trans('total') . '</th>'
            . '<th class="text-right">' . ToolBox::i18n()->trans('qty-return') . '</th>'
            . '</thead>'
            . '<tbody>';

        foreach ($doc->getLines() as $line) {
            $refunded = 0;
            if ($tpv->doctype === 'FacturaCliente') {
                $refunded = $line->refundedQuantity();
            }
            $refundedQty = $line->cantidad - $refunded;
            $price = floatval($line->pvpunitario) * (100 + floatval($line->iva)) / 100;
            $total = floatval($line->pvptotal) * (100 + floatval($line->iva)) / 100;
            $descripcion = strlen($line->descripcion) > 97 ? substr($line->descripcion, 0, 120) . '...' : $line->descripcion;
            $disabled = $refundedQty <= 0 ? 'text-muted' : '';
            $html .= '<tr class="' . $disabled . '" pvpunitario="' . $price . '" referencia="' . $line->referencia . '" idlinea="' . $line->idlinea . '">';

            if (false === empty($line->referencia)) {
                $variant = new Variante();
                $where = [new DataBaseWhere('referencia', $line->referencia)];
                if ($variant->loadFromCode('', $where)) {
                    $html .= '<td>' . self::getImage($variant, 'photo-modal') . '</td>';
                }
            } else {
                $html .= '<td></td>';
            }

            $html .= '<td><b>' . $line->referencia . '</b> ' . $descripcion . '</td>';
            $html .= '<td class="text-center align-middle">' . $line->cantidad . '</td>';
            $html .= '<td class="text-right text-nowrap align-middle">' . ToolBox::coins()::format($price) . '</td>';
            $html .= '<td class="text-right text-nowrap align-middle">' . ToolBox::coins()::format($total) . '</td>';

            if ($refundedQty > 0) {
                $html .= '<td class="table-success align-middle"><input type="number" class="form-control text-right" min="0" max="' . $refundedQty . '" value="0" /></td>';
            } else {
                $html .= '<td></td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody>'
            . '</table>'
            . '</div>';

        return $html;
    }

    public static function saveReturn(array $formData, User $user, TpvCaja $caja, ?string $codagente): bool
    {
        if ((int)$formData['lines'] === 0) {
            ToolBox::i18nLog()->warning('no-lines-return');
            return false;
        }

        $tpv = $caja->getTerminal();
        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $tpv->doctype;

        $docOld = new $modelClass();
        if (!$docOld->loadFromCode($formData['idDocReturn'])) {
            return false;
        }

        if ($tpv->doctype === 'FacturaCliente') {
            return self::saveReturnInvoice($docOld, $formData, $user, $tpv, $caja, $codagente);
        }

        return self::saveReturnDeliveryNote($docOld, $formData);
    }

    protected static function saveReturnDeliveryNote(AlbaranCliente $docOld, array $formData): bool
    {
        $dataBase = new DataBase();
        $dataBase->beginTransaction();

        for ($num = 0; $num < $formData['lines']; $num++) {
            foreach ($docOld->getLines() as $lineOld) {
                if ($lineOld->referencia != $formData['referencia_' . $num]) {
                    continue;
                }
                $lineOld->cantidad = $lineOld->cantidad - $formData['cantidad_' . $num];
                if ($lineOld->save() === false) {
                    $dataBase->rollback();
                    return false;
                }
            }
        }

        $lines = $docOld->getLines();
        if (Calculator::calculate($docOld,$lines, true) === false) {
            $dataBase->rollback();
            return false;
        }

        $dataBase->commit();
        $docOld->code = $docOld->PrimaryColumnValue();
        self::$lastDocSave = $docOld;
        return true;
    }

    protected static function saveReturnInvoice(FacturaCliente $docOld, array $formData, User $user, TpvTerminal $tpv, TpvCaja $caja, ?string $codagente): bool
    {
        $dataBase = new DataBase();
        $dataBase->beginTransaction();

        $user->codagente = $codagente;
        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $tpv->doctype;
        $doc = new $modelClass();

        $cliente = new Cliente();
        $cliente->loadFromCode($docOld->codcliente);
        $doc->setSubject($cliente);
        $doc->setAuthor($user);

        $doc->codpago = $docOld->codpago;
        $doc->idtpv = $tpv->idtpv;
        $doc->idcaja = $caja->idcaja;
        $doc->editable = 1;

        if ($docOld->editable) {
            foreach ($docOld->getAvailableStatus() as $status) {
                if ($status->editable) {
                    continue;
                }

                $docOld->idestado = $status->idestado;
                if ($docOld->save() === false) {
                    $dataBase->rollback();
                    return false;
                }
            }
        }

        $doc->codigorect =  $docOld->codigo;
        $doc->idfacturarect =  $docOld->idfactura;
        $doc->codserie = AppSettings::get('default', 'codserierec');

        if ($doc->save() === false) {
            $dataBase->rollback();
            return false;
        }

        $linesOld = $docOld->getLines();
        for ($num = 0; $num < $formData['lines']; $num++) {
            if (isset($formData['referencia_' . $num])) {
                $newLine = $doc->getNewProductLine($formData['referencia_' . $num]);
            } else {
                $newLine = $doc->getNewLine();
            }

            $newLine->cantidad = 0 - (float)$formData['cantidad_' . $num];
            $newLine->idlinearect = $formData['idlinea_' . $num];

            foreach ($linesOld as $lineOld) {
                if ((int)$formData['idlinea_' . $num] !== (int)$lineOld->idlinea) {
                    continue;
                }
                $newLine->pvpunitario = $lineOld->pvpunitario;
                $newLine->descripcion = $lineOld->descripcion;
                $newLine->dtopor = $lineOld->dtopor;
                break;
            }

            if ($newLine->save() === false) {
                $dataBase->rollback();
                return false;
            }
        }

        $lines = $doc->getLines();
        $doc->idestado = $docOld->idestado;
        if (Calculator::calculate($doc,$lines, true) === false) {
            $dataBase->rollback();
            return false;
        }

        $dataBase->commit();
        $doc->code = $doc->PrimaryColumnValue();
        self::$lastDocSave = $doc;
        return true;
    }

    protected static function changeDivisa(string $coddivisa)
    {
        $divisa = new Divisa();
        $divisa->loadFromCode($coddivisa);

        $divisaTools = new DivisaTools();
        $divisaTools->findDivisa($divisa);
    }
}