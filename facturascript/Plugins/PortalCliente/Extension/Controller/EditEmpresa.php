<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Extension\Controller;

use Closure;
use FacturaScripts\Core\DataSrc\FormasPago;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditEmpresa
{
    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            $mvn = $this->getMainViewName();
            if ($viewName !== $mvn) {
                return;
            }

            $this->loadPaymentMethodsValues($viewName, 'stripe-payment-method');
            $this->loadPaymentMethodsValues($viewName, 'paypal-payment-method');
            $this->loadPaymentMethodsValues($viewName, 'redsys-payment-method');
        };
    }

    protected function loadPaymentMethodsValues(): Closure
    {
        return function (string $viewName, string $columnName) {
            $column = $this->views[$viewName]->columnForName($columnName);
            if (empty($column) || $column->widget->getType() !== 'select') {
                return;
            }

            $values = [];
            $idempresa = $this->getViewModelValue($viewName, 'idempresa');
            foreach (FormasPago::all() as $paymentMethod) {
                if ($paymentMethod->idempresa === $idempresa) {
                    $values[] = ['value' => $paymentMethod->codpago, 'title' => $paymentMethod->descripcion];
                }
            }

            $column->widget->setValuesFromArray($values, false, true);

        };
    }
}
