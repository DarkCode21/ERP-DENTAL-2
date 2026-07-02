<?php
/**
 * Copyright (C) 2021-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\FacturasCompraUniq\Extension\Model;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class FacturaProveedor
{
    public function clear(): Closure
    {
        return function () {
            $this->fechaprov = Tools::date();
        };
    }

    public function saveBefore(): Closure
    {
        return function () {
            if (empty($this->numproveedor)) {
                // saltamos si no hay número de proveedor
                return;
            }

            // comprobamos que no exista otra factura con este proveedor, número y fecha
            $where = [
                new DataBaseWhere('codproveedor', $this->codproveedor),
                new DataBaseWhere('fechaprov', $this->fechaprov),
                new DataBaseWhere('numproveedor', $this->numproveedor)
            ];
            foreach ($this->all($where, [], 0, 0) as $invoice) {
                if ($invoice->idfactura == $this->idfactura) {
                    // excluimos la propia factura
                    continue;
                }

                Tools::log()->warning('repeated-invoice');
                return false;
            }
        };
    }
}
