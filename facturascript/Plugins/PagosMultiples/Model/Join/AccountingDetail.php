<?php
/**
 * This file is part of PagosMultiples plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * PagosMultiples  Copyright (C) 2020-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\PagosMultiples\Model\Join;

use FacturaScripts\Core\Model\Base\JoinModel;
use FacturaScripts\Dinamic\Model\Asiento;

/**
 * Class for read accounting entry data of receipt group.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class AccountingDetail extends JoinModel
{

    /**
     * Returns the url where to see / modify the data.
     *
     * @param string $type
     * @param string $list
     * @return string
     */
    public function url(string $type = 'auto', string $list = 'List'): string
    {
        $entry = new Asiento();
        $entry->idasiento = $this->idasiento;
        return $entry->url($type, $list);
    }

    /**
     * List of fields or columns to select clausule
     */
    protected function getFields(): array
    {
        return [
            'idasiento' => 'partidas.idasiento',
            'idpartida' => 'partidas.idpartida',
            'idsubcuenta' => 'partidas.idsubcuenta',
            'codsubcuenta' => 'partidas.codsubcuenta',
            'concepto' => 'partidas.concepto',
            'debe' => 'partidas.debe',
            'haber' => 'partidas.haber',
            'saldo' => 'partidas.saldo',
            'numero' => 'asientos.numero',
            'fecha' => 'asientos.fecha',
        ];
    }

    /**
     * List of tables related to from clausule
     */
    protected function getSQLFrom(): string
    {
        return 'partidas LEFT JOIN asientos ON partidas.idasiento = asientos.idasiento';
    }

    /**
     * List of tables required for the execution of the view.
     */
    protected function getTables(): array
    {
        return ['asientos', 'partidas'];
    }
}
