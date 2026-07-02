<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

/**
 * Controller to list the items for the Training Course model.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class ListCourseTraining extends ListController
{

    private const VIEW_AREA = 'ListCourseArea';
    private const VIEW_COST = 'ListCourseCost';
    private const VIEW_COURSES = 'ListCourseTraining';
    private const VIEW_METHOD = 'ListCourseMethod';
    private const VIEW_OBJECTIVE = 'ListCourseObjective';

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'training-courses';
        $pagedata['icon'] = 'fa-solid fa-graduation-cap';
        $pagedata['menu'] = 'rrhh';

        return $pagedata;
    }

    /**
     * Load views
     */
    protected function createViews()
    {
        $this->createViewCourse();
        $this->createViewArea();
        $this->createViewCost();
        $this->createViewMethod();
        $this->createViewObjetive();
    }

    private function createViewCourse(string $viewName = self::VIEW_COURSES): void
    {
        $this->addView($viewName, 'CourseTraining', 'courses', 'fa-solid fa-book');
    }

    private function createViewArea(string $viewName = self::VIEW_AREA): void
    {
        $this->addView($viewName, 'CourseArea', 'areas', 'fa-solid fa-circle-nodes');
    }
    private function createViewCost(string $viewName = self::VIEW_COST): void
    {
        $this->addView($viewName, 'CourseCost', 'cost', 'fa-solid fa-hand-holding-dollar');
    }
    private function createViewMethod(string $viewName = self::VIEW_METHOD): void
    {
        $this->addView($viewName, 'CourseMethod', 'methods', 'fa-solid fa-person-dots-from-line');
    }
    private function createViewObjetive(string $viewName = self::VIEW_OBJECTIVE): void
    {
        $this->addView($viewName, 'CourseObjective', 'objectives', 'fa-solid fa-arrow-down-up-across-line');
    }
}