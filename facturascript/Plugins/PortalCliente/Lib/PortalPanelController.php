<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Lib;

use FacturaScripts\Core\Lib\ExtendedController\EditListView;
use FacturaScripts\Core\Lib\ExtendedController\EditView;
use FacturaScripts\Core\Lib\ExtendedController\HtmlView;
use FacturaScripts\Core\Lib\ExtendedController\ListView;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CodeModel;
use FacturaScripts\Dinamic\Lib\PortalController;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
abstract class PortalPanelController extends PortalController
{
    use PortalExtendedControllerTrait;

    const DEFAULT_TEMPLATE = 'Master/PortalPanelTemplate';
    const MODEL_NAMESPACE = '\\FacturaScripts\\Dinamic\\Model\\';

    /**
     * Indicates if the main view has data or is empty.
     *
     * @var bool
     */
    public $hasData = false;

    /**
     * Tabs position in page: left, bottom.
     *
     * @var string
     */
    public $tabsPosition;

    /**
     * @param string $className
     * @param string $uri
     */
    public function __construct(string $className, string $uri = '')
    {
        parent::__construct($className, $uri);
        $activeTabGet = $this->request->query->get('activetab', '');
        $this->active = $this->request->request->get('activetab', $activeTabGet);
        $this->codeModel = new CodeModel();
        $this->setTabsPosition('top');
    }

    public function commonCore(): void
    {
        parent::commonCore();

        // Create the views to display
        $this->createViews();
        $this->pipe('createViews');

        // Get any operations that have to be performed
        $action = $this->request->request->get('action', $this->request->query->get('action', ''));

        // Runs operations before reading data
        if ($this->execPreviousAction($action) === false || $this->pipe('execPreviousAction', $action) === false) {
            return;
        }

        // Load the data for each view
        $mainViewName = $this->getMainViewName();
        foreach ($this->views as $viewName => $view) {
            // disable views if main view has no data
            if ($viewName != $mainViewName && false === $this->hasData) {
                $this->setSettings($viewName, 'active', false);
            }

            if (false === $view->settings['active']) {
                // exclude inactive views
                continue;
            } elseif ($this->active == $viewName) {
                $view->processFormData($this->request, 'load');
            } else {
                $view->processFormData($this->request, 'preload');
            }

            $this->loadData($viewName, $view);
            $this->pipe('loadData', $viewName, $view);

            if ($viewName === $mainViewName && $view->model->exists()) {
                $this->hasData = true;
            }
        }

        // General operations with the loaded data
        $this->execAfterAction($action);
        $this->pipe('execAfterAction', $action);
    }

    /**
     * Sets the tabs position, by default is set to 'top', also supported 'bottom'.
     *
     * @param string $position
     */
    public function setTabsPosition(string $position)
    {
        $this->tabsPosition = $position;
    }

    /**
     * Adds a EditList type view to the controller.
     *
     * @param string $viewName
     * @param string $modelName
     * @param string $viewTitle
     * @param string $viewIcon
     */
    protected function addEditListView($viewName, $modelName, $viewTitle, $viewIcon = 'fas fa-bars'): EditListView
    {
        $view = new EditListView($viewName, $viewTitle, self::MODEL_NAMESPACE . $modelName, $viewIcon);
        $this->addCustomView($viewName, $view);

        return $view;
    }

    /**
     * Adds a Edit type view to the controller.
     *
     * @param string $viewName
     * @param string $modelName
     * @param string $viewTitle
     * @param string $viewIcon
     */
    protected function addEditView($viewName, $modelName, $viewTitle, $viewIcon = 'fas fa-edit'): EditView
    {
        $view = new EditView($viewName, $viewTitle, self::MODEL_NAMESPACE . $modelName, $viewIcon);
        $view->settings['card'] = false;
        $this->addCustomView($viewName, $view);

        return $view;
    }

    /**
     * Adds a HTML type view to the controller.
     *
     * @param string $viewName
     * @param string $fileName
     * @param string $modelName
     * @param string $viewTitle
     * @param string $viewIcon
     */
    protected function addHtmlView($viewName, $fileName, $modelName, $viewTitle, $viewIcon = 'fab fa-html5'): HtmlView
    {
        $view = new HtmlView($viewName, $viewTitle, self::MODEL_NAMESPACE . $modelName, $fileName, $viewIcon);
        $this->addCustomView($viewName, $view);

        return $view;
    }

