<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PrePagos\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\FormasPago;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\PrePagoProv;
use FacturaScripts\Dinamic\Model\Proveedor;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditProveedor
{
    public function createViews(): Closure
    {
        return function () {
            $this->addListView('ListPrePagoProv', 'PrePagoProv', 'payments', 'fa-solid fa-coins')
                ->addOrderBy(['creationdate'], 'date', 2)
                ->addOrderBy(['id'], 'id')
                ->addOrderBy(['amount'], 'amount')
                ->addSearchFields(['id', 'notes'])
                ->disableColumn('supplier', true)
                ->addFilterPeriod('creationdate', 'creation-date', 'creationdate')
                ->addFilterNumber('amount-gte', 'amount', 'amount', '>=')
                ->addFilterNumber('amount-lte', 'amount', 'amount', '<=')
                ->addFilterSelect('codpago', 'payment-method', 'codpago', FormasPago::codeModel())
                ->setSettings('modalInsert', 'add-pre-payment');
        };
    }

    public function execPreviousAction(): Closure
    {
        return function ($action) {
            if ($action !== 'add-pre-payment') {
                return;
            }

            if (false === $this->validateFormToken()) {
                return;
            }

            $supplier = new Proveedor();
            if (false === $supplier->loadFromCode($this->request->get('code'))) {
                Tools::log()->warning('record-not-found');
                return;
            }

            $payment = new PrePagoProv();
            $payment->amount = $this->request->get('amount');
            $payment->codproveedor = $supplier->codproveedor;
            $payment->payment_date = $this->request->get('payment_date');
            $payment->modelid = $supplier->codproveedor;
            $payment->modelname = $supplier->modelClassName();
            $payment->notes = $this->request->get('notes');

            if ($payment->save()) {
                Tools::log()->notice('record-updated-correctly');
                return;
            }

            Tools::log()->error('record-save-error');
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName != 'ListPrePagoProv') {
                return;
            }

            $codproveedor = $this->getViewModelValue($this->getMainViewName(), 'codproveedor');
            $where = [new DataBaseWhere('codproveedor', $codproveedor)];
            $view->loadData('', $where, ['creationdate' => 'DESC']);
        };
    }
}
