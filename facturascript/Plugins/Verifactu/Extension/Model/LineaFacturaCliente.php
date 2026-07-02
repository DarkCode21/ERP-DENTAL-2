<?php

/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Extension\Model;

use Closure;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class LineaFacturaCliente
{
    public function clear(): Closure
    {
        return function () {
            $this->vf_send = true;
        };
    }

    public function deleteBefore(): Closure
    {
        return function () {
            // si la factura está dada de alta o anulada en verifactu, no se pueden eliminar líneas
            $invoice = $this->getDocument();
            if (!empty($invoice->primaryColumnValue()) && ($invoice->verifactuCheckAlta() || $invoice->verifactuCheckAnulacion())) {
                Tools::log()->warning('verifactu-invoice-has-events', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }
        };
    }

    public function saveBefore(): Closure
    {
        return function () {
            $dataBase = new DataBase();

            $idlinea = $this->primaryColumnValue();

            // Si no hay idlinea, es un registro nuevo → no hay nada que comparar
            if (empty($idlinea)) {
                return;
            }

            $row = $dataBase->select(
                "SELECT * FROM lineasfacturascli WHERE idlinea = " . $idlinea
            );

            if (empty($row)) {
                // Si no existe en BD, es nuevo → no hay que comparar nada
                return;
            }

            // $row es un array asociativo de la fila en BD
            $row = $row[0];

            // Comprobamos si alguno de los campos relevantes cambió
            $changed = false;
            foreach ($row as $field => $value) {
                if (property_exists($this, $field) && $this->$field != $value) {
                    $changed = true;
                    break;
                }
            }

            if (!$changed) {
                // No hay cambios → no seguimos
                return;
            }

            // si la factura está dada de alta o anulada en verifactu, no se pueden añadir o editar líneas
            $invoice = $this->getDocument();
            if (!empty($invoice->primaryColumnValue()) && ($invoice->verifactuCheckAlta() || $invoice->verifactuCheckAnulacion())) {
                Tools::log()->warning('verifactu-invoice-has-events', [
                    'model-code' => $this->primaryColumnValue(),
                    'model-class' => $this->modelClassName(),
                ]);
                return false;
            }
        };
    }
}
