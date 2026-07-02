<?php
/**
 * Copyright (C) 2019-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\RemesasSEPA\Controller;

use Digitick\Sepa\Exception\InvalidArgumentException;
use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Plugins\RemesasSEPA\Lib\Accounting\RemesaToAccounting;
use FacturaScripts\Plugins\RemesasSEPA\Lib\RemesaPagosCli;
use FacturaScripts\Plugins\RemesasSEPA\Model\RemesaSEPA;

/**
 * Description of EditRemesaSEPA
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditRemesaSEPA extends EditController
{
    public function getModelClassName(): string
    {
        return 'RemesaSEPA';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'accounting';
        $data['title'] = 'charges';
        $data['icon'] = 'fas fa-piggy-bank';
        return $data;
    }

    protected function addReceiptsAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }

        $codes = $this->request->request->get('code', []);
        if (false === is_array($codes)) {
            return true;
        }

        $num = 0;
        foreach ($codes as $code) {
            $receipt = new ReciboCliente();
            if (false === $receipt->loadFromCode($code)) {
                continue;
            }

            $receipt->updateBankAccount();
            if (empty($receipt->iban)) {
                Tools::log()->warning('customer-have-no-iban', ['%customer%' => $receipt->getSubject()->nombre]);
                continue;
            }

            $receipt->idremesa = $this->request->query->get('code');
            if ($receipt->save()) {
                $num++;
            }
        }

        Tools::log()->notice('items-added-correctly', ['%num%' => $num]);
        return true;
    }

    protected function chargedAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }

        $remesa = $this->getRemittance();
        foreach ($remesa->getReceipts() as $receipt) {
            if ($receipt->pagado) {
                continue;
            }

            $receipt->fechapago = $remesa->fechacargo;
            $receipt->nick = $this->user->nick;
            $receipt->pagado = true;
            if (false === $receipt->save()) {
                Tools::log()->warning('record-save-error');
                return true;
            }
        }

        // marcamos la remesa como cobrada
        $remesa->estado = RemesaSEPA::STATUS_DONE;
        if (false === $remesa->save()) {
            Tools::log()->warning('record-save-error');
            return true;
        }

        // recorremos los recibos
        foreach ($remesa->getReceipts() as $receipt) {
            // asignamos el asiento al último pago
            foreach ($receipt->getPayments() as $payment) {
                $payment->idasiento = $remesa->idasiento;
                $payment->save();
                break;
            }
        }

        Tools::log()->notice('record-updated-correctly');
        return true;
    }

    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        $this->createViewReceipts();
        $this->createViewReceiptsAdd();
    }

    protected function createViewReceipts(string $viewName = 'ListReciboClienteIBAN'): void
    {
        $this->addListView($viewName, 'ReciboCliente', 'receipts', 'fas fa-file-invoice-dollar');
        $this->views[$viewName]->addSearchFields(['codigofactura', 'observaciones']);
        $this->views[$viewName]->addOrderBy(['codcliente'], 'customer');
        $this->views[$viewName]->addOrderBy(['importe'], 'amount');
        $this->views[$viewName]->addOrderBy(['fecha'], 'date', 2);
        $this->views[$viewName]->addOrderBy(['vencimiento'], 'expiration');

        // disable buttons
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);

        // disable columns
        $this->views[$viewName]->disableColumn('payment-method');
    }

    protected function createViewReceiptsAdd(string $viewName = 'ListReciboClienteIBAN-add'): void
    {
        $this->addListView($viewName, 'ReciboCliente', 'add', 'fas fa-folder-plus');
        $this->views[$viewName]->addSearchFields(['codigofactura', 'observaciones']);
        $this->views[$viewName]->addOrderBy(['codcliente'], 'customer');
        $this->views[$viewName]->addOrderBy(['importe'], 'amount');
        $this->views[$viewName]->addOrderBy(['fecha'], 'date', 2);
        $this->views[$viewName]->addOrderBy(['vencimiento'], 'expiration');

        // filters
        $this->views[$viewName]->addFilterPeriod('vencimiento', 'expiration', 'vencimiento');
        $this->views[$viewName]->addFilterAutocomplete('codcliente', 'customer', 'codcliente', 'clientes', 'codcliente', 'razonsocial');

        $where = [
            new DataBaseWhere('domiciliado', true),
            new DataBaseWhere('idempresa', $this->getViewModelValue($viewName, 'idempresa')),
        ];
        $paymentMethods = $this->codeModel->all('formaspago', 'codpago', 'descripcion', true, $where);
        $this->views[$viewName]->addFilterSelect('codpago', 'payment-method', 'codpago', $paymentMethods);

        $this->views[$viewName]->addFilterNumber('total', 'amount', 'importe', '>=');
        $this->views[$viewName]->addFilterNumber('total2', 'amount', 'importe', '<=');

        // disable buttons
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);
    }

    /**
     * @return bool
     * @throws Exception
     */
    protected function downloadAction(): bool
    {
        if (false === $this->permissions->allowExport) {
            Tools::log()->warning('not-allowed-export');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }

        // cargamos la remesa y la marcamos como enviada
        $remesa = $this->getRemittance();
        $remesa->estado = RemesaSEPA::STATUS_REVIEW;
        $remesa->save();

        // exportamos el xml
        $this->setTemplate(false);
        $this->response->headers->set('Content-Type', 'text/xml');
        $this->response->headers->set(
            'Content-Disposition',
            'attachment;filename=sepa-' . $remesa->primaryColumnValue() . '.xml'
        );
        $this->response->setContent(RemesaPagosCli::getXML($remesa));
        return false;
    }

    /**
     * @param string $action
     *
     * @return bool
     * @throws InvalidArgumentException
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'add-receipts':
                return $this->addReceiptsAction();

            case 'charged':
                return $this->chargedAction();

            case 'download':
                return $this->downloadAction();

            case 'generate-accounting':
                return $this->generateAccountingAction();

            case 'ready':
                return $this->readyAction();

            case 'rectify':
                return $this->rectifyAction();

            case 'remove-receipts':
                return $this->removeReceiptsAction();

            case 'sent':
                return $this->sentAction();
        }

        return parent::execPreviousAction($action);
    }

    protected function generateAccountingAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }

        $remesa = $this->getRemittance();
        $tool = new RemesaToAccounting();
        $tool->generate($remesa);

        if ($remesa->idasiento && $remesa->save()) {
            // recorremos los recibos
            foreach ($remesa->getReceipts() as $receipt) {
                // asignamos el asiento al último pago
                foreach ($receipt->getPayments() as $payment) {
                    $payment->idasiento = $remesa->idasiento;
                    $payment->save();
                    break;
                }
            }

            Tools::log()->notice('record-updated-correctly');
            return true;
        }

        Tools::log()->warning('record-save-error');
        return true;
    }

    protected function getRemittance(): RemesaSEPA
    {
        $remesa = new RemesaSEPA();
        $remesa->loadFromCode($this->request->query->get('code'));
        return $remesa;
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $mainViewName = $this->getMainViewName();
        $idremesa = $this->getViewModelValue($mainViewName, 'idremesa');

        switch ($viewName) {
            case 'ListReciboClienteIBAN':
                $where = [new DataBaseWhere('idremesa', $idremesa)];
                $view->loadData('', $where);
                break;

            case 'ListReciboClienteIBAN-add':
                $coddivisa = $this->getViewModelValue($mainViewName, 'coddivisa')
                    ?? Tools::settings('default', 'coddivisa');
                $where = [
                    new DataBaseWhere('idremesa', null, 'IS'),
                    new DataBaseWhere('pagado', false),
                    new DataBaseWhere('coddivisa', $coddivisa),
                    new DataBaseWhere('idempresa', $this->getViewModelValue($mainViewName, 'idempresa'))
                ];
                $view->loadData('', $where);
                break;

            case $mainViewName:
                parent::loadData($viewName, $view);
                $this->loadDataRemesaSEPA($viewName, $view);
                break;
        }
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadDataRemesaSEPA(string $viewName, BaseView $view): void
    {
        // hide company field
        if ($this->empresa->count() < 2) {
            $view->disableColumn('company');
        }

        // lock some fields
        switch ($view->model->estado) {
            default:
                $view->model->updateTotal();
                if ($view->model->total > 0) {
                    // add ready button
                    $this->addButton($viewName, [
                        'action' => 'ready',
                        'color' => 'info',
                        'icon' => 'fas fa-check',
                        'label' => 'ready'
                    ]);
                }
                // remove receipts action button
                $this->addButton('ListReciboClienteIBAN', [
                    'action' => 'remove-receipts',
                    'color' => 'danger',
                    'confirm' => true,
                    'icon' => 'fas fa-folder-minus',
                    'label' => 'remove-from-list'
                ]);
                // add action button
                $this->addButton('ListReciboClienteIBAN-add', [
                    'action' => 'add-receipts',
                    'color' => 'success',
                    'icon' => 'fas fa-folder-plus',
                    'label' => 'add'
                ]);
                break;

            case RemesaSEPA::STATUS_WAIT:
                $view->disableColumn('date', false, 'true');
                $view->disableColumn('description', false, 'true');
                $view->disableColumn('company', $this->empresa->count() < 2, 'true');
                $view->disableColumn('bank-account', false, 'true');
                $view->model->updateTotal();
                if ($view->model->total > 0) {
                    // add download button
                    $this->addButton($viewName, [
                        'action' => 'download',
                        'color' => 'info',
                        'icon' => 'fas fa-download',
                        'label' => 'download'
                    ]);

                    // add sent button
                    $this->addButton($viewName, [
                        'action' => 'sent',
                        'color' => 'info',
                        'icon' => 'fas fa-calendar-check',
                        'label' => 'remittance-sent'
                    ]);
                }
                break;

            case RemesaSEPA::STATUS_REVIEW:
                // add paid button
                $this->addButton($viewName, [
                    'action' => 'charged',
                    'color' => 'info',
                    'icon' => 'fas fa-check-square',
                    'label' => 'remittance-charged'
                ]);
            // no break

            case RemesaSEPA::STATUS_DONE:
                // add rectify button
                $this->addButton($viewName, [
                    'action' => 'rectify',
                    'color' => 'warning',
                    'icon' => 'fas fa-edit',
                    'label' => 'rectify'
                ]);
                $view->disableColumn('date', false, 'true');
                $view->disableColumn('description', false, 'true');
                $view->disableColumn('name', false, 'true');
                $view->disableColumn('company', $this->empresa->count() < 2, 'true');
                $view->disableColumn('bank-account', false, 'true');
                $view->disableColumn('creditor-id', false, 'true');
                $view->disableColumn('charge-date', false, 'true');
                $view->disableColumn('type', false, 'true');
                $view->disableColumn('group-receipts-by-customer', false, 'true');
                $this->setSettings('ListReciboClienteIBAN-add', 'active', false);
                break;
        }

        // si la remesa está completada y no tiene asiento, añadimos el botón para crearlo
        if ($view->model->estado == RemesaSEPA::STATUS_DONE && empty($view->model->idasiento)) {
            $this->addButton($viewName, [
                'action' => 'generate-accounting',
                'color' => 'info',
                'icon' => 'fas fa-magic',
                'label' => 'generate-accounting-entry'
            ]);
        }
    }

    protected function readyAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }

        $remesa = $this->getRemittance();
        $remesa->estado = RemesaSEPA::STATUS_WAIT;
        $remesa->save();

        Tools::log()->notice('remittance-ready-to-download');
        return true;
    }

    protected function rectifyAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }

        $remesa = $this->getRemittance();
        $remesa->estado = RemesaSEPA::STATUS_NEW;
        $remesa->save();

        Tools::log()->notice('remittance-ready-to-download');
        return true;
    }

    protected function removeReceiptsAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }

        $codes = $this->request->request->get('code', []);
        if (false === is_array($codes)) {
            return true;
        }

        $num = 0;
        foreach ($codes as $code) {
            $receipt = new ReciboCliente();
            if ($receipt->loadFromCode($code)) {
                $receipt->idremesa = null;
                $receipt->pagado = false;
                $receipt->nick = $this->user->nick;
                if ($receipt->save()) {
                    $num++;
                }
            }
        }

        $this->getRemittance()->updateTotal();
        Tools::log()->notice('items-removed-correctly', ['%num%' => $num]);
        return true;
    }

    protected function sentAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }

        $remesa = $this->getRemittance();
        $remesa->estado = RemesaSEPA::STATUS_REVIEW;
        $remesa->save();

        Tools::log()->notice('remittance-ready-to-download');
        return true;
    }
}
