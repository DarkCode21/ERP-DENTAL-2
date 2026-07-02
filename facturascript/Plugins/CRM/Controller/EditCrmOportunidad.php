<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CRM\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Dinamic\Model\PresupuestoCliente;

/**
 * Description of EditCrmOportunidad
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditCrmOportunidad extends EditController
{

    public function getModelClassName(): string
    {
        return 'CrmOportunidad';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'crm';
        $data['title'] = 'oportunity';
        $data['icon'] = 'fas fa-trophy';
        return $data;
    }

    protected function createEstimationAction()
    {
        $mainView = $this->getMainViewName();
        $contact = $this->views[$mainView]->model->getContacto();
        if (false === $contact->exists()) {
            $this->toolBox()->i18nLog()->error('contact-not-found');
            return;
        }

        $customer = $contact->getCustomer();
        if (false === $customer->exists()) {
            $this->toolBox()->i18nLog()->error('customer-not-found');
            return;
        }

        $presupuesto = new PresupuestoCliente();
        $presupuesto->setSubject($customer);
        $presupuesto->codagente = $this->views[$mainView]->model->codagente;
        if (false === $presupuesto->save()) {
            $this->toolBox()->i18nLog()->error('record-save-error');
            return;
        }

        $this->views[$mainView]->model->coddivisa = $presupuesto->coddivisa;
        $this->views[$mainView]->model->idpresupuesto = $presupuesto->primaryColumnValue();
        $this->views[$mainView]->model->neto = $presupuesto->neto;
        $this->views[$mainView]->model->netoeuros = empty($presupuesto->tasaconv) ? 0 : round($presupuesto->neto / $presupuesto->tasaconv, 5);
        $this->views[$mainView]->model->tasaconv = $presupuesto->tasaconv;
        $this->views[$mainView]->model->save();

        $this->redirect($presupuesto->url());
    }

    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        $this->createViewEstimations();
        $this->createViewNotes();
    }

    protected function createViewEstimations(string $viewName = 'ListPresupuestoCliente')
    {
        $this->addListView($viewName, 'PresupuestoCliente', 'estimations', 'fas fa-copy');

        // disable buttons
        $this->setSettings($viewName, 'checkBoxes', false);
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);
    }

    protected function createViewNotes(string $viewName = 'EditCrmNota')
    {
        $this->addEditListView($viewName, 'CrmNota', 'notes', 'fas fa-sticky-note');

        // disable columns
        $this->views[$viewName]->disableColumn('contact');
        $this->views[$viewName]->disableColumn('oportunity');
    }

    /**
     * @param string $action
     */
    protected function execAfterAction($action)
    {
        if ($action == 'create-estimation') {
            return $this->createEstimationAction();
        }

        parent::execAfterAction($action);
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case $this->getMainViewName():
                parent::loadData($viewName, $view);
                // set user nick
                if (false === $view->model->exists()) {
                    $view->model->nick = $this->user->nick;
                }

                // disable columns if not editable
                if (false === $view->model->editable) {
                    $view->disableColumn('agent', false, 'true');
                    $view->disableColumn('contact', false, 'true');
                    $view->disableColumn('description', false, 'true');
                    $view->disableColumn('interest', false, 'true');
                    $view->disableColumn('observations', false, 'true');
                }
                break;

            case 'EditCrmNota':
                $idoportunidad = $this->getViewModelValue($this->getMainViewName(), 'id');
                $where = [new DataBaseWhere('idoportunidad', $idoportunidad)];
                $view->loadData('', $where, ['fecha' => 'DESC', 'id' => 'DESC']);
                // set user nick
                if (false === $view->model->exists()) {
                    $view->model->idcontacto = $this->getViewModelValue($this->getMainViewName(), 'idcontacto');
                    $view->model->idinteres = $this->getViewModelValue($this->getMainViewName(), 'idinteres');
                    $view->model->nick = $this->user->nick;
                }
                break;

            case 'ListPresupuestoCliente':
                $idpresupuesto = $this->getViewModelValue($this->getMainViewName(), 'idpresupuesto');
                if (empty($idpresupuesto)) {
                    $this->addButton($viewName, [
                        'action' => 'create-estimation',
                        'color' => 'success',
                        'icon' => 'fas fa-plus',
                        'label' => 'create-estimation'
                    ]);
                    break;
                }

                $where = [new DataBaseWhere('idpresupuesto', $idpresupuesto)];
                $view->loadData('', $where);
                break;
        }
    }
}
