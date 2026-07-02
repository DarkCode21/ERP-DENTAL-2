<?php
/**
 * This file is part of Amortizaciones plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Amortizaciones  Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\Amortizaciones\Model\Join;

use FacturaScripts\Dinamic\Model\Base\JoinModel;

/**
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AmortizacionSubcuenta extends JoinModel
{

    /**
     * List of fields or columns to select clausule
     */
    protected function getFields(): array
    {
        return [
            'code' => 'subcuentas.code',
            'id' => 'subcuentas.id',
            'grouptype' => 'subcuentas.grouptype',
            'groupid' => 'subcuentas.groupid',
            'description' => '(SELECT descripcion FROM cuentas WHERE codcuenta = subcuentas.code LIMIT 1)',
        ];
    }

    /**
     * List of tables related to from clausule
     */
    protected function getSQLFrom(): string
    {
        return 'amortizaciones_subcuentas subcuentas';
    }

    /**
     * List of tables required for the execution of the view.
     */
    protected function getTables(): array
    {
        return [
        ];
    }
}
