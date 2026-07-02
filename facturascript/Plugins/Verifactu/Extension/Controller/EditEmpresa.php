<?php

/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\Certificate;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditEmpresa
{
    public function createViews(): Closure
    {
        return function () {
            $this->addListView('ListVerifactuRequerimiento', 'VerifactuRequerimiento', 'requirements', 'fa-solid fa-gavel')
                ->addSearchFields(['referencia'])
                ->addOrderBy(['creation_date'], 'creation-date', 2)
                ->disableColumn('company');
        };
    }

    public function execPreviousAction(): Closure
    {
        return function ($action) {
            $company = $this->getModel();

            switch ($action) {
                case 'vf-new-certificate':
                    if ($company->loadFromCode($this->request->request->get('code'))) {
                        $this->verifactuNewCertificateAction($company);
                    }
                    break;

                case 'insert':
                    if ($company->loadFromCode($this->views[$this->active]->newCode)) {
                        Certificate::setCertificateMyFiles($company);
                    }
                    break;

                case 'edit':
                    if ($company->loadFromCode($this->request->request->get('code'))) {
                        Certificate::setCertificateMyFiles($company);
                    }
                    break;
            }
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            $mvn = $this->getMainViewName();
            switch ($viewName) {
                case $mvn:
                    // si hay empresa y tiene certificado, añadimos el botón para cambiar el certificado
                    if ($view->model->exists() && false === empty($view->model->vf_certificate)) {
                        $this->addButton($viewName, [
                            'action' => 'vf-new-certificate',
                            'color' => 'warning',
                            'icon' => 'fa-solid fa-file-upload',
                            'label' => 'verifactu-change-certificate',
                            'type' => 'modal'
                        ]);
                    }

                    // si no hay empresa o no está configurada, desactivamos la vista de requerimientos
                    if (!$view->model->exists() || !$view->model->verifactuIsConfigured(false)) {
                        $this->setSettings('ListVerifactuRequerimiento', 'active', false);
                    }
                    break;

                case 'ListVerifactuRequerimiento':
                    $where = [new DataBaseWhere('idempresa', $this->getViewModelValue($mvn, 'idempresa'))];
                    $view->loadData('', $where);
                    break;
            }
        };
    }

    protected function verifactuNewCertificateAction(): Closure
    {
        return function (Empresa $company) {
            if (false === $this->validateFormToken()) {
                return;
            }

            $company->vf_password = $this->request->request->get('vf_new_password', '');
            $uploadFile = $this->request->files->get('vf_new_certificate');
            if (false === Certificate::setCertificateModal($company, $uploadFile)) {
                Tools::log()->warning('record-save-error');
                return;
            }

            Tools::log()->notice('record-updated-correctly');
        };
    }
}
