<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CRM\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Plugins\CRM\Model\CrmInteresContacto;

/**
 * Description of EditCrmInteres
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditCrmInteres extends EditController
{

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'crm';
        $data['title'] = 'interest';
        $data['icon'] = 'fas fa-heart';
        return $data;
    }

    public function getModelClassName(): string
    {
        return 'CrmInteres';
    }

    protected function addContactAction()
    {
        $codes = $this->request->request->get('code', []);
        if (is_array($codes)) {
            $num = 0;
            foreach ($codes as $code) {
                $interesContacto = new CrmInteresContacto();
                $interesContacto->idcontacto = $code;
                $interesContacto->idinteres = $this->request->query->get('code');
                if ($interesContacto->save()) {
                    $num++;
                }
            }

            $this->toolBox()->i18nLog()->notice('items-added-correctly', ['%num%' => $num]);
        }
    }

    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        $this->createViewContacts();
        $this->createViewNewContacts();
        $this->createViewNotes();
        $this->createViewOportunities();

        // needed dependency
        new CrmInteresContacto();
    }

    protected function createViewCommon(string $viewName)
    {
        $this->views[$viewName]->addSearchFields(['nombre', 'apellidos', 'email', 'empresa', 'observaciones', 'telefono1', 'telefono2']);

        // filters
        $i18n = $this->toolBox()->i18n();
        $values = [
            ['label' => $i18n->trans('all'), 'where' => []],
            ['label' => $i18n->trans('customers'), 'where' => [new DataBaseWhere('codcliente', null, 'IS NOT')]],
            ['label' => $i18n->trans('not-customers'), 'where' => [new DataBaseWhere('codcliente', null, 'IS')]],
        ];
        $this->views[$viewName]->addFilterSelectWhere('status', $values);

        $agentes = $this->codeModel->all('agentes', 'codagente', 'nombre');
        $this->views[$viewName]->addFilterSelect('codagente', 'agent', 'codagente', $agentes);

        $fuentes = $this->codeModel->all('crm_fuentes2', 'id', 'nombre');
        $this->views[$viewName]->addFilterSelect('idfuente', 'source', 'idfuente', $fuentes);

        $countries = $this->codeModel->all('paises', 'codpais', 'nombre');
        $this->views[$viewName]->addFilterSelect('codpais', 'country', 'codpais', $countries);

        $provinces = $this->codeModel->all('contactos', 'provincia', 'provincia');
        $this->views[$viewName]->addFilterSelect('provincia', 'province', 'provincia', $provinces);

        $cities = $this->codeModel->all('contactos', 'ciudad', 'ciudad');
        $this->views[$viewName]->addFilterSelect('ciudad', 'city', 'ciudad', $cities);

        $cargoValues = $this->codeModel->all('contactos', 'cargo', 'cargo');
        $this->views[$viewName]->addFilterSelect('cargo', 'position', 'cargo', $cargoValues);

        $this->views[$viewName]->addFilterCheckbox('verificado', 'verified', 'verificado');
        $this->views[$viewName]->addFilterCheckbox('admitemarketing', 'allow-marketing', 'admitemarketing');

        // disable buttons
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'btnDelete', false);
    }

    protected function createViewContacts(string $viewName = 'ListCrmContacto')
    {
        $this->addListView($viewName, 'Contacto', 'contacts', 'fas fa-users');
        $this->views[$viewName]->addOrderBy(['email'], 'email');
        $this->views[$viewName]->addOrderBy(['empresa'], 'company');
        $this->views[$viewName]->addOrderBy(['fechaalta'], 'creation-date');
        $this->views[$viewName]->addOrderBy(['nombre'], 'name', 1);
        $this->createViewCommon($viewName);

        // add action button
        $newButton = [
            'action' => 'remove-contact',
            'color' => 'danger',
            'confirm' => true,
            'icon' => 'fas fa-user-minus',
            'label' => 'remove-from-list',
        ];
        $this->addButton($viewName, $newButton);
    }

    protected function createViewNewContacts(string $viewName = 'ListCrmContacto-new')
    {
        $this->addListView($viewName, 'Contacto', 'add', 'fas fa-user-plus');
        $this->views[$viewName]->addOrderBy(['email'], 'email');
        $this->views[$viewName]->addOrderBy(['empresa'], 'company');
        $this->views[$viewName]->addOrderBy(['fechaalta'], 'creation-date');
        $this->views[$viewName]->addOrderBy(['nombre'], 'name', 1);
        $this->createViewCommon($viewName);

        // add action button
        $newButton = [
            'action' => 'add-contact',
            'color' => 'success',
            'icon' => 'fas fa-user-plus',
            'label' => 'add',
        ];
        $this->addButton($viewName, $newButton);
    }

    protected function createViewNotes(string $viewName = 'ListCrmNota')
    {
        $this->addListView($viewName, 'CrmNota', 'notes', 'far fa-sticky-note');
        $this->views[$viewName]->addOrderBy(['fecha'], 'date', 2);
        $this->views[$viewName]->addOrderBy(['fechaaviso'], 'notice-date');
        $this->views[$viewName]->searchFields = ['observaciones'];

        // disable column
        $this->views[$viewName]->disableColumn('interest');
    }

    protected function createViewOportunities(string $viewName = 'ListCrmOportunidad')
    {
        $this->addListView($viewName, 'CrmOportunidad', 'oportunities', 'fas fa-trophy');
        $this->views[$viewName]->addOrderBy(['fecha'], 'date', 2);
        $this->views[$viewName]->addOrderBy(['neto'], 'net');
        $this->views[$viewName]->searchFields = ['descripcion', 'observaciones'];

        // disable column
        $this->views[$viewName]->disableColumn('interest');
    }

    /**
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'add-contact':
                $this->addContactAction();
                return true;

            case 'remove-contact':
                $this->removeContactAction();
                return true;

            default:
                return parent::execPreviousAction($action);
        }
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $mainViewName = $this->getMainViewName();
        $idinterest = $this->getViewModelValue($mainViewName, 'id');
        $sqlIn = 'select idcontacto from crm_intereses_contactos where idinteres = ' . $this->dataBase->var2str($idinterest);

        switch ($viewName) {
            case 'ListCrmContacto':
                $where = [new DataBaseWhere('idcontacto', $sqlIn, 'IN')];
                $view->loadData('', $where);
                break;

            case 'ListCrmContacto-new':
                $where = [new DataBaseWhere('idcontacto', $sqlIn, 'NOT IN')];
                $view->loadData('', $where);
                break;

            case 'ListCrmNota':
            case 'ListCrmOportunidad':
                $where = [new DataBaseWhere('idinteres', $idinterest)];
                $view->loadData('', $where);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    protected function removeContactAction()
    {
        $codes = $this->request->request->get('code', []);
        if (is_array($codes)) {
            $num = 0;
            foreach ($codes as $code) {
                $interesContacto = new CrmInteresContacto();
                $where = [
                    new DataBaseWhere('idinteres', $this->request->query->get('code')),
                    new DataBaseWhere('idcontacto', $code)
                ];
                if ($interesContacto->loadFromCode('', $where) && $interesContacto->delete()) {
                    $num++;
                }
            }

            $this->toolBox()->i18nLog()->notice('items-removed-correctly', ['%num%' => $num]);
        }
    }
}
