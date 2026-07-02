<?php
/**
 * Copyright (C) 2022-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Ticketbai\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\Ticketbai\Lib\TbaiSignature;

/**
 * @author Carlos Garcia Gomez                  <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez             <hola@danielfg.es>
 * @author Alayn Gortazar Huete - Barnetik Koop <alayn@barnetik.com>
 */
class EditEmpresa
{
    public function execPreviousAction(): Closure
    {
        return function ($action) {
            // obtenemos la empresa
            $companyModel = $this->getModel();
            if (false === $companyModel->loadFromCode($this->request->request->get('code'))) {
                return;
            }

            switch ($action) {
                case 'tbai-new-file':
                    $this->uploadNewFileAction($companyModel);
                    break;

                default:
                    // llamamos a obtener el archivo para que ejecute las comprobaciones pertinentes
                    TbaiSignature::getSignatureFile($companyModel);
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
                    if ($view->model->exists() && false === empty($view->model->tbai_signature)) {
                        $this->addButton($viewName, [
                            'action' => 'tbai-new-file',
                            'color' => 'warning',
                            'icon' => 'fa-solid fa-file-upload',
                            'label' => 'change-certificate',
                            'type' => 'modal'
                        ]);
                    }
                    break;

                case 'ListIaeEmpresa':
                    $where = [new DataBaseWhere('idempresa', $this->getViewModelValue($mvn, 'idempresa'))];
                    $view->loadData('', $where);
                    break;
            }
        };
    }

    protected function uploadNewFileAction(): Closure
    {
        return function (Empresa $companyModel) {
            $uploadFile = $this->request->files->get('newfile');
            if (false === TbaiSignature::setSignature($companyModel, $uploadFile)) {
                return;
            }

            Tools::log()->notice('record-updated-correctly');
        };
    }

    protected function createViews(): Closure
    {
        return function () {
            $this->addEditListView('ListIaeEmpresa', 'IaeEmpresa', 'codes-iae', 'fa-solid fa-wallet')
                ->setInline(true);
        };
    }
}
