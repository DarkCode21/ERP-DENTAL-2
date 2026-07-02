<?php
/**
 * Copyright (C) 2020-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TarifasAvanzadas\Extension\Controller;

use Closure;

/**
 * Description of ListTarifa
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ListTarifa
{
    public function createViews(): Closure
    {
        return function () {
            $viewName = 'ListDescuentoCliente';
            $this->addView($viewName, 'DescuentoCliente', 'discounts', 'fas fa-tags');
            $this->addSearchFields($viewName, ['observaciones', 'referencia']);
            $this->addOrderBy($viewName, ['fecha0'], 'from-date');
            $this->addOrderBy($viewName, ['fecha1'], 'until-date');
            $this->addOrderBy($viewName, ['porcentaje'], 'percentage');
            $this->addOrderBy($viewName, ['prioridad'], 'priority', 2);
            $this->addOrderBy($viewName, ['referencia'], 'reference');
        };
    }
}
