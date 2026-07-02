<?php
/**
 * This file is part of Amortizaciones plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Amortizaciones  Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\Amortizaciones\Extension\Model;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Plugins\Amortizaciones\Model\LineaAmortizacion;

/**
 * Description of Asiento
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class Asiento
{

    /** @var LineaAmortizacion[] */
    protected $lines;

    /**
     * Get list of amortization lines with the accounting code before delete.
     * This is necessary becouse database foreign key set null the field into lines.
     *
     * @return Closure
     */
    public function deleteBefore(): Closure
    {
        return function (): bool {
            $where = [ new DataBaseWhere('idasiento', $this->idasiento) ];
            $model = new LineaAmortizacion();
            $this->lines = $model->all($where, [], 0, 0);
            return true;
        };
    }

    /**
     * if exists lines with accounting entry,
     * recalculate amortization amount and residual amount.
     */
    public function delete(): Closure
    {
        return function (): bool {
            if (empty($this->lines)) {
                return true;
            }

            // recalculate amortization amount and residual amount.
            foreach ($this->lines as $line) {
                $line->idasiento = null;        // set null for avoid FK error.
                $line->amortizado = 0.00;
                if (false === $line->save()) {
                    return false;
                }
            }
            return true;
        };
    }
}
