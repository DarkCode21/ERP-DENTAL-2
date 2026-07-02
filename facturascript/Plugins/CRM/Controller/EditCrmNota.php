<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CRM\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Plugins\CRM\Model\CrmOportunidad;

/**
 * Description of EditCrmNota
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditCrmNota extends EditController
{

    public function getModelClassName(): string
    {
        return 'CrmNota';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'crm';
        $data['title'] = 'note';
        $data['icon'] = 'far fa-sticky-note';
        return $data;
    }

    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');
        $this->createViewEstimations();
    }

    protected function createViewEstimations(string $viewName = 'ListPresupuestoCliente')
    {
        $this->addListView($viewName, 'PresupuestoCliente', 'estimations', 'fas fa-copy');

        // disable buttons
        $this->setSettings($viewName, 'checkBoxes', false);
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $mvn = $this->getMainViewName();

        switch ($viewName) {
            case 'ListPresupuestoCliente':
                $oportunity = new CrmOportunidad();
                $idoportunidad = $this->views[$mvn]->model->idoportunidad;
                if ($this->views[$mvn]->model->tipodocumento === 'presupuesto de cliente') {
                    $where = [new DataBaseWhere('codigo', $this->views[$mvn]->model->documento)];
                    $view->loadData('', $where);
                } elseif (empty($idoportunidad) || false === $oportunity->loadFromCode($idoportunidad)) {
                    break;
                } elseif ($oportunity->idpresupuesto) {
                    $where = [new DataBaseWhere('idpresupuesto', $oportunity->idpresupuesto)];
                    $view->loadData('', $where);
                }
                break;

            case $mvn:
                parent::loadData($viewName, $view);
                if (false === $view->model->exists()) {
                    $view->model->nick = $this->user->nick;
                }
                break;
        }
    }
}
