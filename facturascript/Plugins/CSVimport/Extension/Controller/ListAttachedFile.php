<?php
/**
 * Copyright (C) 2020-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CSVfile;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class ListAttachedFile
{
    public function createViews(): Closure
    {
        return function () {
            $profiles = $this->codeModel->all('csv_files', 'profile', 'profile');
            foreach ($profiles as $profile) {
                $profile->description = Tools::lang()->trans($profile->description);
            }

            $this->addView('ListCSVfile', 'CSVfile', 'csv', 'fas fa-file-csv')
                ->addOrderBy(['date', 'id'], 'date', 2)
                ->addOrderBy(['size'], 'size')
                ->addSearchFields(['name', 'url', 'template'])
                ->addFilterSelect('profile', 'template', 'profile', $profiles)
                ->addFilterSelectWhere('remote', [
                    ['label' => Tools::lang()->trans('all'), 'where' => []],
                    ['label' => '------', 'where' => []],
                    ['label' => Tools::lang()->trans('remote'), 'where' => [new DataBaseWhere('url', '', '!=')]],
                    ['label' => Tools::lang()->trans('not-remote'), 'where' => [new DataBaseWhere('url', '')]]
                ])
                ->addFilterSelectWhere('scheduled', [
                    ['label' => Tools::lang()->trans('all'), 'where' => []],
                    ['label' => '------', 'where' => []],
                    ['label' => Tools::lang()->trans('scheduled'), 'where' => [new DataBaseWhere('expiration', 0, '>')]],
                    ['label' => Tools::lang()->trans('not-scheduled'), 'where' => [new DataBaseWhere('expiration', 0)]]
                ]);
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName !== 'ListCSVfile') {
                return;
            }

            $this->loadProfileValues($viewName);
        };
    }

    protected function loadProfileValues(): Closure
    {
        return function (string $viewName) {
            $column = $this->views[$viewName]->columnForName('profile');
            if ($column && $column->widget->getType() === 'select') {
                $values = [];
                foreach (CSVfile::getManualTemplates() as $key => $value) {
                    $values[] = ['value' => $key, 'title' => Tools::lang()->trans($key)];
                }

                $column->widget->setValuesFromArray($values);
            }
        };
    }
}
