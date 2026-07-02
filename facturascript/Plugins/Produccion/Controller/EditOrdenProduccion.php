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
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Html;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\AssetManager;
use FacturaScripts\Dinamic\Lib\ExtendedController\EditController;
use FacturaScripts\Dinamic\Lib\ExtendedController\EditListView;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\EditOrdenProduccionAction;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\EditOrdenProduccionNumSerieAction;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\NumSerieTraceability;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\OrdenNumSerieViewData;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\ProductDataCard;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\ProductionTools;
use FacturaScripts\Plugins\Produccion\Model\Join\NumSerieAutocomplete;
use FacturaScripts\Plugins\Produccion\Model\OrdenIngrediente;
use FacturaScripts\Plugins\Produccion\Model\OrdenProduccion;
use FacturaScripts\Plugins\Produccion\Model\OrdenProducto;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Description of EditOrdenProduccion
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class EditOrdenProduccion extends EditController
{
    use ProductDataCard;

    public const VIEW_INGREDIENTS = 'EditOrdenIngrediente';

    public const VIEW_NOTES = 'EditOrdenProduccionNota';

    public const VIEW_NUMSERIES = 'EditOrdenNumSerie';

    public const VIEW_PRODUCTS = 'EditOrdenProducto';

    /**
     * Check parameters for actions.
     *
     * @param OrdenProduccion $order
     * @param bool $checkToken
     * @return bool
     */
    public function checkParams(OrdenProduccion $order, bool $checkToken = true): bool
    {
        $code = $this->request->inputOrQuery('code', 0);
        if (empty($code)
            || ($checkToken && false === $this->validateFormToken())
            || false === $order->load($code)
        ) {
            return false;
        }
        return true;
    }

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'OrdenProduccion';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'warehouse';
        $pageData['title'] = 'production-order';
        $pageData['icon'] = 'fa-solid fa-puzzle-piece';
        return $pageData;
    }

    /**
     * Add buttons depending on the status of the document.
     *
     * @param string $viewName
     * @param OrdenProduccion $model
     * @throws Exception
     */
    private function addStatusButton(string $viewName, OrdenProduccion $model): void
    {
        if (empty($model->id)) {
            return;
        }

        $actionManager = new EditOrdenProduccionAction($model);
        $actionManager->addStatusButton($this, $viewName);
    }

    /**
     * Handles the autocomplete action.
     *
     * @return array
     */
    protected function autocompleteAction(): array
    {
        if ($this->request->input('source', '') === 'OrdenProducto') {
            return $this->autocompleteNumSerie();
        }

        return parent::autocompleteAction();
    }

    /**
     * Return data for autocomplete numserie action.
     *
     * @return array
     */
    private function autocompleteNumSerie(): array
    {
        $data = $this->requestGet(['fieldcode', 'fieldtitle', 'source', 'strict', 'term']);
        $where = [new DataBaseWhere('products.idorden', $this->request->input('code', 0))];
        return NumSerieAutocomplete::autocomplete(
            $data['fieldcode'],
            $data['fieldtitle'],
            $data['term'],
            $where
        );
    }

    /**
     * Create the view to display.
     */
    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('left-bottom');

        $this->createViewsIngredients();
        $this->createViewsProducts();
        $this->createViewsNumSeries();
        $this->createViewsNotes();

        $route = Tools::config('route');
        AssetManager::addJs($route . '/Dinamic/Assets/JS/ProductionProductCard.js?v=' . Tools::dateTime());
    }

    /**
     * Create the Recipe Lines view.
     */
    private function createViewsIngredients(): void
    {
        OrdenIngrediente::setLoadProductData(true);
        $view = $this->addEditListView(self::VIEW_INGREDIENTS, 'OrdenIngrediente', 'ingredients', 'fa-solid fa-tasks')
            ->setInLine(true);
        ProductionTools::setQuantityDecimals($view);
    }

    /**
     * Create the Notes view.
     */
    private function createViewsNotes(): void
    {
        $this->addEditView(self::VIEW_NOTES, 'OrdenProduccion', 'notes', 'fa-solid fa-sticky-note')
            ->setSettings('btnDelete', false);
    }

    private function createViewsNumSeries(): void
    {
        $this->addHtmlView(
            self::VIEW_NUMSERIES,
            'Tab/' . self::VIEW_NUMSERIES,
            'OrdenNumSerie',
            'serial-numbers',
            'fa-solid fa-barcode'
        );

        $route = Tools::config('route');
        AssetManager::addJs($route . '/Dinamic/Assets/JS/EditOrdenNumSerie.js?v=' . Tools::dateTime());
    }

    /**
     * Create the Recipe Lines view.
     */
    private function createViewsProducts(): void
    {
        OrdenProducto::setLoadProductData(true);
        $view = $this->addEditListView(self::VIEW_PRODUCTS, 'OrdenProducto', 'to-manufacture', 'fa-solid fa-boxes')
            ->setInLine(true);
        ProductionTools::setQuantityDecimals($view);
    }

    /**
     * Run the actions that alter data before reading it.
     *
     * @param string $action
     * @return bool
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'back':
            case 'cancel':
            case 'confirm':
            case 'start':
            case 'verified':
                $order = new OrdenProduccion();
                if (false === $this->checkParams($order)) {
                    return true;
                }

                $data = $this->request->request->all();
                $actionManager = new EditOrdenProduccionAction($order);
                $actionManager->exec($action, $data);
                return true;

            case 'clone':
                $order = new OrdenProduccion();
                if (false === $this->checkParams($order)) {
                    return true;
                }

                $newId = ProductionTools::cloneProductionOrder($order);
                if ($newId > 0) {
                    $this->redirect('EditOrdenProduccion?code=' . $newId . '&action=save-ok');
                }
                return true;

            case 'getProductData':
                $data = $this->request->request->all();
                $this->setTemplate(false);
                $this->response->json($this->getProductDataAction($data));
                return false;

            case 'get-traceability':
                $data = $this->request->request->all();
                $this->setTemplate(false);
                $this->response->json(NumSerieTraceability::exec($action, $data));
                return false;

            case 'print-traceability':
                $data = [
                    'id'       => $this->request->inputOrQuery('id', 0),
                    'numserie' => $this->request->inputOrQuery('numserie', ''),
                ];
                $this->setTemplate(false);
                $this->response->headers->set('Content-type', 'application/pdf');
                $this->response->headers->set('Content-Disposition', 'inline; filename=' . $data['numserie'] . '.pdf');
                $this->response->setContent(NumSerieTraceability::exec($action, $data)['pdf']);
                return false;

            case 'consume-numserie':
            case 'release-numserie':
            case 'save-numserie':
            case 'verify-numserie':
                $manager = new EditOrdenProduccionNumSerieAction($this);
                $this->setTemplate(false);
                $this->response->json($manager->exec($action));
                return false;

            default:
                return parent::execPreviousAction($action);
        }
    }

    /**
     * Get product data action.
     *
     * @param array $data
     * @return array
     */
    private function getProductDataAction(array $data): array
    {
        $variant = new Variante();
        $production = new OrdenProduccion();
        if (false === $production->load($data['idorden'])
            || false === $variant->loadWhere([Where::eq('referencia', $data['referencia'])])
        ) {
            return [
                'ok'    => false,
                'error' => Tools::lang()->trans('variant-not-found'),
            ];
        }

        $recipe = $production->getRecipe();
        return $this->getProductData($variant, $recipe);
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
            case self::VIEW_NOTES:
                $view->loadData($this->getModel()->id);
                $view->count = empty($view->model->observaciones) ? 0 : 1;
                break;

            case self::VIEW_INGREDIENTS:
            case self::VIEW_PRODUCTS:
                $where = [new DataBaseWhere('idorden', $this->getModel()->id)];
                $view->loadData('', $where, ['id' => 'DESC']);
                $this->setTraceability($view);
                break;

            case self::VIEW_NUMSERIES:
                $viewData = new OrdenNumSerieViewData($this->getModel());
                $view->count = $viewData->count();
                if ($view->count < 1) {
                    unset($this->views[$viewName]);
                    return;
                }

                $view->cursor = [
                    'hasIngredients' => $viewData->hasIngredients(),
                    'hasProduced'    => $viewData->hasProduced(),
                    'ingredients'    => $viewData->getIngredients(),
                    'produced'       => $viewData->getProduced(),
                ];
                break;

            default:
                parent::loadData($viewName, $view);
                if ($viewName === $this->getMainViewName()) {
                    $this->setStatusToView($viewName, $view->model);
                    $this->addStatusButton($viewName, $view->model);
                    // get cost from the recipe, for old orders without cost calculated.
                    if (empty($view->model->coste)) {
                        $view->model->coste = $view->model->getRecipe()->coste;
                    }
                }
                break;
        }
    }

    /**
     * Add buttons depending on the status of the document.
     *
     * @param string $viewName
     * @param OrdenProduccion $model
     * @throws Exception
     */
    private function setStatusToView(string $viewName, OrdenProduccion $model): void
    {
        if (empty($model->id) || $model->estado == OrdenProduccion::STATUS_PENDING) {
            return;
        }

        $this->addButton($viewName, [
            'action'  => 'clone',
            'color'   => 'warning',
            'icon'    => 'fa-solid fa-clone',
            'label'   => 'clone',
            'confirm' => 'true',
        ]);

        $cancel = [OrdenProduccion::STATUS_STARTED, OrdenProduccion::STATUS_CANCELLED];
        $this->views[$viewName]
            ->disableColumn('user', false, 'true')
            ->setSettings('btnDelete', in_array($model->estado, $cancel))
            ->setSettings('btnUndo', false)
            ->setSettings('btnSave', false);

        $this->views[self::VIEW_INGREDIENTS]
            ->disableColumn('reference', false, 'true')
            ->disableColumn('quantity', false, 'true')
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false)
            ->setSettings('btnUndo', false)
            ->setSettings('btnSave', false);

        $this->views[self::VIEW_PRODUCTS]
            ->disableColumn('reference', false, 'true')
            ->disableColumn('quantity', false, 'true')
            ->disableColumn('serial-number', false, 'true')
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false)
            ->setSettings('btnUndo', false)
            ->setSettings('btnSave', false);
    }

    /**
     * Populate with the batch list or block the batch column depending
     * on whether the variant is traceable.
     *
     * @param BaseView|EditListView $view
     */
    private function setTraceability(BaseView|EditListView $view): void
    {
        foreach ($view->cursor as $row) {
            $product = $row->getVariant()->getProducto();
            if ($product->hasColumn('numserie') && $product->numserie) {
                $row->trazabilidad = 'numserie';
                continue;
            }

            if ($product->hasColumn('trazabilidad') && $product->trazabilidad) {
                $row->trazabilidad = 'lotes';
            }
        }
    }
}
