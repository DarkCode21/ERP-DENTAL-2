<?php
/**
 * Copyright (C) 2023-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PrePagos\Lib\Accounting;

use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Model\Subcuenta;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\CuentaEspecial;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\PrePagoCli;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PrePagoCliToAccounting
{
    public static function generate(int $id, bool $save = false): bool
    {
        // cargamos el prepago
        $prepago = new PrePagoCli();
        if (false === $prepago->loadFromCode($id)) {
            return false;
        }

        // si el prepago ya tiene asiento, no hacemos nada
        if ($prepago->idasiento) {
            return false;
        }

        $entry = new Asiento();
        $doc = $prepago->getDocument();
        if (false === static::setAccountingData($entry, $prepago, $doc)) {
            return false;
        }

        // guardamos el asiento
        $entry->concepto = static::getConcept($doc);
        $entry->documento = $doc->codigo;
        if (false === $entry->save()) {
            Tools::log()->warning('accounting-entry-error');
            return false;
        }

        if (static::addBankLine($entry, $doc, $prepago)
            && static::addCustomerLine($entry, $prepago)
            && $entry->isBalanced()) {
            $prepago->idasiento = $entry->idasiento;
            return !$save || $prepago->save();
        }

        // si fallo algo, borramos el asiento
        Tools::log()->warning('accounting-lines-error');
        $entry->delete();
        return false;
    }

    public static function regenerate(array $docs): void
    {
        // recorremos los documentos
        foreach ($docs as $doc) {
            // si el documento no tiene los métodos getPayments o parentDocuments, no hacemos nada
            if (false === method_exists($doc, 'parentDocuments') ||
                false === method_exists($doc, 'getPayments')) {
                continue;
            }

            // buscamos los padres de los padres
            $moreParents = $doc->parentDocuments();

            // si hay más padres, los procesamos
            if (false === empty($moreParents)) {
                static::regenerate($moreParents);
            }

            // buscamos los prepagos relacionados
            foreach ($doc->getPayments() as $prepaid) {
                // obtenemos el asiento general del prepagado de la sesión
                $idasientoPrepaid = Session::get('idasientoPrepaid') ?? null;

                // si no tenemos el asiento general del prepagado, lo creamos
                if (is_null($idasientoPrepaid)) {
                    // guardamos el prepago para generar el asiento
                    if ($prepaid->save()) {
                        // guardamos el asiento general del prepago en la sesión
                        Session::set('idasientoPrepaid', $prepaid->idasiento);
                        continue;
                    }

                    // si fallo al guardar el prepago, no hacemos nada
                    return;
                }

                // guardamos el prepago con el asiento general
                $prepaid->idasiento = $idasientoPrepaid;
                $prepaid->save();
            }
        }
    }

    protected static function addBankLine(Asiento $accountEntry, BusinessDocument $doc, PrePagoCli $prepago): bool
    {
        $account = $prepago->getPaymentMethod()->getSubcuenta($doc->codejercicio, true);
        if (false === $account->exists()) {
            return false;
        }

        $line = $accountEntry->getNewLine($account);
        $line->debe = $prepago->amount;
        return $line->save();
    }

    protected static function addCustomerLine(Asiento $accountEntry, PrePagoCli $prepago): bool
    {
        $account = static::getCustomerAdvanceAccounting($accountEntry->codejercicio) ??
            $prepago->getDocument()->getSubject()->getSubcuenta($accountEntry->codejercicio, true);
        if (false === $account->exists()) {
            return false;
        }

        $line = $accountEntry->getNewLine($account);
        $line->haber = $prepago->amount;
        return $line->save();
    }

    protected static function getCustomerAdvanceAccounting(string $codejercicio): ?Subcuenta
    {
        // buscamos la subcuenta de anticipos de clientes
        $special = new CuentaEspecial();
        $account = $special->loadFromCode('ANTCLI') ?
            $special->getSubcuenta($codejercicio) :
            null;

        if ($account === null) {
            return null;
        }

        return $account->exists() ? $account : null;
    }

    protected static function setAccountingData(Asiento &$entry, PrePagoCli $prepago, $doc): bool
    {
        $date = $prepago->payment_date ?? $prepago->creationdate;

        $ejercicio = new Ejercicio();
        $ejercicio->idempresa = $doc->idempresa;
        if ($ejercicio->loadFromDate(Tools::date($date))) {
            $entry->codejercicio = $ejercicio->codejercicio;
            $entry->fecha = Tools::date($date);
            $entry->idempresa = $ejercicio->idempresa;
            $entry->importe = $prepago->amount;
            return true;
        }

        Tools::log()->warning('accounting-exercise-not-found');
        return false;
    }

    protected static function getConcept($doc): string
    {
        switch ($doc->modelClassName()) {
            case 'AlbaranCliente':
                $docType = '-customer-delivery-note';
                break;

            case 'PedidoCliente':
                $docType = '-customer-order';
                break;

            case 'PresupuestoCliente':
                $docType = '-customer-estimation';
                break;

            default:
                $docType = '';
                break;
        }

        // devolvemos el concepto del asiento
        return Tools::lang()->trans('accounting-prepaid' . $docType, ['%code%' => $doc->codigo]);
    }
}
