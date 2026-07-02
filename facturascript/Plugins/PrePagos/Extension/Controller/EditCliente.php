<?php
/**
 * Copyright (C) 2024-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PrePagos\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\FormasPago;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\PrePagoCli;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditCliente
{
    public function createViews(): Closure
    {
        return function () {
            $this->addListView('ListPrePagoCli', 'PrePagoCli', 'payments', 'fa-solid fa-coins')
                ->addOrderBy(['creationdate'], 'date', 2)
                ->addOrderBy(['id'], 'id')
                ->addOrderBy(['amount'], 'amount')
                ->addSearchFields(['id', 'notes'])
                ->disableColumn('customer', true)
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

            $customer = new Cliente();
            if (false === $customer->loadFromCode($this->request->get('code'))) {
                Tools::log()->warning('record-not-found');
                return;
            }

            $payment = new PrePagoCli();
            $payment->amount = $this->request->get('amount');
            $payment->codcliente = $customer->codcliente;
            $payment->payment_date = $this->request->get('payment_date');
            $payment->modelid = $customer->codcliente;
            $payment->modelname = $customer->modelClassName();
            $payment->notes = $this->request->get('notes');

            if ($payment->save()) {
                Tools::log()->info('record-updated-correctly');
                return;
            }

            Tools::log()->info('record-save-error');
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName != 'ListPrePagoCli') {
                return;
            }

            $codcliente = $this->getViewModelValue($this->getMainViewName(), 'codcliente');
            $where = [new DataBaseWhere('codcliente', $codcliente)];
            $view->loadData('', $where, ['creationdate' => 'DESC']);
        };
    }
}
