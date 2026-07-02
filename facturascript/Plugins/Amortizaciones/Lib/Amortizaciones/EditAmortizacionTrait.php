<?php
/**
 * This file is part of Amortizaciones plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Amortizaciones  Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\Amortizaciones\Lib\Amortizaciones;

use Exception;
use FacturaScripts\Core\Lib\AssetManager;
use FacturaScripts\Dinamic\Model\Amortizacion;

/**
 * Auxiliar code for EditAmortizacion Controller.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
trait EditAmortizacionTrait
{

    /**
     *
     * @param Amortizacion $model
     * @param bool $hasLines
     * @throws Exception
     */
    private function addMainButtons(Amortizacion $model, bool $hasLines)
    {
        $isBanking = $model->tipo === Amortizacion::TYPE_BANKING;
        $mvn = $this->getMainViewName();
        if (false === $hasLines) {
            $this->addButton($mvn, [
                'action' => 'generate',
                'label' => 'create-plan',
                'color' => 'info',
                'icon' => 'fas fa-cogs',
                'type' => $isBanking ? 'action' : 'modal',
                'confirm' => $isBanking ? 'true' : 'false',
            ]);
        }

        if (false === $isBanking) {
            $this->addButton($mvn, [
                'action' => 'sell',
                'label' => 'sell',
                'color' => 'danger',
                'icon' => 'fas fa-donate',
                'type' => 'modal',
            ]);

            $this->addButton($mvn, [
                'action' => 'finalize',
                'label' => 'end-useful-life',
                'color' => 'danger',
                'icon' => 'far fa-calendar-times',
                'type' => 'modal',
            ]);
        }
    }

    /**
     * Add accounting view to the controller.
     *
     * @param string $viewName
     */
    private function createViewsAccounts(string $viewName = self::VIEW_ACCOUNTS)
    {
        $this->addEditView($viewName, 'Amortizacion', 'subaccounts', 'fas fa-book');
        $this->setSettings($viewName, 'btnDelete', false);
    }

    /**
     * Add contabilization view to the controller.
     *
     * @param string $viewName
     */
    private function createViewsDetail(string $viewName = self::VIEW_AMORTIZATION)
    {
        $this->addEditView($viewName, 'Amortizacion', 'contabilization', 'fas fa-sitemap');
        $this->setSettings($viewName, 'btnDelete', false);
    }

    /**
     * Add amortization plan view to the controller.
     *
     * @param string $viewName
     */
    private function createViewLines(string $viewName = self::VIEW_LINES)
    {
        $view = $this->addListView($viewName, 'LineaAmortizacion', 'lines', 'fas fa-list');
        $view->setSettings('clickable', false);
        $view->setSettings('modalInsert', 'insertline');

        $view->disableColumn('amortization');
        $view->disableColumn('description');

        AssetManager::add('js', FS_ROUTE . '/Dinamic/Assets/JS/' . $viewName . '.js');
    }

    /**
     * Add notes view to the controller.
     *
     * @param string $viewName
     */
    private function createViewsNote(string $viewName = self::VIEW_NOTE)
    {
        $this->addEditView($viewName, 'Amortizacion', 'notes', 'fas fa-sticky-note');
        $this->setSettings($viewName, 'btnDelete', false);
    }

    /**
     * Configure the views depending on the amortization type.
     *   - if the amortization has no lines, remove the amortization plan view.
     *   - If the amortization has lines, the amortization data is disabled or readonly.
     *   - If the amortization is banking, show the banking columns.
     *
     * @param bool $hasLines
     */
    private function setStatusToViews(bool $hasLines)
    {
        if (false === $hasLines) {
            unset($this->views[self::VIEW_LINES]);
            return;
        }

        $this->views[self::VIEW_AMORTIZATION]->setReadOnly(true);
        $viewMain = $this->views[$this->getMainViewName()];
        $viewMain->disableColumn('type', false, 'true');
        $viewMain->disableColumn('start-date', false, 'true');
        $viewMain->disableColumn(
            'company',
            ($this->empresa->count() < 2),
            'true'
        );


        if ($viewMain->model->tipo === Amortizacion::TYPE_BANKING) {
            $viewLines = $this->views[self::VIEW_LINES];
            $totalColumn = $viewLines->columnForField('capital');
            if ($totalColumn) {
                $totalColumn->display = 'right';
            }
            $interestColumn = $viewLines->columnForField('interes');
            if ($interestColumn) {
                $interestColumn->display = 'right';
            }

            $viewAccounts = $this->views[self::VIEW_ACCOUNTS];
            $viewAccounts->disableColumn('codsubcuentacierre');
            $viewAccounts->disableColumn('codsubcuentabeneficios');
            $viewAccounts->disableColumn('codsubcuentaperdidas');
            $viewAccounts->disableColumn('codsubcuentainteres', false);
            $DebitColumn = $viewAccounts->columnForField('codsubcuentadebe');
            if ($DebitColumn) {
                $DebitColumn->title = 'subaccount-capital';
            }
            $CreditColumn = $viewAccounts->columnForField('codsubcuentahaber');
            if ($CreditColumn) {
                $CreditColumn->title = 'subaccount-bank';
            }
        }
    }
}