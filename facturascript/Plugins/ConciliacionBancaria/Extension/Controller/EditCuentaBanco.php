<?php
/**
 * Copyright (C) 2023-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\ConciliacionBancaria\Extension\Controller;

use Closure;
use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\CsvFileTools;
use FacturaScripts\Dinamic\Model\CSVfile;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditCuentaBanco
{
    public function createViews(): Closure
    {
        return function () {
            $this->createViewsMovimientoBanco();
        };
    }

    protected function createViewsMovimientoBanco(): Closure
    {
        return function (string $viewName = "ListMovimientoBanco") {
            $this->addListView($viewName, "MovimientoBanco", "banking-movements", "fas fa-list")
                ->addOrderBy(["date"], "date", 2)
                ->addSearchFields(["observations"])
                ->disableColumn('codcuenta', true)
                ->setSettings('btnNew', false);

            if ($this->permissions->allowImport) {
                $this->addButton($viewName, [
                    'action' => 'upload-banking-movements',
                    'icon' => 'fas fa-file-import',
                    'label' => 'import',
                    'type' => 'modal'
                ]);
            }
        };
    }

    public function execPreviousAction(): Closure
    {
        return function ($action) {
            switch ($action) {
                case 'import-banking-movements':
                    $this->importBankingMovementsAction();
                    break;

                case 'upload-banking-movements':
                    $this->uploadBankingMovementsAction();
                    break;
            }
        };
    }

    public function importBankingMovementsAction(): Closure
    {
        return function () {
            // obtenemos la ruta completa del archivo
            $fileName = $this->request->get('import-filename');
            $filePath = CsvFileTools::getFilePath($fileName);
            if (empty($filePath)) {
                return true;
            }

            // se ha elegido crea nueva plantilla
            $template = $this->request->get('import-template', CsvFileTools::NEW_TEMPLATE);
            if ($template === CsvFileTools::NEW_TEMPLATE) {
                $newCsvFile = CSVfile::newTemplate($fileName, 'banking-movements');
                $newCsvFile->mode = $this->request->get('import-mode');
                $newCsvFile->codcuenta = $this->request->get('codcuenta');
                if ($newCsvFile->save()) {
                    $this->redirect($newCsvFile->url());
                }
                return true;
            }

            // se ha elegido plantilla automática
            if ($template === CsvFileTools::AUTOMATIC && $this->importBankingMovementsAutoAction($filePath)) {
                return true;
            }

            // seleccionamos la plantilla
            $templateModel = $template === CsvFileTools::AUTOMATIC ?
                CsvFileTools::getFileTemplate($filePath) :
                CsvFileTools::getFileTemplate($filePath, $template, 'banking-movements');
            if (is_null($templateModel)) {
                Tools::log()->warning('template-not-found');

                // creamos una nueva plantilla
                $newCsvFile = CSVfile::newTemplate($fileName, 'banking-movements');
                $newCsvFile->mode = $this->request->get('import-mode');
                $newCsvFile->codcuenta = $this->request->get('codcuenta');
                if ($newCsvFile->save()) {
                    $this->redirect($newCsvFile->url(), 1);
                }
                return true;
            }

            // procesamos el archivo
            $templateModel->codcuenta = $this->request->get('codcuenta');
            $mode = $this->request->get('import-mode');
            $offset = (int)$this->request->get('import-offset', 0);
            $saveLines = (int)$this->request->get('save-lines', 0);
            $result = $templateModel->getProfile($offset, $saveLines, $filePath, $mode)->import();
            if ($result['offset'] > 0 && $result['offset'] < $result['total']) {
                Tools::log()->notice(
                    'items-save-correctly-to-total',
                    ['%lines%' => $result['offset'], '%total%' => $result['total'], '%save%' => $result['save']]
                );
                Tools::log()->notice('importing');
                $this->redirect($this->url() . '?action=import-banking-movements&import-filename=' . $fileName . '&import-offset='
                    . $result['offset'] . '&save-lines=' . $result['save'] . '&import-template=' . $templateModel->id
                    . '&import-mode=' . $mode, 1);
                return true;
            }

            unlink($filePath);
            Tools::log()->notice('items-added-correctly', ['%num%' => $result['save']]);
            return true;
        };
    }

    public function importBankingMovementsAutoAction(): Closure
    {
        return function (string $filePath) {
            // buscamos una plantilla automática
            $autoTemplates = CSVfile::autoTemplate($filePath, 'banking-movements');
            if ($autoTemplates === null) {
                return false;
            }

            // procesamos el archivo
            $mode = $this->request->get('import-mode');
            $offset = (int)$this->request->get('import-offset', 0);
            $saveLines = (int)$this->request->get('save-lines', 0);
            if (false === $autoTemplates->run($filePath, 'banking-movements', $mode, $offset, $saveLines)) {
                return true;
            }

            if ($autoTemplates->continue()) {
                Tools::log()->notice(
                    'items-save-correctly-to-total',
                    ['%lines%' => $offset, '%total%' => $autoTemplates->getTotalLines(), '%save%' => $saveLines]
                );
                Tools::log()->notice('importing');
                $fileName = $this->request->get('import-filename');
                $this->redirect($this->url() . '?action=import-banking-movements&import-filename=' . $fileName
                    . '&import-offset=' . $offset . '&save-lines=' . $saveLines . '&import-template=' . CsvFileTools::AUTOMATIC . '&import-mode=' . $mode, 1);
                return true;
            }

            unlink($filePath);
            Tools::log()->notice('items-added-correctly', ['%num%' => $saveLines]);
            return true;
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName !== 'ListMovimientoBanco') {
                return;
            }

            // cargamos los datos
            $mvn = $this->getMainViewName();
            $where = [new DataBaseWhere('codcuenta', $this->getViewModelValue($mvn, 'codcuenta'))];
            $view->loadData('', $where);

            // si hay datos añadimos el botón para redirigir a conciliar
            if ($this->views[$viewName]->model->count()) {
                $this->addButton($viewName, [
                    'action' => '/ConciliateBankMovements?codcuenta=' . $this->getViewModelValue($mvn, 'codcuenta'),
                    'icon' => 'fas fa-check-double',
                    'label' => 'conciliate',
                    'type' => 'link'
                ]);
            }

            // rellenamos el select de plantillas del modal
            $column = $this->views[$viewName]->columnModalForName('template');
            if ($column && $column->widget->getType() === 'select') {
                // añadimos las opciones automatic y new
                $customValues = [
                    ['value' => 'automatic', 'title' => Tools::lang()->trans('automatic-template')],
                    ['value' => 'new-template', 'title' => Tools::lang()->trans('new-template')]
                ];

                // añadimos la lista de plantillas compatibles
                $csvFile = new CSVfile();
                $where = [
                    new DataBaseWhere('template', null, 'IS NOT'),
                    new DataBaseWhere('options', null, 'IS NOT'),
                    new DataBaseWhere('profile', 'banking-movements')
                ];
                $templates = $csvFile->all($where, ['template' => 'ASC'], 0, 0);
                if ($templates) {
                    $customValues[] = ['value' => '', 'title' => '------'];
                }
                foreach ($templates as $csv) {
                    $customValues[] = ['value' => $csv->id, 'title' => $csv->template];
                }

                // asignamos el valor predeterminado
                $view->model->template = CsvFileTools::NEW_TEMPLATE;

                // cargamos la lista de valores
                $column->widget->setValuesFromArray($customValues);
            }
        };
    }

    public function uploadBankingMovementsAction(): Closure
    {
        return function () {
            // comprobamos los permisos de importación
            if (false === $this->permissions->allowImport) {
                Tools::log()->warning('no-import-permission');
                return true;
            }

            // comprobamos el token
            if (false === $this->validateFormToken()) {
                return true;
            }

            // comprobamos el tamaño y tipo del archivo
            $uploadFile = $this->request->files->get('bankingmovementsfile');
            if (CsvFileTools::isBigFile($uploadFile) || false === CsvFileTools::isValidFile($uploadFile->getRealPath())) {
                return true;
            }

            try {
                // movemos el archivo
                $path = CsvFileTools::saveUploadFile($uploadFile);

                // convertimos el archivo a CSV, si es necesario
                $filePath = CsvFileTools::convertFileToCsv($path);
            } catch (Exception $exc) {
                Tools::log()->warning('upload-file-error');
                Tools::log()->warning($uploadFile->getClientOriginalName());
                Tools::log()->warning($exc->getMessage());
                return true;
            }

            $model = $this->getModel();
            if (false === $model->loadFromCode($this->request->get('code'))) {
                Tools::log()->warning('record-not-found');
                return true;
            }

            // recargamos la página para llamar a la acción de importación
            $fileName = basename($filePath);
            $template = $this->request->request->get('template', CsvFileTools::AUTOMATIC);
            $mode = $this->request->request->get('mode', CsvFileTools::AUTOMATIC);
            $this->redirect($this->url() . '?action=import-banking-movements&import-filename=' . urlencode($fileName)
                . '&import-template=' . $template . '&import-mode=' . $mode . '&codcuenta=' . $model->codcuenta);
            return true;
        };
    }
}
