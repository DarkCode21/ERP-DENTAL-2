<?php
/**
 * This file is part of Servicios plugin for FacturaScripts
 * Copyright (C) 2021-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\RichiTheme\Controller;

use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Lib\ExtendedController\PanelController;

/**
 * Description of AdminRichiTheme
 * @author Ricard Pros Morell               <ricard@prosite.dev>
 */
class AdminRichiTheme extends PanelController
{

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'RichiTheme';
        $data['icon'] = 'fa-solid fa-cog';
        return $data;
    }

    protected function createViews()
    {
        $this->setTemplate('EditSettings');
        $this->createViewLogin();
        $this->createViewSidebar();
        $this->createViewTopbar();
    }

    private function createViewLogin(string $viewName = 'ConfigLogin'): void
    {
        $this->addEditView($viewName, 'Settings', 'login', 'fas fa-right-to-bracket')
            ->setSettings('btnDelete', false)
            ->setSettings('btnNew', false);

        $this->addResetButton($viewName);
    }

    private function createViewSidebar(string $viewName = 'ConfigSidebar'): void
    {
        $this->addEditView($viewName, 'Settings', 'sidebar', 'fas fa-bars')
            ->setSettings('btnDelete', false)
            ->setSettings('btnNew', false);

        $this->addResetButton($viewName);
    }

    private function createViewTopbar(string $viewName = 'ConfigTopbar'): void
    {
        $this->addEditView($viewName, 'Settings', 'topbar', 'fas fa-window-maximize')
            ->setSettings('btnDelete', false)
            ->setSettings('btnNew', false);

        $this->addResetButton($viewName);
    }

    private function addResetButton(string $viewName): void
    {
        $this->addButton($viewName, [
            'action' => 'reset-defaults',
            'color' => 'warning',
            'icon' => 'fas fa-undo',
            'label' => 'restore-defaults',
            'type' => 'action'
        ]);
    }

    /**
     * Loads the data to display.
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
      $view->loadData('richitheme');
      $view->model->name = 'richitheme';
    }

    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'reset-defaults':
                return $this->resetAllDefaultsAction();
        }

        return parent::execPreviousAction($action);
    }

    private function resetAllDefaultsAction(): bool
    {
        $viewName = $this->active ?? 'ConfigLogin';
        $view = $this->views[$viewName];
        $model = $view->model;

        $model->loadFromCode('', [new DataBaseWhere('name', 'richitheme')]);

        $model->name = 'richitheme';

        switch ($viewName) {
            case 'ConfigLogin':
                $model->lbgcolor = '#333a40';
                $model->fbgcolor = '#ffffff';
                $model->lbtncolor = '#2770ca';
                $model->lfoodis = 0;
                $model->lrpdis = 0;
                break;

            case 'ConfigSidebar':
                $model->sbgcolor = '#f7f7f7';
                $model->subbgcolor = '#ffffff';
                $model->itembgcolor = '#edf5fc';
                $model->accentcolor = '#000000';
                $model->stxtcolor = '#444b52';
                $model->lsfile = 0;
                break;

            case 'ConfigTopbar':
                $model->tbgcolor = '#ffffffb3';
                $model->ttxtcolor = '#1a1f36';
                $model->itxtcolor = '#6c757d';
                $model->uigradient = '#2770ca';
                $model->tcndis = 0;
                break;
        }

        if ($model->save()) {
            Tools::log()->notice('default-values-restored');
            return true;
        }

        Tools::log()->error('error-restoring-defaults');
        return false;
    }
}
