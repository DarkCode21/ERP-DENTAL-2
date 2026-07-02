<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PrePagos\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\FormasPago;

trait PrePagoProvListControllerTrait
{
    protected function createViewPrePago(): Closure
    {
        return function () {
            $this->addView('ListPrePagoProv', 'PrePagoProv', 'payments', 'fa-solid fa-coins')
                ->addOrderBy(['creationdate'], 'date', 2)
                ->addOrderBy(['id'], 'id')
                ->addOrderBy(['amount'], 'amount')
                ->addSearchFields(['id', 'notes'])
                ->addFilterPeriod('creationdate', 'creation-date', 'creationdate')
                ->addFilterAutocomplete('codproveedor', 'supplier', 'codproveedor', 'proveedores', 'codproveedor', 'nombre')
                ->addFilterNumber('amount-gte', 'amount', 'amount', '>=')
                ->addFilterNumber('amount-lte', 'amount', 'amount', '<=')
                ->addFilterSelect('codpago', 'payment-method', 'codpago', FormasPago::codeModel())
                ->setSettings('btnNew', false)
                ->setSettings('btnDelete', false)
                ->setSettings('checkBoxes', false);
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            $mvn = $this->getMainViewName();
            switch ($viewName) {
                case 'ListPrePagoProv':
                    $where = [new DataBaseWhere('modelname', $this->views[$mvn]->model->modelClassName())];
                    $view->loadData('', $where);
                    break;
            }
        };
    }
}