<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Controller;

use FacturaScripts\Core\Lib\ExtendedController\DocFilesTrait;
use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * Controler to edit Contract.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditContract extends EditController
{

    use DocFilesTrait;

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'Contract';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'contracts';
        $pagedata['icon'] = 'fa-solid fa-handshake';
        $pagedata['menu'] = 'rrhh';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    /**
     * Create views to display.
     */
    protected function createViews()
    {
        parent::createViews();
        $this->createViewDocFiles('docfiles', 'Tab/PreviewFiles');
        $this->setTabsPosition('bottom');
    }

    /**
     * Run the actions that alter data before reading it.
     *
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'add-file':
                return $this->addFileAction();

            case 'delete-file':
                return $this->deleteFileAction();

            case 'edit-file':
                return $this->editFileAction();

            case 'unlink-file':
                return $this->unlinkFileAction();

            default:
                return parent::execPreviousAction($action);
        }
    }

    /**
     * Load view data procedure
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $mainViewName = $this->getMainViewName();
        if ($viewName === $mainViewName) {
            parent::loadData($viewName, $view);
            return;
        }

        $idcontract = $this->getViewModelValue($mainViewName, 'id');
        switch ($viewName) {
            case 'docfiles':
                $this->loadDataDocFiles($view, $this->getModelClassName(), $idcontract);
                break;
        }
    }
}
