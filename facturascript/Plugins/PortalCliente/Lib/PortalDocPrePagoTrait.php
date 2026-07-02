<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Lib;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\FormasPago;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait PortalDocPrePagoTrait
{
    protected function createViewPrePagos(string $viewName = 'ListPrePago'): void
    {
        $this->addListView($viewName, 'PrePago', 'payments', 'fas fa-coins')
            ->addOrderBy(['creationdate'], 'date', 2)
            ->addOrderBy(['amount'], 'amount')
            ->addOrderBy(['codpago'], 'payment-method')
            ->addSearchFields(['id', 'notes'])
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false)
            ->setSettings('checkBoxes', false)
            ->setSettings('clickable', false)
            ->disableColumn('customer', true)
            ->disableColumn('id', true)
            ->addFilterPeriod('creationdate', 'creation-date', 'creationdate')
            ->addFilterNumber('amount-gte', 'amount', 'amount', '>=')
            ->addFilterNumber('amount-lte', 'amount', 'amount', '<=')
            ->addFilterSelect('codpago', 'payment-method', 'codpago', FormasPago::codeModel());
    }

    protected function loadDataPrePagos($view): void
    {
        $where = [
            new DataBaseWhere('modelid', $this->views['main']->model->primaryColumnValue()),
            new DataBaseWhere('modelname', $this->views['main']->model->modelClassName()),
        ];
        $orderBy = ['creationdate' => 'DESC', 'id' => 'DESC'];
        $view->loadData('', $where, $orderBy);
        $view->setSettings('active', $view->count > 0 && $this->contact->exists());
    }
}