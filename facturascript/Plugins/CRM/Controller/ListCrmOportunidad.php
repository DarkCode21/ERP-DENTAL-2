<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CRM\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Plugins\CRM\Model\CrmOportunidadEstado;

/**
 * Description of ListCrmOportunidad
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ListCrmOportunidad extends ListController
{

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'crm';
        $data['title'] = 'oportunities';
        $data['icon'] = 'fas fa-trophy';
        return $data;
    }

    protected function createViews()
    {
        $this->createViewOporunities();

        // add a new tab for every status
        $crmOpoEstado = new CrmOportunidadEstado();
        foreach ($crmOpoEstado->all([], ['orden' => 'ASC']) as $estado) {
            $viewName = 'ListCrmOportunidad-' . $estado->id;
            $this->createViewCustomOporunities($viewName, $estado->nombre, $estado->icon);
            $this->addFilterSelectWhere(
                $viewName,
                'idestado',
                [['label' => $estado->nombre, 'where' => [new DataBaseWhere('idestado', $estado->id)]]]
            );

            $this->setSettings($viewName, 'megasearch', false);
        }

        if ($this->user->admin) {
            $this->createViewStatus();
        }
    }

    protected function createViewCustomOporunities(string $viewName, string $label, string $icon)
    {
        $this->addView($viewName, 'CrmOportunidad', $label, $icon);
        $this->addOrderBy($viewName, ['fecha'], 'date');
        $this->addOrderBy($viewName, ['fechamod'], 'last-update', 2);
        $this->addOrderBy($viewName, ['neto'], 'net');
        $this->addSearchFields($viewName, ['descripcion', 'observaciones']);

        // filters
        $this->addFilterPeriod($viewName, 'fecha', 'date', 'fecha');

        $users = $this->codeModel->all('users', 'nick', 'nick');
        $this->addFilterSelect($viewName, 'nick', 'user', 'nick', $users);

        $interests = $this->codeModel->all('crm_intereses', 'id', 'nombre');
        $this->addFilterSelect($viewName, 'idinteres', 'interest', 'idinteres', $interests);
    }

    protected function createViewOporunities(string $viewName = 'ListCrmOportunidad')
    {
        $this->addView($viewName, 'CrmOportunidad', 'all', 'fas fa-trophy');
        $this->addOrderBy($viewName, ['fecha'], 'date');
        $this->addOrderBy($viewName, ['fechamod'], 'last-update', 2);
        $this->addOrderBy($viewName, ['neto'], 'net');
        $this->addSearchFields($viewName, ['descripcion', 'observaciones']);

        // filters
        $this->addFilterPeriod($viewName, 'fecha', 'date', 'fecha');

        $users = $this->codeModel->all('users', 'nick', 'nick');
        $this->addFilterSelect($viewName, 'nick', 'user', 'nick', $users);

        $agents = $this->codeModel->all('agentes', 'codagente', 'nombre');
        $this->addFilterSelect($viewName, 'codagente', 'agents', 'codagente', $agents);

        $interests = $this->codeModel->all('crm_intereses', 'id', 'nombre');
        $this->addFilterSelect($viewName, 'idinteres', 'interest', 'idinteres', $interests);
    }

    protected function createViewStatus(string $viewName = 'ListCrmOportunidadEstado')
    {
        $this->addView($viewName, 'CrmOportunidadEstado', 'states', 'fas fa-tags');
        $this->addOrderBy($viewName, ['nombre'], 'name');
        $this->addOrderBy($viewName, ['orden'], 'sort', 1);
        $this->addSearchFields($viewName, ['nombre']);

        $this->setSettings($viewName, 'megasearch', false);
    }
}
