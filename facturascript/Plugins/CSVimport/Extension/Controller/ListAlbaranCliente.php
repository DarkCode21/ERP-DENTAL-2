<?php
/**
 * Copyright (C) 2019-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Extension\Controller;

use Closure;
use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CSVfile;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class ListAlbaranCliente
{
    public function createViews(): Closure
    {
        return function () {
            // import button
            if ($this->permissions->allowImport) {
                $this->addButton('ListAlbaranCliente', [
                    'action' => 'upload-delivery-notes',
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
                case 'import-delivery-notes':
                    $this->importDeliveryNotesAction();
                    break;

                case 'upload-delivery-notes':
                    $this->uploadDeliveryNotesAction();
                    break;
            }
        };
    }

    public function importDeliveryNotesAction(): Closure
    {
        return function () {
            // obtenemos la ruta completa del archivo
            $fileName = $this->request->get('import-filename');
            $filePath = CsvFileTools::getFilePath($fileName);
            if (empty($filePath)) {
                return true;
            }

            // se ha elegido crear nueva plantilla
            $template = $this->request->get('import-template', CsvFileTools::NEW_TEMPLATE);
            if ($template === CsvFileTools::NEW_TEMPLATE) {
                $newCsvFile = CSVfile::newTemplate($fileName, 'customer-delivery-notes');
                $newCsvFile->mode = $this->request->get('import-mode');
                if ($newCsvFile->save()) {
                    $this->redirect($newCsvFile->url());
                }
                return true;
            }

            // se ha elegido plantilla automática
            if ($template === CsvFileTools::AUTOMATIC && $this->importDeliveryNotesAutoAction($filePath)) {
                return true;
            }

            // se ha elegido una plantilla existente
            $templateModel = $template === CsvFileTools::AUTOMATIC ?
                CsvFileTools::getFileTemplate($filePath) :
                CsvFileTools::getFileTemplate($filePath, $template, 'customer-delivery-notes');
            if (is_null($templateModel)) {
                // no se ha encontrado la plantilla
                Tools::log()->warning('template-not-found');

                // creamos una nueva plantilla
                $newCsvFile = CSVfile::newTemplate($fileName, 'customer-delivery-notes');
                $newCsvFile->mode = $this->request->get('import-mode');
                if ($newCsvFile->save()) {
                    $this->redirect($newCsvFile->url(), 1);
                }
                return true;
            }

            // procesamos el archivo
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
                $this->redirect($this->url() . '?action=import-delivery-notes&import-filename=' . urlencode($fileName)
                    . '&import-offset=' . $result['offset'] . '&save-lines=' . $result['save']
                    . '&import-template=' . $templateModel->id . '&import-mode=' . $mode, 1);
                return true;
            }

            unlink($filePath);
            Tools::log()->notice('items-added-correctly', ['%num%' => $result['save']]);
            return true;
        };
    }

    public function importDeliveryNotesAutoAction(): Closure
    {
        return function (string $filePath) {
            // buscamos una plantilla automática
            $autoTemplates = CSVfile::autoTemplate($filePath, 'customer-delivery-notes');
            if ($autoTemplates === null) {
                return false;
            }

            // procesamos el archivo
            $mode = $this->request->get('import-mode');
            $offset = (int)$this->request->get('import-offset', 0);
            $saveLines = (int)$this->request->get('save-lines', 0);
            if (false === $autoTemplates->run($filePath, 'customer-delivery-notes', $mode, $offset, $saveLines)) {
                return true;
            }

            if ($autoTemplates->continue()) {
                Tools::log()->notice(
                    'items-save-correctly-to-total',
                    ['%lines%' => $offset, '%total%' => $autoTemplates->getTotalLines(), '%save%' => $saveLines]
                );
                Tools::log()->notice('importing');
                $fileName = $this->request->get('import-filename');
                $this->redirect($this->url() . '?action=import-delivery-notes&import-filename=' . urlencode($fileName)
                    . '&import-offset=' . $offset . '&save-lines=' . $saveLines
                    . '&import-template=' . CsvFileTools::AUTOMATIC . '&import-mode=' . $mode, 1);
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
            if ($viewName !== 'ListAlbaranCliente') {
                return;
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
                    new DataBaseWhere('profile', 'customer-delivery-notes')
                ];
                $templates = $csvFile->all($where, ['template' => 'ASC'], 0, 0);
                if ($templates) {
                    $customValues[] = ['value' => '', 'title' => '------'];
                }
                foreach ($templates as $csv) {
                    $customValues[] = ['value' => $csv->id, 'title' => $csv->template];
                }

                // asignamos el valor predeterminado
                $view->model->template = CsvFileTools::AUTOMATIC;

                // cargamos la lista de valores
                $column->widget->setValuesFromArray($customValues);
            }
        };
    }

    public function uploadDeliveryNotesAction(): Closure
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
            $uploadFile = $this->request->files->get('deliverynotesfile');
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

            // recargamos la página para llamar a la acción de importación
            $fileName = basename($filePath);
            $template = $this->request->request->get('template', CsvFileTools::AUTOMATIC);
            $mode = $this->request->request->get('mode', CsvFileTools::AUTOMATIC);
            $this->redirect($this->url() . '?action=import-delivery-notes&import-filename=' . urlencode($fileName)
                . '&import-template=' . $template . '&import-mode=' . $mode);
            return true;
        };
    }
}
