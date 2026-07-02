<?php
/**
 * Copyright (C) 2020-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Controller;

use Exception;
use FacturaScripts\Core\Base\ExtensionsTrait;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\CsvFileTools;
use FacturaScripts\Dinamic\Model\CSVfile;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class EditCSVfile extends EditController
{
    use ExtensionsTrait;

    public function getModelClassName(): string
    {
        return 'CSVfile';
    }

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'admin';
        $pageData['title'] = 'csv-file';
        $pageData['icon'] = 'fas fa-file-csv';
        return $pageData;
    }

    public function getRequiredTitles(array $fields, array $columns): string
    {
        $titles = [];
        foreach ($columns as $column) {
            if (isset($fields[$column])) {
                $titles[] = Tools::lang()->trans($fields[$column]['title']);
            }
        }
        return implode(', ', $titles);
    }

    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        // desactivamos el botón de opciones de la primera pestaña
        $mvn = $this->getMainViewName();
        $this->tab($mvn)->setSettings('btnOptions', false);

        $this->createViewsFields();
    }

    protected function createViewsFields(string $viewName = 'CSVfields'): void
    {
        $this->addHtmlView($viewName, 'CSVfields', 'CSVfile', 'fields', 'fas fa-cogs');
    }

    protected function execAfterAction($action)
    {
        parent::execAfterAction($action);
        if ($action === 'import') {
            $this->importCsvAction();
        }
    }

    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'download-file':
                return $this->downloadFileAction();

            case 'upload-new-file':
                return $this->uploadNewFileAction();

            case 'save-columns':
                return $this->saveColumnsAction();
        }

        return parent::execPreviousAction($action);
    }

    protected function importCsvAction(): void
    {
        $offset = (int)$this->request->get('import-offset', '0');
        $saveLines = (int)$this->request->get('save-lines', '0');
        if ($offset === 0 && false === $this->saveColumnsAction()) {
            return;
        }

        $model = $this->getModel();
        $result = $model->getProfile($offset, $saveLines)->import();
        $errors = Tools::log()->read('', ['error']);
        if ($result['offset'] > 0 && $result['offset'] < $result['total'] && empty($errors)) {
            Tools::log()->notice(
                'items-save-correctly-to-total',
                ['%lines%' => $result['offset'], '%total%' => $result['total'], '%save%' => $result['save']]
            );
            Tools::log()->notice('importing');
            $this->redirect($this->url() . '?code=' . $this->request->get('code') . '&action=import&import-offset='
                . $result['offset'] . '&save-lines=' . $result['save'], 1);
            return;
        }

        Tools::log()->notice('items-added-correctly', ['%num%' => $result['save']]);
        $this->pipe('afterImport', $model, $result);
    }

    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);
        $this->loadProfileValues($viewName);

        // si la columna de proveedor no está vacía, la mostramos
        if (false === empty($view->model->codproveedor)) {
            $this->views[$viewName]->disableColumn('supplier', false);
        }

        if ($viewName === 'EditCSVfile' && false === empty($view->model->path)) {
            $this->addButton('EditCSVfile', [
                'action' => 'upload-new-file',
                'color' => 'warning',
                'icon' => 'fas fa-file-upload',
                'label' => 'upload-file',
                'type' => 'modal'
            ]);
        }

        if ($viewName === 'EditCSVfile' && false === empty($view->model->url)) {
            $this->addButton('EditCSVfile', [
                'action' => 'download-file',
                'color' => 'info',
                'icon' => 'fas fa-file-download',
                'label' => 'download',
                'type' => 'action'
            ]);
        }
    }

    protected function loadProfileValues(string $viewName)
    {
        $column = $this->views[$viewName]->columnForName('profile');
        if ($column && $column->widget->getType() === 'select') {
            $values = [];
            foreach (CSVfile::getManualTemplates() as $key => $value) {
                $values[] = ['value' => $key, 'title' => Tools::lang()->trans($key)];
            }

            $column->widget->setValuesFromArray($values);
        }
    }

    protected function saveColumnsAction(): bool
    {
        $model = $this->getModel();
        if (false === $model->loadFromCode($this->request->get('code'))) {
            return true;
        }

        $options = [];
        $exclude = ['action', 'import-offset', 'multireqtoken', 'save-lines'];
        foreach ($this->request->request->all() as $key => $value) {
            if (!empty($value) && !in_array($key, $exclude)) {
                $options[$key] = $value;
            }
        }

        // guardamos las columnas que vamos a usar
        if (false === $model->setOptions($options)) {
            return true;
        }

        if (false === $this->checkRequiredAnd($model, $options)) {
            return true;
        }

        if (false === $this->checkRequiredOr($model, $options)) {
            return true;
        }

        // obtenemos las columnas del csv según las opciones seleccionadas
        $columns = [];
        $titles = $model->getProfile()->getCsv()->titles;
        foreach ($options as $key => $value) {
            $index = str_replace('field', '', $key);
            $columns[$key] = $titles[$index];
        }


        return $model->setCsvColumns($columns);
    }

    protected function checkRequiredAnd($model, $options): bool
    {
        $requiredAndCont = 0;
        $fields = $model->getProfile()->getDataFields();
        $requiredAnd = $model->getProfile()->getRequiredFieldsAnd();

        if (empty($requiredAnd)) {
            return true;
        }

        $fieldsToColumn = $model->getProfile()->getFieldsToColumn();
        foreach ($requiredAnd as $required) {
            // comprobamos que el campo requerido esté en las opciones
            foreach ($options as $key => $value) {
                if ($required === $value) {
                    $requiredAndCont++;
                    continue 2;
                }
            }

            // comprobamos si el campo requerido está relacionado con alguna columna del modelo
            foreach ($fieldsToColumn as $field => $column) {
                if ($required === $field && property_exists($model, $column) && false === empty($model->{$column})) {
                    $requiredAndCont++;
                    continue 2;
                }
            }
        }

        // comprobamos que se hayan seleccionado todos los campos requeridos
        if ($requiredAndCont !== count($requiredAnd)) {
            Tools::log()->error(
                Tools::lang()->trans('you-must-select-all-of-the-following-required-fields')
                . ': ' . $this->getRequiredTitles($fields, $requiredAnd)
            );
            return false;
        }

        return true;
    }

    protected function checkRequiredOr($model, $options): bool
    {
        $fields = $model->getProfile()->getDataFields();
        $requiredOr = $model->getProfile()->getRequiredFieldsOr();

        if (empty($requiredOr)) {
            return true;
        }

        foreach ($requiredOr as $required) {
            // comprobamos que al menos uno de los campos requeridos esté en las opciones
            foreach ($options as $key => $value) {
                if ($required === $value) {
                    return true;
                }
            }
        }

        Tools::log()->error(
            Tools::lang()->trans('you-must-select-one-of-the-following-required-fields')
            . ': ' . $this->getRequiredTitles($fields, $requiredOr)
        );

        return false;
    }

    protected function downloadFileAction(): bool
    {
        if (false === $this->validateFormToken()) {
            return true;
        }

        // cargamos el modelo
        $model = new CSVfile();
        if (false === $model->loadFromCode($this->request->get('code'))) {
            Tools::log()->error('record-not-found');
            return true;
        }

        if (false === $model->download()) {
            Tools::log()->error('record-save-error');
            return true;
        }

        Tools::log()->notice('record-updated-correctly');
        return true;
    }

    protected function uploadNewFileAction(): bool
    {
        if (false === $this->validateFormToken()) {
            return true;
        }

        // cargamos el modelo
        $model = new CSVfile();
        if (false === $model->loadFromCode($this->request->get('code'))) {
            Tools::log()->error('record-not-found');
            return true;
        }

        // comprobamos el tamaño y tipo de archivo
        $uploadFile = $this->request->files->get('newfile');
        if (CsvFileTools::isBigFile($uploadFile) || false === CsvFileTools::isValidFile($uploadFile->getRealPath())) {
            return true;
        }

        // movemos y convertimos el archivo
        try {
            $path = CsvFileTools::saveUploadFile($uploadFile);
            $filePath = CsvFileTools::convertFileToCSV($path);
        } catch (Exception $exc) {
            Tools::log()->warning('upload-file-error');
            Tools::log()->warning($uploadFile->getClientOriginalName());
            Tools::log()->warning($exc->getMessage());
            return true;
        }

        // asignamos el archivo al modelo
        $model->path = basename($filePath);
        if (false === $model->save()) {
            Tools::log()->error('record-save-error');
            return true;
        }

        Tools::log()->notice('record-updated-correctly');
        return true;
    }
}
