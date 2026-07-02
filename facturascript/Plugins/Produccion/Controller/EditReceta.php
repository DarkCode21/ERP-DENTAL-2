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
use FacturaScripts\Core\Lib\ExtendedController\DocFilesTrait;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Lib\ExtendedController\HtmlView;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\AssetManager;
use FacturaScripts\Dinamic\Lib\Produccion\RecipeManager;
use FacturaScripts\Dinamic\Model\Join\VariantAutocomplete;
use FacturaScripts\Dinamic\Model\LineaReceta;
use FacturaScripts\Dinamic\Model\Receta;
use FacturaScripts\Dinamic\Model\RecetaProducto;
use FacturaScripts\Dinamic\Model\Stock;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\ProductDataCard;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\ProductionTools;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\RecipeReportPDF;
use FacturaScripts\Plugins\Produccion\Model\OrdenProduccion;

/**
 * Description of EditReceta
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Jose Antonio Cuello <yopli2000@gmail.com>
 */
class EditReceta extends EditController
{
    private const VIEW_DOCFILES = 'docfiles';
    private const VIEW_HISTORICAL = 'ListRecetaHistorial';
    private const VIEW_LINES = 'EditLineaReceta';
    private const VIEW_PRODUCT = 'EditRecetaProducto';
    private const VIEW_STOCK = 'ListMovimientoStock';

    use DocFilesTrait;
    use ProductDataCard;

