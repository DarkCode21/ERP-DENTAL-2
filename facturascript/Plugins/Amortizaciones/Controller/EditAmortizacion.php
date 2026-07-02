<?php
/**
 * This file is part of Amortizaciones plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Amortizaciones  Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\Amortizaciones\Controller;

use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ExtendedController\BaseView;
use FacturaScripts\Dinamic\Model\Amortizacion;
use FacturaScripts\Dinamic\Model\AmortizacionPlantilla;
use FacturaScripts\Plugins\Amortizaciones\Lib\Accounting\AmortizationPlanToAccounting;
use FacturaScripts\Plugins\Amortizaciones\Lib\Amortizaciones\AmortizationPlan;
use FacturaScripts\Plugins\Amortizaciones\Lib\Amortizaciones\AmortizacionFinalizar;
use FacturaScripts\Plugins\Amortizaciones\Lib\Amortizaciones\AmortizacionVender;
use FacturaScripts\Plugins\Amortizaciones\Lib\Amortizaciones\AmortizationInfo;
use FacturaScripts\Plugins\Amortizaciones\Lib\Amortizaciones\EditAmortizacionAction;
use FacturaScripts\Plugins\Amortizaciones\Lib\Amortizaciones\EditAmortizacionTrait;

/**
 * Controller to edit Amortize.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditAmortizacion extends EditController
{
    private const VIEW_ACCOUNTS = 'EditAmortizacionCuentas';
    private const VIEW_AMORTIZATION = 'EditAmortizacionDetalle';
    private const VIEW_LINES = 'ListLineaAmortizacion';
    private const VIEW_NOTE = 'EditAmortizacionNota';

    use EditAmortizacionTrait;

    /** @var AmortizationInfo */
    public $infoView;

    /**
     * Returns the class name of the model to use in the editView.
     */
    public function getModelClassName(): string
    {
        return 'Amortizacion';
    }

    /**
     * Return the basic data for this page.
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
     * Run the autocomplete action.
     * Returns a JSON string for the searched values.
     *
     * @return array
     */
    protected function autocompleteAction(): array
    {
        $data = $this->request->request->all();
        if ($data['source'] === 'asientos') {
            return EditAmortizacionAction::exec('autocomplete-entry', $data);
        }
        return parent::autocompleteAction();
    }

    /**
     * Create the view to display.
     *   - Create main view and set template.
     *   - Create info view.
     *   - Create lines of the amortization view.
     *   - Create accounting detail of the amortization view.
     *   - Create note of the amortization view.
     * If there is more than one company, the company column is active.
     */
    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('left-bottom');

        $this->infoView = new AmortizationInfo();
        $this->createViewLines();
        $this->createViewsDetail();
        $this->createViewsAccounts();
        $this->createViewsNote();

        // active company column if there are more than one
        if ($this->empresa->count() > 1) {
            $this->views[$this->getMainViewName()]->disableColumn('company', false);
        }
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
            case 'contabilize':
                if ($this->checkRequestToken()) {
                    $codes = $this->request->request->get('code', []);
                    AmortizationPlanToAccounting::exec($codes);
                }
                return true;

            case 'invoice-data':
            case 'line-data':
                $this->setTemplate(false);
                $data = $this->request->request->all();
                $result = EditAmortizacionAction::exec($action, $data);
                $this->response->setContent(json_encode($result));
                return false;

            case 'editline':
            case 'insertline':
                if ($this->checkRequestToken()) {
                    $data = $this->request->request->all();
                    EditAmortizacionAction::exec($action, $data);
                }
                return true;

            case 'finalize':
                if ($this->checkRequestToken()) {
                    $data = $this->request->request->all();
                    AmortizacionFinalizar::exec($data);
                }
                return true;

            case 'generate':
                if ($this->checkRequestToken()) {
                    $data = $this->request->request->all();
                    AmortizationPlan::exec($data);
                }
                return true;

            case 'template':
                if ($this->checkRequestToken()) {
                    $data = $this->request->request->all();
                    $this->setTemplateToAmortization($data);
                }
                return true;

            case 'sell':
                if ($this->checkRequestToken()) {
                    $data = $this->request->request->all();
                    AmortizacionVender::exec($data);
                }
                return true;
        }
        return parent::execPreviousAction($action);
    }

    /**
     * Loads the data to display.
     *
     * @param string $viewName
     * @param BaseView $view
     * @throws Exception
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case self::VIEW_ACCOUNTS:
            case self::VIEW_NOTE:
                $view->model = $this->getModel();
                break;

            case self::VIEW_AMORTIZATION:
                $view->model = $this->getModel();
                $this->setViewColumnsFromType($view);
                break;

            case self::VIEW_LINES:
                if (false === isset($this->views[$viewName])) {
                    return;
                }

                $mvn = $this->getMainViewName();
                $idamortizacion = $this->getViewModelValue($mvn, 'idamortizacion');
                $idasientofinvida = $this->getViewModelValue($mvn, 'idasientofinvida');
                $view->setSettings('btnDelete', empty($idasientofinvida));

                $where = [ new DataBaseWhere('idamortizacion', $idamortizacion) ];
                $view->loadData('', $where, ['ano' => 'ASC', 'periodo' => 'ASC']);
                if (empty($idasientofinvida)) {
                    $this->addButton($viewName, [
                        'action' => 'contabilize',
                        'label' => 'contabilization',
                        'color' => 'warning',
                        'icon' => 'fas fa-cogs',
                        'confirm' => true,
                    ]);
                }
                break;

            default:
                parent::loadData($viewName, $view);
                if ($viewName === $this->getMainViewName() && $view->model->idamortizacion !== null) {
                    $lines = $view->model->getLines();
                    $hasLines = count($lines) > 0;
                    $this->setStatusToViews($hasLines);
                    if (false === empty($view->model->idasientofinvida)) {
                        $view->setReadOnly(true);
                        return;
                    }
                    $this->setModalValues($view->model, $lines, $hasLines);
                    $this->addMainButtons($view->model, $hasLines);
                    $this->addDetailButtons($view->model, $hasLines);
                }
        }
    }

    /**
     *
     * @param Amortizacion $model
     * @param bool $hasLines
     * @throws Exception
     */
    private function addDetailButtons(Amortizacion $model, bool $hasLines)
    {
        if (false === $hasLines && $model->tipo === Amortizacion::TYPE_CONSTANT) {
            $this->addButton(self::VIEW_AMORTIZATION, [
                'action' => 'template',
                'label' => 'template',
                'color' => 'info',
                'icon' => 'fas fa-paste',
                'type' => 'modal',
            ]);
        }
    }

    /**
     * Check if the request token exists and is valid.
     *
     * @return bool
     */
    private function checkRequestToken(): bool
    {
        if ($this->multiRequestProtection->tokenExist($this->request->request->get('multireqtoken', ''))) {
            Tools::log()->warning('duplicated-request');
            return false;
        }
        return true;
    }

    /**
     * @param Amortizacion $model
     * @param array $lines
     * @param bool $hasLines
     * @return void
     */
    private function setModalValues(Amortizacion $model, array $lines, bool $hasLines): void
    {
        // finalize modal
        $model->finalize_date = date(Amortizacion::DATE_STYLE);
        $model->amortized = $hasLines ? $model->getTotalAmortized() : 0.00;
        $model->pending = round($model->residual, FS_NF0);

        // sell modal
        $model->sell_date = date(Amortizacion::DATE_STYLE);
        $model->sell_amount = round($model->residual, FS_NF0);
        $model->sales_serie = Tools::settings('default', 'codserie');

        // Lines modals
        if (isset($this->views[self::VIEW_LINES])) {
            $viewLines = $this->views[self::VIEW_LINES];
            $viewLines->model->idamortizacion = $model->idamortizacion;
            $viewLines->model->ano = date('Y', strtotime($model->fechainicio));
            $viewLines->model->periodo = 1;

            $years = $viewLines->columnModalForName('insert-year');
            if ($years && $years->widget->getType() === 'select') {
                $start = (int)date('Y', strtotime($model->fechainicio));
                $end = $start + $model->periodos;
                $years->widget->setValuesFromRange($start, $end, 1);
            }

            $period = $viewLines->columnModalForName('insert-period');
            if ($period && $period->widget->getType() === 'select') {
                $period->widget->setValuesFromRange(1, $model->amortizationsByPeriod(), 1);
            }
        }
    }

    /**
     * @param array $data
     * @return void
     */
    private function setTemplateToAmortization(array $data): void
    {
        $idAmortization = $data['idamortizacion'] ?? 0;
        $idTemplate = $data['template_id'] ?? 0;

        $amortization = new Amortizacion();
        $template = new AmortizacionPlantilla();
        if (false === $amortization->loadFromCode($idAmortization)
            || false === $template->loadFromCode($idTemplate))
        {
            return;
        }

        $amortization->periodos = $template->periods;
        $amortization->codsubcuentabeneficios = $template->benefits_subaccount;
        $amortization->codsubcuentacierre = $template->closing_subaccount;
        $amortization->codsubcuentadebe = $template->debit_subaccount;
        $amortization->codsubcuentahaber = $template->credit_subaccount;
        $amortization->codsubcuentaperdidas = $template->loss_subaccount;
        $amortization->save();
    }

    /**
     * @param BaseView $view
     * @return void
     */
    private function setViewColumnsFromType($view)
    {
        $isBanking = $view->model->tipo === Amortizacion::TYPE_BANKING;
        $isLineal = $view->model->tipo === Amortizacion::TYPE_LINEAL;
        $readOnly = $isBanking ? 'true' : 'false';

        $columnPeriod = $view->columnForName('period');
        if ($columnPeriod && $columnPeriod->widget->getType() === 'number') {
            $columnPeriod->description = $isBanking ? 'number-years' : 'number-months';
            $columnPeriod->widget->max = $isBanking ? 999 : 200;
        }

        $view->disableColumn('contabilization', false, $readOnly);
        $view->disableColumn('annual-rate', $isBanking === false);
        $view->disableColumn('per-amor', $isLineal === false);
        $view->disableColumn('period', $isLineal === true);
    }
}
