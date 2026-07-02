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
use FacturaScripts\Plugins\HumanResources\Model\EmployeeWorkPeriod;

/**
 * Controler to edit Employee Work Shift.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditEmployeeWorkShift extends EditController
{

    private const VIEW_WORKPERIOD = 'EditEmployeeWorkPeriod';

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'EmployeeWorkShift';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['menu'] = 'rrhh';
        $pagedata['title'] = 'work-shift';
        $pagedata['icon'] = 'fa-solid fa-business-time';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    /**
     * Create the view to display.
     */
    protected function createViews()
    {
        parent::createViews();
        $this->createViewsWorkPeriod();
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
            case 'workinghours':
                return $this->createWorkPeriod();

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
            case $mvn:
                parent::loadData($viewName, $view);
                $view->disableColumn('code', true);  // Force disable PK column
                break;

            case self::VIEW_WORKPERIOD:
                $idworkshift = $this->getViewModelValue($mvn, 'id');
                $where = [ new DataBaseWhere('idworkshift', $idworkshift)];
                $order = [ 'dayweek' => 'ASC', 'starttime' => 'ASC'];
                $view->loadData('', $where, $order, 0, 0);
                break;
        }
    }

    /**
     *
     * @param string $viewName
     */
    private function createViewsWorkPeriod(string $viewName = self::VIEW_WORKPERIOD)
    {
        $this->addEditListView($viewName, 'EmployeeWorkPeriod', 'working-hours', 'far fa-calendar-alt');
        $this->views[$viewName]->setInLine(true);
    }

    private function createWorkPeriod(): bool
    {
        $data = $this->request->request->all();
        if (empty($data['code'])) {
            return true;
        }

        $period = new EmployeeWorkPeriod();
        for ($day = (int)$data['_startdayweek']; $day <= (int)$data['_enddayweek']; ++$day) {
            $where = [
                new DataBaseWhere('idworkshift', $data['code']),
                new DataBaseWhere('dayweek', $day),
                new DataBaseWhere('starttime', $data['_starttime']),
            ];
            if ($period->loadFromCode('', $where)) {
                continue;
            }

            $period->idworkshift = $data['code'];
            $period->dayweek = $day;
            $period->starttime = $data['_starttime'];
            $period->endtime = $data['_endtime'];
            $period->save();
        }
        return true;
    }
}
