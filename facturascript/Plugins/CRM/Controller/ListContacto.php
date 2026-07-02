<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CRM\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Dinamic\Model\CodeModel;
use FacturaScripts\Plugins\CRM\Lib\Import\ContactImport;

/**
 * Description of ListContacto
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ListContacto extends ListController
{

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'crm';
        $data['title'] = 'contacts';
        $data['icon'] = 'fas fa-users';
        return $data;
    }

    protected function createViews()
    {
        $this->createViewContacts();
        $this->createViewSources();
        $this->createViewInterests();
        $this->createViewLists();
    }

    protected function createViewContacts(string $viewName = 'ListCrmContacto')
    {
        $this->addView($viewName, 'Contacto', 'contacts', 'fas fa-users');
        $this->addSearchFields($viewName, ['nombre', 'apellidos', 'email', 'empresa', 'observaciones', 'telefono1', 'telefono2', 'lastip']);
        $this->addOrderBy($viewName, ['email'], 'email');
        $this->addOrderBy($viewName, ['nombre'], 'name');
        $this->addOrderBy($viewName, ['empresa'], 'company');
        $this->addOrderBy($viewName, ['puntos'], 'points');
        $this->addOrderBy($viewName, ['fechaalta'], 'creation-date', 2);

        // filters
        $this->createViewContactsFilters($viewName);

        // buttons
        $this->addButton($viewName, [
            'action' => 'import-contacts',
            'icon' => 'fas fa-file-import',
            'label' => 'import-contacts',
            'type' => 'modal'
        ]);
    }

    protected function createViewContactsFilters(string $viewName)
    {
        $i18n = $this->toolBox()->i18n();
        $values = [
            ['label' => $i18n->trans('all'), 'where' => []],
            ['label' => $i18n->trans('customers'), 'where' => [new DataBaseWhere('codcliente', null, 'IS NOT')]],
            ['label' => $i18n->trans('not-customers'), 'where' => [new DataBaseWhere('codcliente', null, 'IS')]]
        ];
        $this->addFilterSelectWhere($viewName, 'status', $values);

        $agentes = $this->codeModel->all('agentes', 'codagente', 'nombre');
        $this->addFilterSelect($viewName, 'codagente', 'agent', 'codagente', $agentes);

        $fuentes = $this->codeModel->all('crm_fuentes2', 'id', 'nombre');
        $this->addFilterSelect($viewName, 'idfuente', 'source', 'idfuente', $fuentes);

        $countries = $this->codeModel->all('paises', 'codpais', 'nombre');
        $this->addFilterSelect($viewName, 'codpais', 'country', 'codpais', $countries);

        $provinces = $this->codeModel->all('contactos', 'provincia', 'provincia');
        if (count($provinces) >= CodeModel::ALL_LIMIT) {
            $this->addFilterAutocomplete($viewName, 'provincia', 'province', 'provincia', 'contactos', 'provincia');
        } else {
            $this->addFilterSelect($viewName, 'provincia', 'province', 'provincia', $provinces);
        }

        $cities = $this->codeModel->all('contactos', 'ciudad', 'ciudad');
        if (count($cities) >= CodeModel::ALL_LIMIT) {
            $this->addFilterAutocomplete($viewName, 'ciudad', 'city', 'ciudad', 'contactos', 'ciudad');
        } else {
            $this->addFilterSelect($viewName, 'ciudad', 'city', 'ciudad', $cities);
        }

        $cargoValues = $this->codeModel->all('contactos', 'cargo', 'cargo');
        $this->addFilterSelect($viewName, 'cargo', 'position', 'cargo', $cargoValues);

        $this->addFilterCheckbox($viewName, 'verificado', 'verified', 'verificado');
        $this->addFilterCheckbox($viewName, 'admitemarketing', 'allow-marketing', 'admitemarketing');
    }

    protected function createViewInterests(string $viewName = 'ListCrmInteres')
    {
        $this->addView($viewName, 'CrmInteres', 'interests', ' fas fa-heart');
        $this->addOrderBy($viewName, ['nombre'], 'name');
        $this->addOrderBy($viewName, ['numcontactos'], 'contacts');
        $this->addOrderBy($viewName, ['descripcion'], 'description');
        $this->addSearchFields($viewName, ['nombre', 'descripcion']);
    }

    protected function createViewLists(string $viewName = 'ListCrmLista')
    {
        $this->addView($viewName, 'CrmLista', 'lists', ' fas fa-notes-medical');
        $this->addOrderBy($viewName, ['nombre'], 'name');
        $this->addOrderBy($viewName, ['numcontactos'], 'contacts');
        $this->addOrderBy($viewName, ['fecha'], 'date');
        $this->addSearchFields($viewName, ['nombre']);
    }

    protected function createViewSources(string $viewName = 'ListCrmFuente')
    {
        $this->addView($viewName, 'CrmFuente', 'sources', 'fas fa-file-import');
        $this->addOrderBy($viewName, ['nombre'], 'name');
        $this->addOrderBy($viewName, ['numcontactos'], 'contacts');
        $this->addOrderBy($viewName, ['descripcion'], 'description');
        $this->addSearchFields($viewName, ['nombre', 'descripcion']);
    }

    /**
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        if ($action === 'import-contacts') {
            $this->importContactsAction();
        }

        return parent::execPreviousAction($action);
    }

    protected function importContactsAction(): bool
    {
        $idfuente = $this->request->request->get('idfuente');
        $mode = $this->request->request->get('mode', ContactImport::INSERT_MODE);
        $uploadFile = $this->request->files->get('contactsfile');

        switch ($uploadFile->getMimeType()) {
            case 'application/octet-stream':
            case 'text/csv':
            case 'text/plain':
                $num = ContactImport::importCSV($uploadFile->getPathname(), $idfuente, $mode);
                $this->toolBox()->i18nLog()->notice('items-added-correctly', ['%num%' => $num]);
                break;

            default:
                $this->toolBox()->i18nLog()->error('file-not-supported');
        }

        return true;
    }
}
