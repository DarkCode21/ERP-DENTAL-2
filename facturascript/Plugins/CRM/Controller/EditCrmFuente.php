<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CRM\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * Description of EditCrmFuente
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditCrmFuente extends EditController
{

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'crm';
        $data['title'] = 'source';
        $data['icon'] = 'fas fa-file-import';
        return $data;
    }

    public function getModelClassName(): string
    {
        return 'CrmFuente';
    }

    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        $viewName = 'ListCrmContacto';
        $this->addListView($viewName, 'Contacto', 'contacts', 'fas fa-users');
        $this->views[$viewName]->searchFields = ['nombre', 'apellidos', 'email', 'empresa', 'observaciones', 'telefono1', 'telefono2', 'lastip'];
        $this->views[$viewName]->addOrderBy(['email'], 'email');
        $this->views[$viewName]->addOrderBy(['nombre'], 'name');
        $this->views[$viewName]->addOrderBy(['empresa'], 'company');
        $this->views[$viewName]->addOrderBy(['level'], 'level');
        $this->views[$viewName]->addOrderBy(['puntos'], 'points');
        $this->views[$viewName]->addOrderBy(['lastactivity'], 'last-activity', 2);

        // disable buttons
        $this->setSettings($viewName, 'btnDelete', false);
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $mainViewName = $this->getMainViewName();
        $idfuente = $this->getViewModelValue($mainViewName, 'id');

        switch ($viewName) {
            case 'ListCrmContacto':
                $where = [new DataBaseWhere('idfuente', $idfuente)];
                $view->loadData('', $where);
                break;

            default:
                parent::loadData($viewName, $view);
        }
    }
}
