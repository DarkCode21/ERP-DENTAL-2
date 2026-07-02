<?php
/**
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TPVneo\Lib\TPVneo;

use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\DivisaTools;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Model\Base\SalesDocumentLine;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Divisa;
use FacturaScripts\Dinamic\Model\PresupuestoCliente;
use FacturaScripts\Dinamic\Model\TpvTerminal;
use FacturaScripts\Dinamic\Model\User;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ParkForm
{
    /** @var SalesDocument */
    protected static $doc;

    /** @var SalesDocumentLine[] */
    protected static $lines = [];

    public static function advancePaymentIsEnabled(): bool
    {
        return class_exists('\\FacturaScripts\\Dinamic\\Model\\PrePago');
    }

    public static function getAdvancePayments(int $idpresupuesto): array
    {
        if (false === self::advancePaymentIsEnabled()) {
            return [];
        }

        $pr = new PresupuestoCliente();
        $pr->loadFromCode($idpresupuesto);
        return $pr->getPayments();
    }

    public static function loadPark(int $idpresupuesto, User $user, TpvTerminal $tpv)
    {
        if (empty($idpresupuesto)) {
            return;
        }

        $pr = new PresupuestoCliente();
        $pr->loadFromCode($idpresupuesto);

        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $tpv->doctype;
        self::$doc = new $modelClass();
        $user->codagente = $pr->codagente;
        self::$doc->setAuthor($user);

        $cliente = new Cliente();
        $cliente->loadFromCode($pr->codcliente);
        self::$doc->setSubject($cliente);

        foreach ($pr->getLines() as $line) {
            $newLine = empty($line->referencia) ?
                self::$doc->getNewLine() :
                self::$doc->getNewProductLine($line->referencia);

            $newLine->cantidad = (float)$line->cantidad;
            $newLine->dtopor = (float)$line->dtopor;
            $newLine->pvpunitario = (float)$line->pvpunitario;
            $newLine->descripcion = $line->descripcion;
            $newLine->idlinea = $line->idlinea;
            self::$lines[] = $newLine;
        }

        SaleForm::setDoc(self::$doc);
        SaleForm::setLines(self::$lines);
        SaleForm::recalculate();
    }

    public static function renderModalPark(TpvTerminal $tpv, ?string $codagente): string
    {
        $html = '';
        $modelPresupuesto = new PresupuestoCliente();
        $where = [
            new DataBaseWhere('aparcado', true),
            new DataBaseWhere('idtpv', $tpv->idtpv),
            new DataBaseWhere('finoferta', date('Y-m-d'), '>=')
        ];

        if (false === is_null($codagente)) {
            $where[] = new DataBaseWhere('codagente', $codagente);
        }

        foreach ($modelPresupuesto->all($where, ['idpresupuesto' => 'desc']) as $pr) {
            self::changeDivisa($pr->coddivisa);
            $html .= '<tr>'
                . '<td class="align-middle">' . $pr->codigo . '</td>'
                . '<td class="align-middle">' . $pr->nombrecliente . '</td>'
                . '<td class="align-middle text-right">' . ToolBox::coins()::format($pr->total) . '</td>'
                . '<td class="align-middle text-right">' . $pr->fecha . ' ' . $pr->hora . '</td>'
                . '<td class="align-middle">' . $pr->observaciones . '</td>'
                . '<td>'
                . '<button onclick="return loadPark(\'' . $pr->idpresupuesto . '\')" title="' . ToolBox::i18n()->trans('show')
                . '" class="btnLoadPark btn btn-success btn-block btn-spin-action"><i class="fas fa-eye fa-fw"></i></button>'
                . '</td>'
                . '<td class="align-middle text-center">'
                . '<button onclick="return modalDeletePark(\'' . $pr->idpresupuesto . '\', this)" title="' . ToolBox::i18n()->trans('delete')
                . '" class="btnDeletePark btn btn-danger btn-block btn-spin-action"><i class="fas fa-trash-alt fa-fw"></i></button>'
                . '</td>';
        }

        if (empty($html)) {
            $html .= '<tr class="table-warning">'
                . '<td colspan="7">' . ToolBox::i18n()->trans('no-data') . '</td>'
                . '</tr>';
        }

        return $html;
    }

    public static function savePark(array $formData, User $user, TpvTerminal $tpv, ?string $codagente): bool
    {
        $dataBase = new DataBase();
        $dataBase->beginTransaction();
        $user->codagente = $codagente;
        $updateDoc = false;

        $doc = new PresupuestoCliente();
        $cliente = new Cliente();
        $codcliente = empty($formData['codcliente']) ? $tpv->codcliente : $formData['codcliente'];
        $cliente->loadFromCode($codcliente);
        $doc->setSubject($cliente);
        $doc->setAuthor($user);

        if (!empty($formData['codpark'])) {
            $doc->loadFromCode($formData['codpark']);
            $updateDoc = true;
        }

        $doc->aparcado = true;
        $budgetDateEnd = $tpv->budgetdateend;
        $doc->finoferta = date("Y-m-d", strtotime(date("Y-m-d") . "+ " . $budgetDateEnd . " days"));
        $doc->codpago = $tpv->codpago;
        $doc->coddivisa = $tpv->coddivisa;
        $doc->idtpv = $tpv->idtpv;
        $doc->codserie = $tpv->codserie;
        $doc->observaciones = $formData['observations'] ?? '';

        if ($doc->save() === false) {
            $dataBase->rollback();
            return false;
        }

        // lines
        $linesCart = $formData['linesCart'] ?? 100;
        for ($num = 1; $num <= $linesCart; $num++) {
            if (!isset($formData['descripcion_' . $num])) {
                continue;
            }

            // update lines
            foreach ($doc->getLines() as $line) {
                if (false === $updateDoc) {
                    continue;
                }

                if ((int)$formData['idlinea_' . $num] === (int)$line->idlinea) {
                    $line->cantidad = (float)$formData['cantidad_' . $num];

                    if ($tpv->adddiscount) {
                        $line->dtopor = (float)$formData['dtopor_' . $num];
                    }

                    if ($tpv->changeprice || empty($line->referencia)) {
                        $line->pvpunitario = round((100 * floatval($formData['precio_' . $num])) / (100 + floatval($line->iva)), 5);
                    }

                    if ($line->save() === false) {
                        $dataBase->rollback();
                        return false;
                    }

                    continue 2;
                }
            }

            // new line
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

        $lines = $doc->getLines();
        if (false === Calculator::calculate($doc, $lines, true)) {
            $dataBase->rollback();
            return false;
        }

        // guardamos el PrePago
        if (self::advancePaymentIsEnabled() && (float)$formData['advance-payment-amount'] > 0) {
            $modelClass = '\\FacturaScripts\\Dinamic\\Model\\PrePago';
            $advancePayment = new $modelClass();
            $advancePayment->amount = (float)$formData['advance-payment-amount'];
            $advancePayment->codcliente = $doc->codcliente;
            $advancePayment->codpago = $formData['advance-payment'] ?? $tpv->codpago;
            $advancePayment->modelid = $doc->primaryColumnValue();
            $advancePayment->modelname = $doc->modelClassName();
            $advancePayment->save();
        }

        $dataBase->commit();
        SaleForm::clearCart($tpv);

        return true;
    }

    public static function totalParks(TpvTerminal $tpv, ?string $codagente): string
    {
        $doc = new PresupuestoCliente();
        $where = [
            new DataBaseWhere('aparcado', true),
            new DataBaseWhere('idtpv', $tpv->idtpv),
            new DataBaseWhere('finoferta', date('Y-m-d'), '>=')
        ];

        if (false === is_null($codagente)) {
            $where[] = new DataBaseWhere('codagente', $codagente);
        }
        $cont = $doc->count($where);
        return $cont > 0 ? (string)$cont : '';
    }

    protected static function changeDivisa(string $coddivisa)
    {
        $divisa = new Divisa();
        $divisa->loadFromCode($coddivisa);

        $divisaTools = new DivisaTools();
        $divisaTools->findDivisa($divisa);
    }
}