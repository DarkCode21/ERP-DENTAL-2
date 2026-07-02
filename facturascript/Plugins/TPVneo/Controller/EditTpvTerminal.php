<?php
/**
 * Copyright (C) 2022-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TPVneo\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Agentes;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Plugins\Tickets\Model\TicketPrinter;
use FacturaScripts\Plugins\TPVneo\Lib\Tickets\BoxClosure;
use FacturaScripts\Plugins\TPVneo\Lib\TPVneo\SaleTicket;
use FacturaScripts\Plugins\TPVneo\Model\TpvCaja;
use FacturaScripts\Plugins\TPVneo\Model\TpvTerminal;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditTpvTerminal extends EditController
{
    public function getModelClassName(): string
    {
        return 'TpvTerminal';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data["title"] = "pos-terminal";
        $data["icon"] = "fas fa-cash-register";
        return $data;
    }

    protected function closeBoxAction(): void
    {
        if (false === $this->validateFormToken()) {
            return;
        }

        $codes = $this->request->request->get('code', []);
        if (empty($codes)) {
            $this->toolBox()->i18nLog()->warning('no-selected-item');
            return;
        }

        foreach ($codes as $code) {
            $caja = new TpvCaja();
            if (false === $caja->loadFromCode($code) || $caja->fechafin !== null) {
                continue;
            }

            $caja->close(0);
            if (false === $caja->save()) {
                $this->toolBox()->i18nLog()->warning('record-save-error');
                return;
            }

            $this->toolBox()->i18nLog()->notice('record-updated-correctly');
        }
    }

    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        $this->createViewsBoxes();
        $this->createViewsDocs();
        $this->createViewsBudgets();
        $this->createViewsAgents();
        $this->createViewsPayments();
    }

    protected function createViewsAgents(string $viewName = 'EditTpvAgente')
    {
        $this->addEditListView($viewName, 'TpvAgente', 'agents', 'fas fa-user-tie');
    }

    protected function createViewsBoxes(string $viewName = 'ListTpvCaja')
    {
        $this->addListView($viewName, 'TpvCaja', 'boxes', 'fas fa-box');
        $this->views[$viewName]->addSearchFields(['observaciones']);
        $this->views[$viewName]->addOrderBy(['idcaja'], 'code', 2);
        $this->views[$viewName]->addOrderBy(['fechaini'], 'start-date');
        $this->views[$viewName]->addOrderBy(['fechafin'], 'end-date');

        // filtros
        $this->views[$viewName]->addFilterPeriod('fechaini', 'start-date', 'fechaini');
        $this->views[$viewName]->addFilterPeriod('fechafin', 'end-date', 'fechafin');
        $this->views[$viewName]->addFilterNumber('income', 'income', 'ingresos', '>=');
        $this->views[$viewName]->addFilterCheckbox('box', 'opened', 'fechafin', 'IS', null);

        // desactivamos botones nuevo y eliminar
        $this->setSettings($viewName, 'clickable', false);
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'btnDelete', false);

        $this->addButton($viewName, [
            'action' => 'print-box-closure',
            'color' => 'light',
            'icon' => 'fas fa-print',
            'label' => 'print-box-closure',
            'type' => 'action'
        ]);

        // si es administrador, añadimos el botón cerrar caja
        if ($this->user->admin) {
            $this->addButton($viewName, [
                'action' => 'close-box',
                'color' => 'warning',
                'confirm' => true,
                'icon' => 'fas fa-lock',
                'label' => 'close-box'
            ]);
        }
    }

    protected function createViewsBudgets(string $viewName = 'ListTpvPresupuesto')
    {
        $this->addListView($viewName, 'PresupuestoCliente', 'estimations', 'far fa-file-powerpoint');
        $this->setSettings($viewName, 'btnNew', false);
        $this->optionsFilters($viewName);
    }

    protected function createViewsDocs(string $viewName = 'ListTpvDoc')
    {
        $tpv = new TpvTerminal();
        $tpv->loadFromCode($this->request->query->get('code', ''));
        $title = $tpv->doctype === 'FacturaCliente' ? 'invoices' : 'delivery-notes';
        $icon = $tpv->doctype === 'FacturaCliente' ? 'fas fa-file-invoice-dollar fa-fw' : 'fas fa-dolly-flatbed';
        $this->addListView($viewName, $tpv->doctype, $title, $icon);
        $this->setSettings($viewName, 'btnNew', false);
        $this->optionsFilters($viewName);
    }

    protected function createViewsPayments(string $viewName = 'EditTpvPago')
    {
        $this->addEditListView($viewName, 'TpvPago', 'payment-methods', 'fas fa-credit-card');
    }

    protected function fastTpvConfigAction()
    {
        // si ya hay terminales, no hacemos nada
        $terminal = new TpvTerminal();
        if ($terminal->count() > 0) {
            return;
        }

        // si no hay clientes, creamos el cliente contado
        $cliente = new Cliente();
        if ($cliente->count() === 0) {
            $cliente->cifnif = '00000000-A';
            $cliente->nombre = 'Contado';
            $cliente->save();
        } else {
            foreach ($cliente->all() as $cli) {
                // seleccionamos el primero que encontremos
                $cliente = $cli;
                break;
            }
        }

        // si no hay una impresora, creamos una
        $impresora = new TicketPrinter();
        if ($impresora->count() === 0) {
            $impresora->name = 'TPV';
            $impresora->nick = $this->user->nick;
            $impresora->save();
        } else {
            foreach ($impresora->all() as $imp) {
                // seleccionamos la primera que encontremos
                $impresora = $imp;
                break;
            }
        }

        // creamos la terminal
        $terminal->codcliente = $cliente->codcliente;
        $terminal->idprinter = $impresora->id;
        $terminal->name = 'Terminal 1';
        $terminal->ticketformat = 'Normal';
        if ($terminal->save()) {
            $this->redirect('TPVneo');
            return;
        }

        $this->toolBox()->i18nLog()->warning('tpv-terminal-not-created');
    }

    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'close-box':
                $this->closeBoxAction();
                break;

            case 'fast-config':
                $this->fastTpvConfigAction();
                break;

            case 'print-box-closure':
                $this->printBoxClosureAction();
                break;

            default:
                parent::execPreviousAction($action);
        }
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'EditTpvAgente':
            case 'EditTpvPago':
            case 'ListTpvCaja':
            case 'ListTpvPresupuesto':
            case 'ListTpvDoc':
                $where = [new DataBaseWhere('idtpv', $this->request->query->get('code'))];
                $view->loadData('', $where);
                break;

            default:
                parent::loadData($viewName, $view);
                $this->loadTicketFormats($view);
                break;
        }
    }

    protected function loadTicketFormats(BaseView $view): void
    {
        $column = $view->columnForName('ticket-format');
        if ($column && $column->widget->getType() === 'select') {
            $customValues = [];
            foreach (SaleTicket::loadFormats() as $fileName) {
                $customValues[] = [
                    'value' => $fileName,
                    'title' => self::toolBox()::i18n()->trans(strtolower($fileName))
                ];
            }
            $column->widget->setValuesFromArray($customValues, false, false);
        }
    }

    protected function optionsFilters($viewName)
    {
        $this->views[$viewName]->addSearchFields(['codigo']);
        $this->views[$viewName]->addOrderBy(['fecha', 'hora'], 'date', 2);
        $this->views[$viewName]->addOrderBy(['codigo'], 'code');
        $this->views[$viewName]->addOrderBy(['codagente'], 'agent');
        $this->views[$viewName]->addOrderBy(['nombrecliente'], 'customer');
        $this->views[$viewName]->addOrderBy(['finoferta'], 'expiration');
        $this->views[$viewName]->addOrderBy(['pvptotal'], 'total');

        // filters
        $this->views[$viewName]->addFilterPeriod('date', 'date', 'fecha');
        $this->views[$viewName]->addFilterNumber('min-total', 'total', 'total', '>=');
        $this->views[$viewName]->addFilterNumber('max-total', 'total', 'total', '<=');

        $agents = Agentes::codeModel();
        $this->views[$viewName]->addFilterSelect('codagente', 'agent', 'codagente', $agents);
    }

    protected function printBoxClosureAction()
    {
        if (false === $this->validateFormToken()) {
            return;
        }

        $terminal = $this->getModel();
        if (false === $terminal->loadFromCode($this->request->query->get('code', ''))
            || empty($terminal->idprinter)) {
            return;
        }

        $codes = $this->request->request->get('code', []);
        if (false === is_array($codes)) {
            return;
        }
        if (empty($codes)) {
            $this->toolBox()->i18nLog()->warning('no-selected-item');
            return;
        }

        foreach ($codes as $code) {
            $box = new TpvCaja();
            if ($box->loadFromCode($code)) {
                BoxClosure::print($box);
            }
        }
    }
}