    /**
     * Adds a List type view to the controller.
     *
     * @param string $viewName
     * @param string $modelName
     * @param string $viewTitle
     * @param string $viewIcon
     */
    protected function addListView($viewName, $modelName, $viewTitle, $viewIcon = 'fas fa-list'): ListView
    {
        $view = new ListView($viewName, $viewTitle, self::MODEL_NAMESPACE . $modelName, $viewIcon);
        $view->settings['card'] = true;
        $view->template = 'Master/PortalListView.html.twig';
        $this->addCustomView($viewName, $view);

        return $view;
    }

    /**
     * Run the controller after actions.
     *
     * @param string $action
     */
    protected function execAfterAction($action)
    {
    }

    /**
     * Run the actions that alter data before reading it.
     *
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'autocomplete':
                $this->setTemplate(false);
                $results = $this->autocompleteAction();
                $this->response->setContent(json_encode($results));
                return false;

            case 'delete':
                $this->deleteAction();
                break;

            case 'edit':
                if ($this->editAction()) {
                    $this->views[$this->active]->model->clear();
                }
                break;

            case 'insert':
                if ($this->insertAction() || !empty($this->views[$this->active]->model->primaryColumnValue())) {
                    // we need to clear model in these scenarios
                    $this->views[$this->active]->model->clear();
                }
                break;
        }

        return true;
    }

    /**
     * Runs the data edit action.
     *
     * @return bool
     */
    protected function editAction()
    {
        if (false === $this->permissions->allowUpdate || false === $this->views[$this->active]->settings['btnSave']) {
            Tools::log()->warning('not-allowed-modify');
            return false;
        } elseif (false === $this->validateFormToken()) {
            return false;
        }

        // loads model data
        $code = $this->request->request->get('code', '');
        if (false === $this->views[$this->active]->model->loadFromCode($code)) {
            Tools::log()->error('record-not-found');
            return false;
        }

        // loads form data
        $this->views[$this->active]->processFormData($this->request, 'edit');

        // checks security
        if (false === $this->checkModelSecurity($this->views[$this->active]->model)) {
            Tools::log()->warning('not-allowed-modify');
            return false;
        }

        // has PK value been changed?
        $this->views[$this->active]->newCode = $this->views[$this->active]->model->primaryColumnValue();
        if ($code != $this->views[$this->active]->newCode && $this->views[$this->active]->model->test()) {
            $pkColumn = $this->views[$this->active]->model->primaryColumn();
            $this->views[$this->active]->model->{$pkColumn} = $code;
            // change in database
            if (!$this->views[$this->active]->model->changePrimaryColumnValue($this->views[$this->active]->newCode)) {
                Tools::log()->error('record-save-error');
                return false;
            }
        }

        // save in database
        if ($this->views[$this->active]->model->save()) {
            Tools::log()->notice('record-updated-correctly');
            return true;
        }

        Tools::log()->error('record-save-error');
        return false;
    }

    /**
     * Runs data insert action.
     *
     * @return bool
     */
    protected function insertAction()
    {
        if (false === $this->permissions->allowUpdate || false === $this->views[$this->active]->settings['btnNew']) {
            Tools::log()->warning('not-allowed-modify');
            return false;
        } elseif (false === $this->validateFormToken()) {
            return false;
        }

        // loads form data
        $this->views[$this->active]->processFormData($this->request, 'edit');
        if ($this->views[$this->active]->model->exists()) {
            Tools::log()->error('duplicate-record');
            return false;
        }

        // checks security
        if (false === $this->checkModelSecurity($this->views[$this->active]->model)) {
            Tools::log()->warning('not-allowed-modify');
            return false;
        }

        // save in database
        if (false === $this->views[$this->active]->model->save()) {
            Tools::log()->error('record-save-error');
            return false;
        }

        // redir to new model url only if this is the first view
        if ($this->active === $this->getMainViewName()) {
            $this->redirect($this->views[$this->active]->model->url() . '&action=save-ok');
        }

        $this->views[$this->active]->newCode = $this->views[$this->active]->model->primaryColumnValue();
        Tools::log()->notice('record-updated-correctly');
        return true;
    }
}
