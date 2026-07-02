<?php

/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditVerifactuRequerimiento extends EditController
{
    protected function createViews()
    {
        $this->setTabsPosition('bottom');
        parent::createViews();
        $this->tab($this->getMainViewName())->setSettings('btnNew', false);
        $this->createViewsVerifactuRequerimientoLine();
    }

    protected function createViewsVerifactuRequerimientoLine(string $viewName = 'ListVerifactuRequerimientoLine'): void
    {
        $this->addListView($viewName, 'VerifactuRequerimientoLine', 'lines', 'fa-solid fa-list')
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false)
            ->setSettings('checkBoxes', false)
            ->setSettings('clickable', false);
    }

    public function getModelClassName(): string
    {
        return 'VerifactuRequerimiento';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'requirement';
        $data['icon'] = 'fa-solid fa-gavel';
        return $data;
    }

    protected function loadData($viewName, $view)
    {
        $mvn = $this->getMainViewName();
        if ($viewName === 'ListVerifactuRequerimientoLine') {
            $where = [new DataBaseWhere('id_requerimiento', $this->getViewModelValue($mvn, 'id'))];
            $view->loadData('', $where, ['id' => 'DESC']);
            return;
        }

        parent::loadData($viewName, $view);
    }
}
