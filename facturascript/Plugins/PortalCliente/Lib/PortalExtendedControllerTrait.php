<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Lib;

use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\ListView;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CodeModel;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait PortalExtendedControllerTrait
{
    /**
     * Indicates the active view.
     *
     * @var string
     */
    public $active;

    /**
     * Model to use with select and autocomplete filters.
     *
     * @var CodeModel
     */
    public $codeModel;

    /**
     * Indicates current view, when drawing.
     *
     * @var string
     */
    private $current;

    /**
     * List of views displayed by the controller.
     *
     * @var BaseView[]|ListView[]
     */
    public $views = [];

    /**
     * Inserts the views or tabs to display.
     */
    abstract protected function createViews();

    /**
     * Loads the data to display.
     *
     * @param string $viewName
     * @param BaseView $view
     */
    abstract protected function loadData($viewName, $view);

    public function addButton(string $viewName, array $btnArray): BaseView
    {
        if (!array_key_exists($viewName, $this->views)) {
            throw new Exception('View not found: ' . $viewName);
        }

        $rowType = isset($btnArray['row']) ? 'footer' : 'actions';
        $row = $this->views[$viewName]->getRow($rowType);
        if ($row) {
            $row->addButton($btnArray);
        }

        return $this->tab($viewName);
    }

    public function addCustomView(string $viewName, BaseView $view): BaseView
    {
        if ($viewName !== $view->getViewName()) {
            throw new Exception('$viewName must be equals to $view->name');
        }

        $view->loadPageOptions($this->user);

        $this->views[$viewName] = $view;
        if (empty($this->active)) {
            $this->active = $viewName;
        }

        return $view;
    }

    public function getCurrentView(): BaseView
    {
        return $this->tab($this->current);
    }

    public function getMainViewName(): string
    {
        foreach (array_keys($this->views) as $key) {
            return $key;
        }

        return '';
    }

    /**
     * Returns the configuration value for the indicated view.
     *
     * @param string $viewName
     * @param string $property
     *
     * @return mixed
     * @throws Exception
     */
    public function getSettings(string $viewName, string $property)
    {
        return $this->tab($viewName)->settings[$property] ?? null;
    }

    /**
     * Return the value for a field in the model of the view.
     *
     * @param string $viewName
     * @param string $fieldName
     *
     * @return mixed
     * @throws Exception
     */
    public function getViewModelValue(string $viewName, string $fieldName)
    {
        return $this->tab($viewName)->model->{$fieldName} ?? null;
    }

    public function setCurrentView(string $viewName): void
    {
        $this->current = $viewName;
    }

    /**
     * Set value for setting of a view
     *
     * @param string $viewName
     * @param string $property
     * @param mixed $value
     * @return BaseView
     * @throws Exception
     */
    public function setSettings(string $viewName, string $property, $value): BaseView
    {
        return $this->tab($viewName)->setSettings($property, $value);
    }

    public function tab(string $viewName): BaseView
    {
        if (isset($this->views[$viewName])) {
            return $this->views[$viewName];
        }

        throw new Exception('View not found: ' . $viewName);
    }

    protected function autocompleteAction(): array
    {
        $data = $this->requestGet(['field', 'fieldcode', 'fieldfilter', 'fieldtitle', 'formname', 'source', 'strict', 'term']);
        if ($data['source'] == '') {
            return $this->getAutocompleteValues($data['formname'], $data['field']);
        }

        $where = [];
        foreach (DataBaseWhere::applyOperation($data['fieldfilter'] ?? '') as $field => $operation) {
            $value = $this->request->get($field);
            $where[] = new DataBaseWhere($field, $value, '=', $operation);
        }

        $results = [];
        foreach ($this->codeModel->search($data['source'], $data['fieldcode'], $data['fieldtitle'], $data['term'], $where) as $value) {
            $results[] = ['key' => Tools::fixHtml($value->code), 'value' => Tools::fixHtml($value->description)];
        }

        if (empty($results) && '0' == $data['strict']) {
            $results[] = ['key' => $data['term'], 'value' => $data['term']];
        } elseif (empty($results)) {
            $results[] = ['key' => null, 'value' => Tools::lang()->trans('no-data')];
        }

        return $results;
    }

    protected function checkModelSecurity(ModelClass $model): bool
    {
        return true;
    }

    protected function deleteAction(): bool
    {
        // check user permissions
        if (false === $this->permissions->allowDelete || false === $this->views[$this->active]->settings['btnDelete']) {
            Tools::log()->warning('not-allowed-delete');
            return false;
        } elseif (false === $this->validateFormToken()) {
            return false;
        }

        $model = $this->views[$this->active]->model;
        $codes = $this->request->request->get('code', '');
        if (empty($codes)) {
            Tools::log()->warning('no-selected-item');
            return false;
        }

        if (is_array($codes)) {
            $this->dataBase->beginTransaction();

            // deleting multiples rows
            $numDeletes = 0;
            foreach ($codes as $cod) {
                if ($model->loadFromCode($cod) && $model->delete()) {
                    ++$numDeletes;
                    continue;
                }

                // error?
                $this->dataBase->rollback();
                break;
            }

            $model->clear();
            $this->dataBase->commit();
            if ($numDeletes > 0) {
                Tools::log()->notice('record-deleted-correctly');
                return true;
            }
        } elseif ($model->loadFromCode($codes) && $model->delete()) {
            // deleting a single row
            Tools::log()->notice('record-deleted-correctly');
            $model->clear();
            return true;
        }

        Tools::log()->warning('record-deleted-error');
        $model->clear();
        return false;
    }

    protected function getAutocompleteValues(string $viewName, string $fieldName): array
    {
        $result = [];
        $column = $this->views[$viewName]->columnForField($fieldName);
        if (!empty($column)) {
            foreach ($column->widget->values as $value) {
                $result[] = ['key' => Tools::lang()->trans($value['title']), 'value' => $value['value']];
            }
        }
        return $result;
    }

    protected function requestGet(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->request->get($key);
        }
        return $result;
    }
}
