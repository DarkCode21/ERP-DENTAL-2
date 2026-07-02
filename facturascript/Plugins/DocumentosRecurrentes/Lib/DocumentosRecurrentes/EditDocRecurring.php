<?php
/**
 * This file is part of DocumentosRecurrentes plugin for FacturaScripts.
 * FacturaScripts         Copyright (C) 2015-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DocumentosRecurrentes  Copyright (C) 2020-2021 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DocumentosRecurrentes\Lib\DocumentosRecurrentes;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Lib\ExtendedController\EditController;
use FacturaScripts\Dinamic\Model\Base\DocRecurring;
use FacturaScripts\Dinamic\Model\CodeModel;

/**
 * Description of EditDocRecurring
 *
 * @author Carlos Garcia Gomez  <carlos@facturascripts.com>
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
abstract class EditDocRecurring extends EditController
{

    abstract protected function duplicateDocAction();

    abstract protected function generateDocsAction();

    abstract protected function generateManuallyAction();

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'doc-recurring';
        $pagedata['icon'] = 'fas fa-calendar-plus';
        $pagedata['showonmenu'] = false;
        return $pagedata;
    }

    /**
     * Create the view to display.
     */
    protected function createViews()
    {
        parent::createViews();
        $this->createViewsDocument();
        $this->createViewsPeriod();
        $this->createViewsLines();
        $this->createViewsChildren();
        $this->setTabsPosition('left-bottom');
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
            case 'generate-docs':
                $this->generateDocsAction();
                return true;

            case 'generate-manually':
                $this->generateManuallyAction();
                return true;

            case 'duplicate-doc':
                $this->duplicateDocAction();
                return true;

            default:
                return parent::execPreviousAction($action);
        }
    }

    /**
     * Loads the data to display.
     *
     * @param string   $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $mvn = $this->getMainViewName();

        switch ($viewName) {
            case $this->getViewName('Data'):
                $this->setStatusOptions(
                    $viewName,
                    $this->getViewModelValue($mvn, 'generatedoc')
                );
                $view->loadData($this->getViewModelValue($mvn, 'id'));
                $view->count = 0;
                break;


            case $this->getViewName('Period'):
                $view->loadData($this->getViewModelValue($mvn, 'id'));
                $view->count = 0;
                break;

            case $this->getViewName('Line'):
                $view->loadData('', $this->getMainWhere($mvn), ['id' => 'DESC']);
                break;

            case $this->getViewName('Children', 'List'):
                $iddoc = $this->getViewModelValue($mvn, 'id');
                $where = [new DataBaseWhere('id', $iddoc)];
                $view->loadData('', $where);
                $view->count = count($view->cursor);
                break;

            case $mvn:
                parent::loadData($viewName, $view);
                if (empty($view->model->nick)) {
                    $view->model->nick = $this->user->nick;
                }
                $this->addActionButtons($viewName);
                $this->setGenerateModal($viewName);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    protected function createViewsChildren()
    {
        $viewName = $this->getViewName('Children', 'List');
        $modelName = 'Join\\' . $this->getModelClassName() . 'Children';
        $this->addListView($viewName, $modelName, 'documents', 'fas fa-file-invoice');
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'btnDelete', false);
    }

    protected function createViewsDocument()
    {
        $viewName = $this->getViewName('Data');
        $this->addEditView($viewName, $this->getModelClassName(), 'document', 'fas fa-file-invoice');
        $this->setSettings($viewName, 'btnDelete', false);
    }

    /**
     * Add document lines view.
     */
    protected function createViewsLines()
    {
        $this->addEditListView(
            $this->getViewName('Line'),
            $this->getModelClassName() . 'Line',
            'lines',
            'fas fa-tasks'
        );
    }

    protected function createViewsPeriod()
    {
        $viewName = $this->getViewName('Period');
        $this->addEditView($viewName, $this->getModelClassName(), 'period', 'fas fa-clock');
        $this->setSettings($viewName, 'btnDelete', false);
    }

    /**
     * Add actions buttons for:
     *   - manual generate document from template.
     *   - close document
     *
     * @param string $viewName
     */
    private function addActionButtons(string $viewName)
    {
        $id = $this->getViewModelValue($viewName, 'id');
        if (false === empty($id)) {
            $this->addButton($viewName, [
                'action' => 'duplicate-doc',
                'color' => 'info',
                'icon' => 'fas fa-clone',
                'label' => 'clone',
                'type' => 'modal'
            ]);
        }

        $termType = $this->getViewModelValue($viewName, 'termtype');
        if ($termType === DocRecurring::TERM_TYPE_MANUAL) {
            $this->addButton($viewName, [
                'action' => 'generate-manually',
                'color' => 'warning',
                'icon' => 'fas fa-magic',
                'label' => 'generate',
                'type' => 'modal'
            ]);
            return;
        }

        $nextDate = $this->getViewModelValue($viewName, 'nextdate');
        if (!empty($nextDate)) {
            $this->addButton($viewName, [
                'action' => 'generate-docs',
                'color' => 'warning',
                'confirm' => true,
                'icon' => 'fas fa-magic',
                'label' => 'generate'
            ]);
        }
    }

    /**
     *
     * @return string
     */
    private function getViewName(string $suffix = '', string $prefix = 'Edit')
    {
        return $prefix . $this->getModelClassName() . $suffix;
    }

    /**
     *
     * @param string $mainViewName
     *
     * @return DatabaseWhere[]
     */
    private function getMainWhere(string $mainViewName)
    {
        $iddoc = $this->getViewModelValue($mainViewName, 'id');
        return [new DataBaseWhere('iddoc', $iddoc)];
    }

    /**
     *
     * @param string $viewName
     */
    private function setGenerateModal(string $viewName)
    {
        $this->views[$viewName]->model->generatedate = \date(DocRecurring::DATE_STYLE);
        if ($this->views[$viewName]->model->generatedoc == 'FacturaCliente') {
            $this->views[$viewName]->disableColumn('generatedate', false, 'true');
        }
    }

    /**
     *
     * @param string $viewName
     * @param string $doc
     */
    private function setStatusOptions(string $viewName, string $doc)
    {
        $stateColumn = $this->views[$viewName]->columnForName('state');
        if (isset($stateColumn) && $stateColumn->widget->getType() === 'select') {
            $where = [ new DataBaseWhere('tipodoc', $doc) ];
            $rows = CodeModel::all('estados_documentos', 'idestado', 'nombre', true, $where);
            $stateColumn->widget->setValuesFromCodeModel($rows);
        }
    }
}
