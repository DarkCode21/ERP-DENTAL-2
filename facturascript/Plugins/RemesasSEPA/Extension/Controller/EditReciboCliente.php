<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\RemesasSEPA\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

/**
 * Description of EditReciboCliente
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditReciboCliente
{
    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName !== 'EditReciboCliente') {
                return;
            }

            // set widget values
            $where = [new DataBaseWhere('codcliente', $view->model->codcliente)];
            $ibanColumn = $this->views[$viewName]->columnForName('iban');
            if ($ibanColumn) {
                $ibanValues = $this->codeModel->all('cuentasbcocli', 'iban', 'iban', true, $where);
                $ibanColumn->widget->setValuesFromCodeModel($ibanValues);
            }
            $swiftColumn = $this->views[$viewName]->columnForName('swift');
            if ($swiftColumn) {
                $swiftValues = $this->codeModel->all('cuentasbcocli', 'swift', 'swift', true, $where);
                $swiftColumn->widget->setValuesFromCodeModel($swiftValues);
            }
        };
    }
}
