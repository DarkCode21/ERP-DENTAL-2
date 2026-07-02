<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * Controller to edit Training Course.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditCourseTraining extends EditController
{
    private const VIEW_NOTES = 'EditCourseTrainingNote';
    private const VIEW_EMPLOYEES = 'EditEmployeeCourse';

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'CourseTraining';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'course';
        $pagedata['icon'] = 'fa-solid fa-book';
        $pagedata['menu'] = 'rrhh';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    /**
     * Create the view to display.
     */
    protected function createViews()
    {
        parent::createViews();
        $this->createViewsNotes();
        $this->createViewsEmployees();
        $this->setTabsPosition('left-bottom');
    }

    /**
     * Loads the data to display.
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case self::VIEW_EMPLOYEES:
                $idcourse = $this->getModel()->id;
                $where = [ new DataBaseWhere('idcourse', $idcourse) ];
                $view->loadData('', $where);
                break;

            case self::VIEW_NOTES:
                $view->model = $this->getModel();
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    private function createViewsEmployees()
    {
        $view = $this->addEditListView(self::VIEW_EMPLOYEES, 'EmployeeCourse', 'employees', 'fa-solid fa-id-card');
        $view->setInLine(true);
        $view->disableColumn('course');
    }

    private function createViewsNotes()
    {
        $this->addEditView(self::VIEW_NOTES, 'CourseTraining', 'notes', 'fa-solid fa-sticky-note');
        $this->setSettings(self::VIEW_NOTES, 'btnDelete', false);
    }
}
