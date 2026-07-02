<?php
/**
* This file is part of the Produccion plugin for FacturaScripts.
* FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
* Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
*
* This program and its files are under the terms of the license specified in the LICENSE file.
* All Rights Reserved.
*/
namespace FacturaScripts\Plugins\Produccion\Extension\Lib\PlantillasPDF\Helper;

use Closure;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\LineaAlbaranCliente;
use FacturaScripts\Dinamic\Model\OrdenNumSerie;

/**
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class BusinessDocLinesHelper
{
    public function get(): Closure
    {
        /**
         * Return line description if the line has numserie.
         * Else return null to use the default description.
         *
         * @param AlbaranCliente $model
         * @param LineaAlbaranCliente $line
         * @param array $field
         * @return ?string
         */
        return function ($model, $line, array $field): ?string {
            if ($model->modelClassName() === 'AlbaranCliente'
                && $field['key'] === 'descripcion'
            ) {
                return $this->getNumSerie($line);
            }
            return null;
        };
    }

    protected function getNumSerie(): Closure
    {
        return function ($line): ?string {
            $where = [
                Where::eq('reference', $line->referencia),
                Where::eq('iddelivery', $line->idalbaran),
            ];
            $numSerieList = OrdenNumSerie::all($where);
            if (empty($numSerieList)) {
                return null;
            }

            $sep = '';
            $description = $line->descripcion . '<br/>';
            foreach ($numSerieList as $numSerie) {
                $description .= $numSerie->numserie . $sep;
                $sep = ', ';
            }
            return $description;
        };
    }
}
