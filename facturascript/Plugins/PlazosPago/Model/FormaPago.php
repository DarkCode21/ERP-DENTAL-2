<?php
/**
 * Copyright (C) 2019-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PlazosPago\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\FormaPago as ParentModel;
use FacturaScripts\Core\Tools;

/**
 * Description of FormaPago
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class FormaPago extends ParentModel
{
    /**
     * @return FormaPagoPlazo[]
     */
    public function getPlazos(): array
    {
        $where = [new DataBaseWhere('codpago', $this->codpago)];
        $order = ['dias' => 'ASC', 'meses' => 'ASC'];
        return FormaPagoPlazo::all($where, $order, 0, 0);
    }

    public function test(): bool
    {
        if ($this->pagado && count($this->getPlazos()) > 0) {
            Tools::log()->warning('cannot-mark-payment-as-paid-if-terms');
            $this->pagado = false;
        }

        return parent::test();
    }
}
