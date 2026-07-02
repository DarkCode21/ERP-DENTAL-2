<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\HumanResources;

use Exception;
use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Lib\ExtendedController\ListView;

/**
 * Controller for report data and sumary data
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
abstract class ReportController extends ListController
{

    const MODEL_REPORT_NAMESPACE = self::MODEL_NAMESPACE . 'Report\\';

    /**
     * Initializes all the objects and properties.
     *
     * @param string $className
     * @param string $uri
     */
    public function __construct(string $className, string $uri = '')
    {
        parent::__construct($className, $uri);
        $this->setTemplate('Master/ReportController');
    }

    /**
     * Creates and adds a ReportView to the controller.
     *
     * @param string $viewName
     * @param string $modelName
     * @param string $viewTitle
     * @param string $icon
     * @return ReportView
     * @throws Exception
     */
    protected function addView(string $viewName, string $modelName, string $viewTitle = '', string $icon = 'fa-solid fa-search'): ListView
    {
        $title = empty($viewTitle) ? $this->title : $viewTitle;
        $view = new ReportView($viewName, $title, self::MODEL_REPORT_NAMESPACE . $modelName, $icon);
        $this->addCustomView($viewName, $view);
        $this->setSettings($viewName, 'btnPrint', true);
        $this->setSettings($viewName, 'card', false);
        $this->setSettings($viewName, 'megasearch', false);
        return $view;
    }

    /**
     * Runs the controller actions after data read.
     *
     * @param string $action
     */
    protected function execAfterAction($action)
    {
        if ($action === 'export') {
            $option = $this->request->get('option', 'show');
            if ($option === 'show') {
                return;
            }
        }
        parent::execAfterAction($action);
    }

    /**
     * Load data for active view. For greater performance, only for active view
     * Load data when: exec export action or load saved filter.
     *
     * @param string $viewName
     * @param ReportView $view
     */
    protected function loadData($viewName, $view)
    {
        if ($this->mustLoadData($viewName)) {
            $view->loadData();
        }
    }

    /**
     * indicates if the data should be loaded for the reported view.
     *
     * @param string $viewName
     * @return bool
     */
    protected function mustLoadData(string $viewName): bool
    {
        if ($this->active == $viewName) {
            $action = $this->request->get('action', '');
            $loadfilter = $this->request->get('loadfilter', '');
            return ($action == 'export' || false === empty($loadfilter));
        }
        return false;
    }
}
