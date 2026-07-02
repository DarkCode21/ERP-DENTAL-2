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
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Lib\AssetManager;
use FacturaScripts\Dinamic\Lib\ExtendedController\BaseView;

/**
 * Controller to edit Attendance.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditAttendance extends EditController
{

    private const VIEW_AUDIT = 'ListAttendanceAudit';
    private const VIEW_AUDIT_INFO = 'AuditInfo';

    /**
     * Create the view to display.
     *   - Add CSS and JS files for geolocation and map view.
     */
    public function createViews(): void
    {
        parent::createViews();
        $this->createViewsAudit();
        $this->createViewsAuditLog();
        $this->setTabsPosition('left-bottom');

        AssetManager::add('css', FS_ROUTE . '/Dinamic/Assets/CSS/leaflet.css');
        AssetManager::add('css', FS_ROUTE . '/Dinamic/Assets/CSS/Control.Geocoder.css');

        AssetManager::add('js', FS_ROUTE . '/Dinamic/Assets/JS/geolocation.js');
        AssetManager::add('js', FS_ROUTE . '/Dinamic/Assets/JS/leaflet.js');
        AssetManager::add('js', FS_ROUTE . '/Dinamic/Assets/JS/Control.Geocoder.js');
        AssetManager::add('js', FS_ROUTE . '/Dinamic/Assets/JS/map.js');
    }

    private function createViewsAudit(): void
    {
        $this->addHtmlView(self::VIEW_AUDIT_INFO, 'Tab\AuditInfo', 'Attendance', 'audit-legend', 'fa-solid fa-person-circle-question');
    }

    private function createViewsAuditLog(): void
    {
        $this->addListView(self::VIEW_AUDIT, 'AttendanceAudit', 'history', 'fa-solid fa-list')
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false)
            ->addOrderBy(['dateaction', 'id'], 'date')
            ->addOrderBy(['id'], 'code');
    }

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'Attendance';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'attendances';
        $pagedata['icon'] = 'fa-solid fa-clock';
        $pagedata['menu'] = 'rrhh';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    /**
     * Action to delete data.
     * Sets the reason for deletion from the request.
     *
     * @return bool
     */
    protected function deleteAction()
    {
        $this->views[$this->getMainViewName()]->model->reason = $this->request->request->get('reason', '');
        return parent::deleteAction();
    }

    /**
     * Loads the data to display.
     * Force disable reason column when creating a new attendance.
     *
     * @param string   $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case self::VIEW_AUDIT:
                $where = [ new DataBaseWhere('idattendance', $this->getModel()->id) ];
                $view->loadData('', $where);
                break;

            case self::VIEW_AUDIT_INFO:
                $view->model = $this->getModel();
                break;

            default:
                parent::loadData($viewName, $view);
                $view->disableColumn('code', true);  // Force disable PK column
                if (empty($this->getModel()->primaryColumnValue())) {
                    $view->disableColumn('reason', false, "true");
                }
        }
    }
}
