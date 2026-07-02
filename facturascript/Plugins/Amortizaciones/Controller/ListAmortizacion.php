<?php
/**
 * This file is part of Amortizaciones plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Amortizaciones  Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\Amortizaciones\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Dinamic\Lib\Accounting\AmortizationPlanToAccounting;

/**
 * Controller for Amortize model
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class ListAmortizacion extends ListController
{

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'amortization';
        $pagedata['icon'] = 'fas fa-piggy-bank';
        $pagedata['menu'] = 'accounting';
        return $pagedata;
    }

    /**
     * Load views
     */
    protected function createViews()
    {
        $this->createViewAmortization();
        $this->createViewAmortizationPending();
        $this->createViewAmortizationTemplate();
        $this->createViewAmortizationTable();
        $this->createViewAmortizationSubAccount();
    }

    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'contabilize':
                if ($this->validateFormToken()) {
                    $codes = $this->request->request->get('code', []);
                    AmortizationPlanToAccounting::exec($codes);
                }
                return true;

            default:
                return parent::execPreviousAction($action);
        }
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'ListLineaAmortizacion':
                $where = [
                    new DataBaseWhere('ano', date('Y'), '<='),
                    new DataBaseWhere('idasiento', null),
                ];
                $view->loadData('', $where);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    /**
     *
     * @param string $viewName
     */
    private function createViewAmortization($viewName = 'ListAmortizacion')
    {
        $this->addView($viewName, 'Join\Amortizacion', 'amortization', 'fas fa-piggy-bank');
        $this->addSearchFields($viewName, ['amortizaciones.descripcion']);

        $this->addOrderBy($viewName, ['amortizaciones.idamortizacion'], 'code');
        $this->addOrderBy($viewName, ['amortizaciones.descripcion'], 'description');

        // disable company column if there is only one company
        $companies = Empresas::codeModel();
        if (count($companies) > 2) {
            $this->addFilterSelect($viewName, 'idempresa', 'company', 'amortizaciones.idempresa', $companies);
            $this->views[$viewName]->disableColumn('company', false);
        }
    }

    /**
     *
     * @param $viewName
     */
    private function createViewAmortizationPending($viewName = 'ListLineaAmortizacion')
    {
        $this->addView($viewName, 'LineaAmortizacion', 'pending', 'fas fa-list');
        $this->addOrderBy($viewName, ['ano', 'periodo', 'idamortizacion'], 'period');
        $this->addOrderBy($viewName, ['idamortizacion', 'ano', 'periodo'], 'amortization');

        $this->addFilterPeriod($viewName, 'date', 'date', 'fecha');
        $this->addFilterAutocomplete($viewName, 'amortization', 'amortization', 'idamortizacion', 'amortizaciones', 'idamortizacion', 'descripcion');

        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'clickable', false);
        $this->addButton($viewName, [
            'action' => 'contabilize',
            'label' => 'contabilization',
            'color' => 'warning',
            'icon' => 'fas fa-cogs',
            'confirm' => true,
        ]);
    }

    /**
     *
     * @param string $viewName
     */
    private function createViewAmortizationTable($viewName = 'ListAmortizacionTabla')
    {
        $this->addView($viewName, 'AmortizacionTabla', 'legal-periods', 'fas fa-list-alt');
        $this->addSearchFields($viewName, ['name']);
    }

    /**
     * @param $viewName
     */
    private function createViewAmortizationTemplate($viewName = 'ListAmortizacionPlantilla')
    {
        $this->addView($viewName, 'AmortizacionPlantilla', 'templates', 'fas fa-paste');
        $this->addSearchFields($viewName, ['name']);

        $this->addOrderBy($viewName, ['id'], 'code');
        $this->addOrderBy($viewName, ['name', 'id'], 'description', 1);
    }

    /**
     * @param $viewName
     */
    private function createViewAmortizationSubAccount($viewName = 'ListAmortizacionSubcuenta')
    {
        $this->addView($viewName, 'AmortizacionSubcuenta', 'accounts', 'fas fa-list-alt');
        $this->addSearchFields($viewName, ['code']);
    }
}
