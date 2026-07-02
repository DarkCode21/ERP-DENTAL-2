<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Controller;

use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Employee;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ReportController;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ReportView;

/**
 * Report Summary of Attendance
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class ReportAttendance extends ReportController
{

    private const VIEW_ATTENDANCES = 'ReportAttendance';
    private const VIEW_INCIDENCES = 'ReportIncidence';
    private const VIEW_SUMMARY = 'ReportAttendanceSummary';

    /**
     * Get employee name.
     *
     * @param int $idemployee
     * @return string
     */
    public function employeeName(int $idemployee): string
    {
        if (empty($idemployee)) {
            return '';
        }

        $employee = new Employee();
        $employee->loadFromCode($idemployee);
        return $employee->nombre;
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'reports';
        $pagedata['icon'] = 'fa-solid fa-file-alt';
        $pagedata['menu'] = 'rrhh';

        return $pagedata;
    }

    /**
     * Add Report Views
     *
     * @throws Exception
     */
    protected function createViews()
    {
        $this->createViewSummary();
        $this->createViewAttendance();
        $this->createViewIncidence();
    }

    /**
     * Load data for active view. For greater performance, only for active view
     * Load data when: exec export action or load saved filter.
     *
     * @param string $viewName
     * @param ReportView $view
     * @throws Exception
     */
    protected function loadData($viewName, $view) {
        if (false === $this->mustLoadData($viewName)) {
            return;
        }

        if ($viewName === self::VIEW_ATTENDANCES) {
            $view->model->codes = $this->request->request->get('code', []);
        }

        parent::loadData($viewName, $view);

        if ($viewName === self::VIEW_SUMMARY && $view->count > 0) {
            $this->addButton($viewName, [
                'action' => "reportAttendance('" . $viewName . "')",
                'icon' => 'fa-solid fa-user-clock',
                'label' => 'detail',
                'type' => 'js',
                'color' => 'info',
            ]);
        }
    }

    /**
     * Add Date and Employee filter to view
     *
     * @param string $viewName
     */
    private function addFiltersBase(string $viewName)
    {
        $this->addFilterPeriod($viewName, 'date', 'date', 'date');
        $this->addFilterAutocomplete($viewName, 'employee', 'employee', 'employee', 'Employee', 'id', 'nombre');
        $values = [
            ['label' => Tools::lang()->trans('only-active'), 'where' => [new DataBaseWhere('dischargedate', NULL), new DataBaseWhere('dischargedate', date('Y-m-d'), '>=', 'OR')]],
            ['label' => Tools::lang()->trans('only-suspended'), 'where' => [new DataBaseWhere('dischargedate', NULL, 'IS NOT')]],
            ['label' => Tools::lang()->trans('all'), 'where' => []]
        ];
        $this->addFilterSelectWhere($viewName, 'status', $values);
    }

    /**
     * Add and configure Attendance Report
     *
     * @throws Exception
     */
    private function createViewAttendance(string $viewName = self::VIEW_ATTENDANCES)
    {
        $this->addView($viewName, 'AttendanceReport', 'attendances', 'fa-solid fa-clock');
        $this->addOrderBy($viewName, ['checkdate', 'checktime'], 'date');
        $this->addFiltersBase($viewName);
    }

    /**
     * Add and configure Incidence Report
     *
     * @throws Exception
     */
    private function createViewIncidence(string $viewName = self::VIEW_INCIDENCES)
    {
        $this->addView($viewName, 'AttendanceIncidenceReport', 'incidences', 'fa-solid fa-exclamation-triangle');
        $this->addOrderBy($viewName, ['id'], 'employee');
        $this->addFiltersBase($viewName);
    }

    /**
     * Add and configure Summary Report
     * Set detail view data into templateData
     *
     * @throws Exception
     */
    private function createViewSummary(string $viewName = self::VIEW_SUMMARY)
    {
        $this->addView($viewName, 'AttendanceSummaryReport', 'attendances-summary', 'fa-solid fa-object-group');
        $this->views[$viewName]->templateData = $viewName . 'Data.html.twig';
        $this->addOrderBy($viewName, ['id'], 'employee');
        $this->addOrderBy($viewName, ['nombre'], 'name');
        $this->addFiltersBase($viewName);
    }
}
