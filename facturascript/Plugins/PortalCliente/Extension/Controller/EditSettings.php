<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditSettings
{
    public function loadData(): Closure
    {
        return function($viewName, $view) {
            if ($viewName !== 'SettingsPortalCliente') {
                return;
            }

            $this->loadStatusDocument($viewName, 'approve-status-estimations', 'PresupuestoCliente');
            $this->loadStatusDocument($viewName, 'cancel-status-estimations', 'PresupuestoCliente');
            $this->loadStatusDocument($viewName, 'payment-status-estimations', 'PresupuestoCliente');
            $this->loadStatusDocument($viewName, 'cancel-status-orders', 'PedidoCliente');
            $this->loadStatusDocument($viewName, 'payment-status-orders', 'PedidoCliente');
            $this->loadStatusDocument($viewName, 'payment-status-delivery-notes', 'AlbaranCliente');
            $this->loadStatusDocument($viewName, 'payment-status-invoices', 'FacturaCliente');
            $this->loadSerieShop($viewName, 'serie-shop');
        };
    }

    protected function loadSerieShop(): Closure
    {
        return function (string $viewName, string $columnName) {
            // comprobamos la columna
            $column = $this->views[$viewName]->columnForName($columnName);
            if (empty($column) || $column->widget->getType() !== 'select') {
                return;
            }

            // obtenemos las series que no sean de tipo 'R'
            $where = [new DataBaseWhere('tipo', 'R', '!=')];
            $values = $this->codeModel->all('series', 'codserie', 'descripcion', true, $where);
            $column->widget->setValuesFromCodeModel($values);
        };
    }

    protected function loadStatusDocument(): Closure
    {
        return function (string $viewName, string $columnName, string $type) {
            // comprobamos la columna
            $column = $this->views[$viewName]->columnForName($columnName);
            if (empty($column) || $column->widget->getType() !== 'select') {
                return;
            }

            // obtenemos los estados para ese tipo de documento
            $where = [
                new DataBaseWhere('tipodoc', $type),
                new DataBaseWhere('predeterminado', false),
            ];
            $values = $this->codeModel->all('estados_documentos', 'idestado', 'nombre', true, $where);
            $column->widget->setValuesFromCodeModel($values);
        };
    }
}
