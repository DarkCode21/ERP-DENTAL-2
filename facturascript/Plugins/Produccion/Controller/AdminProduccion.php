<?php
/**
 * This file is part of the Produccion plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 * All Rights Reserved.
 */
namespace FacturaScripts\Plugins\Produccion\Controller;

use Exception;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ExtendedController\BaseView;
use FacturaScripts\Dinamic\Lib\ExtendedController\PanelController;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\MigrateReference;

/**
 * Controller to admin Produccion config and basic data
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AdminProduccion extends PanelController
{
    private const VIEW_CONFIG_PRODUCTION = 'ConfigProduction';
    private const VIEW_NUMSERIE_COUNTER = 'ListNumSerieCounter';

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'production';
        $pageData['icon'] = 'fa-solid fa-clipboard-list';
        $pageData['menu'] = 'admin';
        return $pageData;
    }

    /**
     * Load views
     *
     * @throws Exception
     */
    protected function createViews()
    {
        $this->setTemplate('EditSettings');
        $this->createViewEditConfig();
        $this->createViewNumSerieCounter();
    }

    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'migrate-reference':
                $this->migrateReferenceAction();
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
        switch ($viewName) {
            case self::VIEW_CONFIG_PRODUCTION:
                $view->loadData('production');
                $view->model->name = 'production';
                break;

            case self::VIEW_NUMSERIE_COUNTER:
                $view->loadData('', [], ['idfamily' => 'ASC', 'idproduct' => 'ASC']);
                break;
        }
    }

    /**
     * Add the configuration view for production settings
     *
     * @return void
     * @throws Exception
     */
    private function createViewEditConfig(): void
    {
        $this->addEditView(self::VIEW_CONFIG_PRODUCTION, 'Settings', 'general')
            ->setSettings('btnDelete', false)
            ->setSettings('btnNew', false);

        $this->addButton(self::VIEW_CONFIG_PRODUCTION, [
                'action' => 'migrate-reference',
                'color' => 'warning',
                'icon' => 'fa-solid fa-gears',
                'label' => 'migrate-reference',
                'confirm' => 'true'
            ]);
    }

    /**
     * Add the view to manage the numserie counter
     *
     * @return void
     */
    private function createViewNumSerieCounter(): void
    {
        $this->addListView(self::VIEW_NUMSERIE_COUNTER, 'NumSerieCounter', 'numserie-counter', 'fa-solid fa-code')
            ->addFilterAutocomplete('idfamily', 'family', 'idfamily', 'familias', 'codfamilia', 'descripcion')
            ->addFilterAutocomplete('idproduct', 'product', 'idproduct', 'productos', 'idproducto', 'concat(referencia, \' - \', descripcion)')
            ->addOrderBy(['idfamily', 'idproduct'], 'family')
            ->addOrderBy(['idproduct'], 'product');
    }

    /**
     * Migrate references from old production orders to new production orders
     *
     * @return void
     */
    private function migrateReferenceAction(): void
    {
        $migrateReference = new MigrateReference();
        $count = $migrateReference->run();
        switch ($count) {
            case -1:
                break;

            case 0:
                Tools::log()->error('reference-migrate-error');
                break;

            default:
                Tools::log()->notice('reference-migrate-complete', ['%count%' => $count]);
                break;
        }
    }
}
