<?php
/**
 * Copyright (C) 2023-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Extension\Model;

use Closure;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\ProductoLoteMovimiento;

class FacturaCliente
{
    public function saveUpdate(): Closure
    {
        return function () {
            // si no es una factura rectificativa o es editable, no hacemos nada
            if (empty($this->idfacturarect) || $this->editable) {
                return true;
            }

            // si ya tiene lotes, no hacemos nada
            $movimiento = new ProductoLoteMovimiento();
            $whereDoc = [
                new DataBaseWhere('docmodel', 'FacturaCliente'),
                new DataBaseWhere('docid', $this->idfactura),
            ];
            if ($movimiento->count($whereDoc) > 0) {
                return true;
            }

            // recorremos las líneas de la factura rectificativa
            foreach ($this->getLines() as $line) {
                // obtenemos la línea original
                $oldLine = $line->get($line->idlinearect);
                if (empty($oldLine)) {
                    continue;
                }

                $pte = $line->cantidad;

                // obtenemos el movimiento de lote original
                foreach ($oldLine->getMovimientosLinea() as $mov) {
                    if ($pte == 0) {
                        break;
                    }

                    // copiamos el movimiento
                    $newMov = new ProductoLoteMovimiento();
                    $newMov->cantidad = 0 - min(abs($pte), $mov->cantidad);
                    $newMov->docfecha = Tools::dateTime($this->fecha . ' ' . $this->hora);
                    $newMov->docid = $this->idfactura;
                    $newMov->docmodel = $this->modelClassName();
                    $newMov->documento = $this->codigo;
                    $newMov->fecha = $mov->fecha;
                    $newMov->idlinea = $line->idlinea;
                    $newMov->numserie = $mov->numserie;
                    $newMov->referencia = $line->referencia;
                    if (false === $newMov->save()) {
                        return false;
                    }

                    $pte += $newMov->cantidad;
                }
            }

            return true;
        };
    }
}