    /**
     * Bridge method to keep compatibility between EditController and DocFilesTrait.
     */
    protected function addHtmlView(
        string $viewName,
        string $fileName,
        string $modelName,
        string $viewTitle,
        string $viewIcon = 'fab fa-html5'
    ): HtmlView
    {
        return parent::addHtmlView($viewName, $fileName, $modelName, $viewTitle, $viewIcon);
    }

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'Receta';
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
        $pageData['title'] = 'recipe';
        $pageData['icon'] = 'fa-solid fa-clipboard-list';
        return $pageData;
    }

    /**
     * Autocomplete action for Variant references.
     *
     * @return array
     */
    protected function autocompleteAction(): array
    {
        if ($this->request->input('source', '') === 'variantes') {
            return $this->autocompleteReference();
        }

        return parent::autocompleteAction();
    }

    /**
     * Create the view to display.
     */
    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('left-bottom');
        ProductionTools::setQuantityDecimals($this->views[$this->getMainViewName()]);

        $this->createViewLines();
        $this->createViewProducts();
        $this->createViewMovement();
        $this->createViewHistory();
        $this->createViewDocFiles();

        $route = Tools::config('route');
        AssetManager::addJs($route . '/Dinamic/Assets/JS/ProductionProductCard.js?v=' . Tools::dateTime());
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
            case 'add-file':
                $this->addFileAction();
                return true;

            case 'clone':
                $newId = $this->cloneAction();
                if ($newId > 0) {
                    $this->redirect('EditReceta?code=' . $newId . '&action=save-ok');
                }
                return true;

            case 'delete-file':
                $this->deleteFileAction();
                return true;

            case 'edit-file':
                $this->editFileAction();
                return true;

            case 'produce-product':
                $this->produceProduct();
                return true;

            case 'getProductData':
                $data = $this->request->request->all();
                $this->setTemplate(false);
                $this->response->json($this->getProductDataAction($data));
                return false;

            default:
                return parent::execPreviousAction($action);
        }
    }

    /**
     * Export the data to PDF, CSV or EXCEL.
     * Reemplace PDF format with the RecipeReportPDF class.
     */
    protected function exportAction()
    {
        $option = $this->request->input('option', 'PDF');
        if ($option !== 'PDF') {
            parent::exportAction();
            return;
        }

        if (false === $this->views[$this->active]->settings['btnPrint']) {
            Tools::log()->warning('no-print-permission');
            return;
        }

        $reportPDF = new RecipeReportPDF($this->views, $this->response);
        if ($reportPDF->generatePDF()) {
            $this->setTemplate(false);
        }
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
            case self::VIEW_DOCFILES:
                $this->loadDataDocFiles($view, $this->getModelClassName(), $this->getModel()->id());
                break;

            case self::VIEW_HISTORICAL:
                $where = [new DataBaseWhere('idreceta', $this->getModel()->id())];
                $view->loadData('', $where);
                break;

            case self::VIEW_PRODUCT:
                $where = [new DataBaseWhere('idreceta', $this->getModel()->id())];
                $view->loadData('', $where, ['id' => 'DESC']);
                if ($view->count > 0) {
                    $this->setProductData($view->cursor);
                }
                break;

            case self::VIEW_LINES:
                $where = [new DataBaseWhere('idreceta', $this->getModel()->id())];
                $view->loadData('', $where, ['idlinea' => 'DESC']);
                if ($view->count > 0) {
                    $this->addProduceButton($this->getMainViewName());
                    $this->setProductData($view->cursor);
                    $this->setStockLineas($view->cursor, $this->getModel()->codalmacen);
                }
                break;

            case self::VIEW_STOCK:
                $where = [
                    new DataBaseWhere('docmodel', $this->getModelClassName()),
                    new DataBaseWhere('referencia', $this->getModel()->referencia)
                ];
                $view->loadData('', $where);
                break;

            default:
                parent::loadData($viewName, $view);
                if ($viewName === $this->getMainViewName()
                    && false === empty($this->getModel()->id())
                ) {
                    $this->setCloneData($viewName, $view->model);
                    $this->setStockData($view->model, $view->model->codalmacen2);
                    $maxQuantity = 999;
                    $parents = [];
                    $this->setMaxToProduce($view->model, $maxQuantity, $parents);
                }
                break;
        }
    }

    /**
     * Add the button to produce the product if it has production lines.
     *
     * @param string $mainViewName
     * @throws Exception
     */
    private function addProduceButton(string $mainViewName): void
    {
        $where = [
            new DataBaseWhere('idreceta', $this->getModel()->idreceta),
            new DataBaseWhere('numserietype', 0, '>'),
        ];
        $produceLine = new RecetaProducto();
        if ($produceLine->loadWhere($where)) {
            return;
        }

        $this->addButton($mainViewName, [
            'action' => 'produce-product',
            'color' => 'info',
            'confirm' => true,
            'icon' => 'fa-solid fa-cogs',
            'label' => 'produce-product',
            'type' => 'modal'
        ]);
    }

    /**
     * Return array data for Variant Autocomplete.
     *
     * @return array
     */
    private function autocompleteReference(): array
    {
        $data = $this->requestGet(['fieldcode', 'fieldtitle', 'source', 'strict', 'term']);
        return VariantAutocomplete::autocomplete(
            $data['fieldcode'],
            $data['fieldtitle'],
            $data['term'],
            $data['strict']
        );
    }

    /**
     * Clones the recipe.
     * The new recipe will have a new code and name.
     * Optionally, you can copy the ingredients and production lines.
     *
     * @return int
     */
    private function cloneAction(): int
    {
        $recipe = new Receta();
        $data = $this->request->request->all();
        if (false === $this->validateFormToken()
            || empty($data['idreceta'])
            || empty($data['new_code'])
            || empty($data['new_name'])
            || false === $recipe->load($data['idreceta'])
        ) {
            return 0;
        }

        $this->dataBase->beginTransaction();
        try {
            $newRecipe = clone $recipe;
            $newRecipe->idreceta = null;
            $newRecipe->codreceta = $data['new_code'];
            $newRecipe->descripcion = $data['new_name'];
            if (false === $newRecipe->save()) {
                Tools::log()->error('recipe-clone-error', ['%recipe%' => $recipe->codreceta]);
                return 0;
            }

            if (isset($data['copy_ingredients']) && $data['copy_ingredients'] === 'TRUE') {
                foreach ($recipe->getLines() as $line) {
                    $newLine = new LineaReceta();
                    $newLine->idreceta = $newRecipe->idreceta;
                    $newLine->referencia = $line->referencia;
                    $newLine->cantidad = $line->cantidad;
                    if (false === $newLine->save()) {
                        Tools::log()->error('recipe-clone-ingredient-error', ['%recipe%' => $recipe->codreceta]);
                        return 0;
                    }
                }
            }

            if (isset($data['copy_production']) && $data['copy_production'] === 'TRUE') {
                foreach ($recipe->getProducts() as $production) {
                    $newProduction = new RecetaProducto();
                    $newProduction->idreceta = $newRecipe->idreceta;
                    $newProduction->referencia = $production->referencia;
                    $newProduction->cantidad = $production->cantidad;
                    $newProduction->numserietype = $production->numserietype;
                    $newProduction->repartircoste = $production->repartircoste;
                    if (false === $newProduction->save()) {
                        Tools::log()->error('recipe-clone-production-error', ['%recipe%' => $recipe->codreceta]);
                        return 0;
                    }
                }
            }
            $this->dataBase->commit();
            return $newRecipe->id();
        } finally {
            if ($this->dataBase->inTransaction()) {
                $this->dataBase->rollback();
            }
        }
    }

    private function createViewHistory(string $viewName = self::VIEW_HISTORICAL): void
    {
        $isAdmin = $this->user->admin ?? false;
        $this->addListView($viewName, 'RecetaHistorial', 'historical', 'fa-solid fa-history')
            ->setSettings('btnDelete', $isAdmin)
            ->setSettings('checkBoxes', $isAdmin)
            ->setSettings('btnNew', false)
            ->setSettings('clickable', false)
            ->disableColumn('idreceta')
            ->addOrderBy(['fecha', 'hora', 'id'], 'date', 2);
    }

    /**
     * Create the Movement History view.
     *
     * @param string $viewName
     */
    private function createViewMovement(string $viewName = self::VIEW_STOCK): void
    {
        $this->addListView($viewName, 'MovimientoStock', 'movements', 'fa-solid fa-truck-loading')
            // Settings
            ->setSettings('btnDelete', false)
            ->setSettings('btnNew', false)
            ->setSettings('checkBoxes', false)
            ->disableColumn('document')
            // Order by
            ->addOrderBy(['cantidad'], 'quantity')
            ->addOrderBy(['fecha', 'hora', 'id'], 'date', 2);
    }

    /**
     * Create the Recipe Lines view.
     *
     * @param string $viewName
     */
    private function createViewLines(string $viewName = self::VIEW_LINES): void
    {
        $view = $this->addEditListView($viewName, 'LineaReceta', 'ingredients', 'fa-solid fa-tasks')
            ->setInLine(true);
        ProductionTools::setQuantityDecimals($view);
    }

    /**
     * Create the Recipe Lines view.
     *
     * @param string $viewName
     */
    private function createViewProducts(string $viewName = self::VIEW_PRODUCT): void
    {
        $view = $this->addEditListView($viewName, 'RecetaProducto', 'to-manufacture', 'fa-solid fa-boxes')
            ->setInLine(true);
        ProductionTools::setQuantityDecimals($view);
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
        $recipe = new Receta();
        if (false === $recipe->load($data['idreceta'])
            || false === $variant->loadWhere([Where::eq('referencia', $data['referencia'])])
        ) {
            return [
                'ok' => false,
                'error' => 'variant-not-found',
            ];
        }

        return $this->getProductData($variant, $recipe);
    }

    /**
     * Produces the amount of the indicated product.
     *
     * @return bool
     */
    private function produceProduct(): bool
    {
        $code = $this->request->input('idreceta');
        $quantity = $this->request->input('quantity');
        $maximum = $this->request->input('maximum');
        if (empty($code) || empty($quantity)) {
            return true;
        }

        if ($maximum < $quantity) {
            Tools::log()->notice('max-produce-error', ['%quantity%' => $maximum]);
            return true;
        }

        $manager = new RecipeManager();
        if ($manager->produce($code, $quantity)) {
            Tools::log()->notice(
                'recipe-produce-ok',
                ['%recipe%' => $this->request->input('codreceta'), '%quantity%' => $quantity]
            );
        }
        return true;
    }

    /**
     * @param string $viewName
     * @param $model
     * @return void
     * @throws Exception
     */
    private function setCloneData(string $viewName, $model): void
    {
        $this->addButton($viewName, [
            'action' => 'clone',
            'color' => 'warning',
            'icon' => 'fa-solid fa-clone',
            'label' => 'clone',
            'type' => 'modal'
        ]);
        $model->copy_ingredients = true;
        $model->copy_production = true;
    }

    /**
     * Load the max quantity to produce into the fields of the modal form.
     *
     * @param Receta $model
     * @param float $maxQuantity
     * @param array $parents
     */
    private function setMaxToProduce(Receta $model, float &$maxQuantity, array $parents): void
    {
        foreach ($model->getLines() as $recipeLine) {
            if ($recipeLine->nostock || $recipeLine->cantidad < 0.01) {
                continue;
            }

            // check if we have enough stocks
            if ($recipeLine->disponible >= $recipeLine->cantidad) {
                $maxReference = $recipeLine->disponible / $recipeLine->cantidad;
                if ($maxReference < $maxQuantity) {
                    $maxQuantity = $maxReference;
                }
                continue;
            }

            // check if exists a recipe to produce the product
            $producedIn = ProductionTools::producedIn($recipeLine->referencia);
            if (false === isset($producedIn['idreceta'])) {
                $maxQuantity = 0;
                break;
            }

            // load the parent recipe, and calculate the max quantity to produce
            $recipe = new Receta();
            if (false === $recipe->load($producedIn['idreceta'])) {
                $maxQuantity = 0;
                break;
            }

            // check if we are in a loop of recipes
            if (in_array($recipe->idreceta, $parents)) {
                Tools::log()->warning('recipe-produce-loop', ['%recipe%' => $recipe->codreceta]);
                $maxQuantity = 0;
                break;
            }

            $parents[] = $recipe->idreceta;
            $this->setMaxToProduce($recipe, $maxQuantity, $parents);
        }
        $model->maximum = $maxQuantity;
    }

    /**
     * Set product description to additional products.
     *
     * @param LineaReceta[]|RecetaProducto[] $rows
     */
    private function setProductData(array $rows): void
    {
        $i18n = Tools::lang();
        $sql = 'SELECT t1.coste, t2.descripcion, t2.sevende'
            . ' FROM variantes t1 INNER JOIN productos t2 ON t2.idproducto = t1.idproducto'
            . ' WHERE t1.referencia=';
        foreach ($rows as $model) {
            $reference = '\'' . $model->referencia . '\'';
            $row = $this->dataBase->selectLimit($sql . $reference, 1);
            $model->productcost = empty($row) ? 0.00 : $row[0]['coste'];
            $model->productname = empty($row) ? '' : $row[0]['descripcion'];
            $model->productforsale = empty($row) || empty($row[0]['sevende'])
                ? $i18n->trans('no')
                : $i18n->trans('yes');
        }
    }

    /**
     * Loads the stock data into the fields of the model.
     *
     * @param Receta|LineaReceta $model
     * @param string $warehouse
     */
    private function setStockData($model, string $warehouse): void
    {
        $stock = new Stock();
        $where = [
            new DataBaseWhere('codalmacen', $warehouse),
            new DataBaseWhere('referencia', $model->referencia)
        ];
        if ($stock->loadWhere($where)) {
            $model->stock = $stock->cantidad;
            $model->reserved = $stock->reservada;
            $model->available = $stock->disponible;
        }
    }

    /**
     * Set stock data to all Recipe Lines.
     *
     * @param array $rows
     * @param string $warehouse
     */
    private function setStockLineas(array $rows, string $warehouse): void
    {
        foreach ($rows as $model) {
            $this->setStockData($model, $warehouse);
        }
    }
}
