<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Lib;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Dinamic\Lib\PortalPanelController;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
abstract class PortalViewController extends PortalPanelController
{
    const DEFAULT_TEMPLATE = 'Master/PortalViewTemplate';
    const MAIN_VIEW_NAME = 'main';

    /**
     * Returns the class name of the model to use in the editView.
     */
    abstract public function getModelClassName(): string;

    /**
     * Sets the tabs position, by default is set to 'top', also supported 'bottom'.
     *
     * @param string $position
     */
    public function setTabsPosition(string $position)
    {
        $this->tabsPosition = $position;
    }

    protected function createEditView()
    {
        $viewName = 'Edit' . $this->getModelClassName();
        $this->addEditView($viewName, $this->getModelClassName(), 'edit');
        $this->setSettings($viewName, 'btnDelete', $this->permissions->allowDelete);
    }

    protected function createViews()
    {
        $this->addHtmlView(static::MAIN_VIEW_NAME, $this->getClassName(), $this->getModelClassName(), static::MAIN_VIEW_NAME);
    }

    protected function getComposeUrlColumn(): string
    {
        return '';
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case self::MAIN_VIEW_NAME:
                // do not merge end with explode
                $parts = explode('/', $this->uri);
                $code = end($parts);
                if (!empty($code)) {
                    $colName = empty($this->getComposeUrlColumn()) ? $view->model->primaryColumn() : $this->getComposeUrlColumn();
                    $where = [new DataBaseWhere($colName, $code)];
                    $view->loadData('', $where);
                    if ($view->count > 0) {
                        break;
                    }
                }
                $altCode = $this->request->query->get('code', '');
                $view->loadData($altCode);
                $this->description = $view->model->primaryDescription();
                $this->title .= ' ' . $view->model->primaryColumnValue();
                break;

            case 'Edit' . $this->getModelClassName():
                $code = $this->views[self::MAIN_VIEW_NAME]->model->primaryColumnValue();
                $view->loadData($code);
                break;
        }
    }

    protected function preloadModel(): ModelClass
    {
        $modelClass = self::MODEL_NAMESPACE . $this->getModelClassName();
        $model = new $modelClass();

        // do not merge end with explode
        $parts = explode('/', $this->uri);
        $code = end($parts);
        if (!empty($code)) {
            $colName = empty($this->getComposeUrlColumn()) ? $model->primaryColumn() : $this->getComposeUrlColumn();
            $where = [new DataBaseWhere($colName, $code)];
            if ($model->loadFromCode('', $where)) {
                return $model;
            }
        }

        $altCode = $this->request->query->get('code', '');
        $model->loadFromCode($altCode);
        return $model;
    }
}
