<?php
/**
 * This file is part of the Produccion plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 * All Rights Reserved.
 */
namespace FacturaScripts\Plugins\Produccion\Lib\Produccion;

use FacturaScripts\Plugins\Produccion\Model\OrdenNumSerie;

/**
 * Class to manage num serie production
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class NumSerieManager
{
    /**
     * Assign num serie to production order
     *
     * @param array $data
     * @return void
     */
    public static function assignNumSerie(array $data): void
    {
        $iddoc = array_key_first($data['iddoc'] ?? []) ?? 0;
        if (empty($iddoc)) {
            return;
        }

        $orderNumSerie = new OrdenNumSerie();
        $numSerieList = $data['numserie'] ?? [];
        foreach ($numSerieList as $key => $id) {
            if (false === $orderNumSerie->load($id)) {
                continue;
            }
            $orderNumSerie->setDelivery((int)$iddoc);
        }
    }

    /**
     * Unassign num serie from production order
     *
     * @param array $data
     * @return void
     */
    public static function unAssignNumSerie(array $data): void
    {
        $iddoc = array_key_first($data['iddoc'] ?? []) ?? 0;
        if (empty($iddoc)) {
            return;
        }

        $orderNumSerie = new OrdenNumSerie();
        $numSerieList = $data['idnumserie'] ?? [];
        foreach ($numSerieList as $key => $id) {
            if (false === $orderNumSerie->load($id)
                || $orderNumSerie->iddelivery !== (int)$iddoc
            ) {
                continue;
            }
            $orderNumSerie->setDelivery(0);
        }
    }
}
