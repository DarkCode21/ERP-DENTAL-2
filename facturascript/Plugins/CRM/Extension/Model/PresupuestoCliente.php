<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CRM\Extension\Model;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Plugins\CRM\Model\CrmOportunidad;

/**
 * Description of PresupuestoCliente
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class PresupuestoCliente
{

    public function deleteBefore(): Closure
    {
        return function () {
            $opportunity = new CrmOportunidad();
            $where = [new DataBaseWhere('idpresupuesto', $this->idpresupuesto)];
            if ($opportunity->loadFromCode('', $where)) {
                $opportunity->neto = 0;
                $opportunity->netoeuros = 0;
                $opportunity->tasaconv = 1;
                $opportunity->save();
            }
        };
    }

    public function save(): Closure
    {
        return function () {
            $opportunity = new CrmOportunidad();
            $where = [new DataBaseWhere('idpresupuesto', $this->idpresupuesto)];
            if ($opportunity->loadFromCode('', $where)) {
                $opportunity->coddivisa = $this->coddivisa;
                $opportunity->neto = $this->neto;
                $opportunity->netoeuros = empty($this->tasaconv) ? 0 : round($this->neto / $this->tasaconv, 5);
                $opportunity->tasaconv = $this->tasaconv;
                $opportunity->save();
            }
        };
    }
}
