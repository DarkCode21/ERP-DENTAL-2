<?php
/**
 * Copyright (C) 2022-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Extension\Lib;

use Closure;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\ProductoLoteMovimiento;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class BusinessDocumentGenerator
{
    public function cloneLine(): Closure
    {
        return function ($prototype, $line, $cantidad, $newDoc, $newLine) {
            // si no es un albarán, no hacemos nada
            if (false === in_array($prototype->modelClassName(), ['AlbaranProveedor', 'AlbaranCliente'])) {
                return;
            }

            // copiamos los movimientos al nuevo documento hasta que nos quedemos sin cantidad
            foreach ($this->getMovimientosLinea($line) as $mov) {
                if ($cantidad == 0) {
                    break;
                }

                $newMov = new ProductoLoteMovimiento();
                $newMov->cantidad = $cantidad >= 0 ?
                    min($cantidad, $mov->cantidad) :
                    max($cantidad, $mov->cantidad);
                $newMov->docfecha = Tools::dateTime($newDoc->fecha . ' ' . $newDoc->hora);
                $newMov->docid = $newDoc->primaryColumnValue();
                $newMov->docmodel = $newDoc->modelClassName();
                $newMov->documento = $newDoc->codigo;
                $newMov->fecha = $mov->fecha;
                $newMov->idclone = $mov->id;
                $newMov->idlinea = $newLine->idlinea;
                $newMov->numserie = $mov->numserie;
                $newMov->referencia = $newLine->referencia;
                if (false === $newMov->save()) {
                    return false;
                }

                // reducimos la cantidad restante
                $cantidad -= $newMov->cantidad;
            }
        };
    }

    public function generateBefore(): Closure
    {
        return function ($prototype, $lines, $quantity, $properties, $newDoc) {
            Session::set('autobatchserialsales', Tools::settings('default', 'autobatchserialsales', false));
            Tools::settingsSet('default', 'autobatchserialsales', false);
            Tools::settingsSave();
        };
    }

    public function generateFalse(): Closure
    {
        return function ($prototype, $lines, $quantity, $properties, $newDoc) {
            $this->restoreAutoBatchSerialSales();
        };
    }

    public function generateTrue(): Closure
    {
        return function ($prototype, $lines, $quantity, $properties, $newDoc, $newLines) {
            $this->restoreAutoBatchSerialSales();
        };
    }

    public function getMovimientosLinea(): Closure
    {
        return function ($line) {
            $doc = $line->getDocument();
            if (empty($doc) || empty($doc->primaryColumnValue())) {
                return [];
            }

            $where = [
                new DataBaseWhere('docid', $doc->primaryColumnValue()),
                new DataBaseWhere('docmodel', $doc->modelClassName()),
                new DataBaseWhere('idlinea', $line->idlinea),
                new DataBaseWhere('referencia', $line->referencia)
            ];
            $orderBy = ['id' => 'ASC'];
            return ProductoLoteMovimiento::all($where, $orderBy, 0, 0);
        };
    }

    public function restoreAutoBatchSerialSales(): Closure
    {
        return function () {
            Tools::settingsSet('default', 'autobatchserialsales', Session::get('autobatchserialsales'));
            Tools::settingsSave();
        };
    }
}
