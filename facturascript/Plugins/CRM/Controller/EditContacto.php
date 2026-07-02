<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CRM\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Controller\EditContacto as ParentController;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Plugins\CRM\Model\CrmInteres;
use FacturaScripts\Plugins\CRM\Model\CrmLista;

/**
 * Description of EditContacto
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditContacto extends ParentController
{

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'crm';
        return $data;
    }

    protected function createViews()
    {
        parent::createViews();
        $this->createViewCrmNotes();

        // show interest tab only if there are interests
        $interest = new CrmInteres();
        if ($interest->count() > 0) {
            $this->createViewCrmInterests();
        }

        // show lists tab only if there are lists
        $list = new CrmLista();
        if ($list->count() > 0) {
            $this->createViewCrmLists();
        }

        $this->createViewCrmOportunities();
    }

    protected function createViewCrmInterests(string $viewName = 'EditCrmInteresContacto')
    {
        $this->addEditListView($viewName, 'CrmInteresContacto', 'interests', 'fas fa-heart');
        $this->views[$viewName]->setInline(true);

        // disable column
        $this->views[$viewName]->disableColumn('contact');
    }

    protected function createViewCrmLists(string $viewName = 'EditCrmListaContacto')
    {
        $this->addEditListView($viewName, 'CrmListaContacto', 'lists', 'fas fa-notes-medical');
        $this->views[$viewName]->setInline(true);

        // disable column
        $this->views[$viewName]->disableColumn('contact');
    }

    protected function createViewCrmNotes(string $viewName = 'EditCrmNota')
    {
        $this->addEditListView($viewName, 'CrmNota', 'notes', 'far fa-sticky-note');

        // disable column
        $this->views[$viewName]->disableColumn('contact');
    }

    protected function createViewCrmOportunities(string $viewName = 'ListCrmOportunidad')
    {
        $this->addListView($viewName, 'CrmOportunidad', 'oportunities', 'fas fa-trophy');
        $this->views[$viewName]->addOrderBy(['fecha'], 'date', 2);
        $this->views[$viewName]->addOrderBy(['neto'], 'net');
        $this->views[$viewName]->searchFields = ['descripcion', 'observaciones'];

        // disable column
        $this->views[$viewName]->disableColumn('contact');
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $mainViewName = $this->getMainViewName();
        $idcontacto = $this->getViewModelValue($mainViewName, 'idcontacto');

        switch ($viewName) {
            case 'EditCrmInteresContacto':
            case 'EditCrmListaContacto':
            case 'ListCrmOportunidad':
                $where = [new DataBaseWhere('idcontacto', $idcontacto)];
                $view->loadData('', $where);
                break;

            case 'EditCrmNota':
                $where = [new DataBaseWhere('idcontacto', $idcontacto)];
                $view->loadData('', $where, ['fecha' => 'DESC']);
                $this->views[$viewName]->model->nick = $this->user->nick;
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}
