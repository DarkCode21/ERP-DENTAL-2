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
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ExtendedController\ListController;
use FacturaScripts\Dinamic\Model\LineaReceta;
use FacturaScripts\Dinamic\Model\OrdenProduccion;
use FacturaScripts\Dinamic\Model\Receta;
use FacturaScripts\Dinamic\Model\RecetaProducto;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\ProductionTools;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\RecipeReferenceChange;
use FacturaScripts\Plugins\Produccion\Model\Join\ProductPackBoxAutocomplete;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

/**
 * Description of ListReceta
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Jose Antonio Cuello <yopli2000@gmail.com>
 */
class ListReceta extends ListController
{
    private const VIEW_ORDER = 'ListOrdenProduccion';
    private const VIEW_RECIPE = 'ListReceta';
    private const VIEW_INGREDIENTE = 'ListIngrediente';
    private const VIEW_NUMSERIE = 'ListOrdenNumSerie';
    private const VIEW_PRODUCCION = 'ListProduccion';

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'warehouse';
        $pageData['title'] = 'recipes';
        $pageData['icon'] = 'fa-solid fa-clipboard-list';
        return $pageData;
    }

    /**
     * Add special autocomplete for product packs.
     *
     * @return array
     */
    protected function autocompleteAction(): array
    {
        if ($this->request->input('source', '') === 'productopack_pack') {
            return $this->autocompletePack();
        }

        return parent::autocompleteAction();
    }

    /**
     * Load views
     *
     * @throws Exception
     */
    protected function createViews()
    {
        $this->createViewsRecipe();
        $this->createViewsOrder();
        $this->createViewsIngredient();
        $this->createViewsProduction();
        $this->createViewsNumSeries();
    }

    /**
     * Runs the actions that alter the data before reading it.
     *
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'change-reference':
                $this->changeReference();
                return true;

            case 'insert':
                $codeRecipe = $this->request->request->get('coderecipe', '');
                $quantity = (float)$this->request->request->get('quantity', 1.00) ?? 1.00;
                if (false === empty($codeRecipe) && false === empty($quantity)) {
                    $order = ProductionTools::recipeToProductionOrder($codeRecipe, $quantity);
                    if ($order->exists()) {
                        $this->redirect($order->url('edit'));
                    }
                }
                return true;

            case 'new-from-pack':
                $this->newFromPack();
                return true;
        }
        return parent::execPreviousAction($action);
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);
        if ($viewName === self::VIEW_ORDER) {
            $view->model->quantity = 1.00;
        }
    }

    /**
     * Change reference of recipe.
     *
     * @return void
     */
    private function changeReference(): void
    {
        if (false === $this->validateFormToken()) {
            return;
        }

        $data = $this->request->request->all();
        $manager = new RecipeReferenceChange();
        $manager->exec($data);
    }

    /**
     * Return array data for Product Pack Autocomplete.
     *
     * @return array
     */
    private function autocompletePack(): array
    {
        if (false === $this->hasProductPackModule()) {
            return [];
        }

        $data = $this->requestGet(['fieldcode', 'fieldtitle', 'source', 'strict', 'term']);
        return ProductPackBoxAutocomplete::autocomplete(
            $data['fieldcode'],
            $data['fieldtitle'],
            $data['term'],
            $data['strict']
        );
    }

    /**
     * Create views for the recipe ingredient list.
     */
    private function createViewsIngredient(): void
    {
        $this->addView(self::VIEW_INGREDIENTE, 'Join\Ingrediente', 'ingredients', 'fa-solid fa-tasks')
            // SETTINGS
            ->setSettings('clickable', false)
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false)
            // SEARCH AND ORDER
            ->addSearchFields(['ordenes.fecha','ordenes.hora', 'recetas.descripcion'])
            ->addOrderBy(['ordenes.fecha', 'ordenes.hora'], 'date', 2)
            ->addOrderBy(['ordenes.estado'], 'status')
            ->addOrderBy(['ingredientes.referencia'], 'reference')
            ->addOrderBy(['recetas.descripcion'], 'recipe')
            // FILTERS
            ->addFilterPeriod('date', 'date', 'ordenes.fecha')
            ->addFilterNumber('min-total', 'quantity', 'ingredientes.cantidad')
            ->addFilterNumber('max-total', 'quantity', 'ingredientes.cantidad', '<=')
            ->addFilterAutocomplete('recipe', 'recipe', 'ordenes.idreceta', 'produccion_recetas', 'idreceta', 'descripcion')
            ->addFilterAutocomplete('product', 'product', 'productos.referencia', 'productos', 'referencia', 'descripcion')
            ->addFilterAutocomplete('variant', 'variant', 'variantes.referencia', 'variantes', 'referencia');
    }

    /**
     * Create views for the production orders list.
     */
    private function createViewsOrder(): void
    {
        $i18n = Tools::lang();
        $pending = [OrdenProduccion::STATUS_PENDING, OrdenProduccion::STATUS_STARTED];

        $this->addView(self::VIEW_ORDER, 'Join\OrdenProduccion', 'production-orders', 'fa-solid fa-puzzle-piece')
            // SETTINGS
            ->setSettings('modalInsert', 'insert')
            // SEARCH AND ORDER
            ->addSearchFields(['ordenes.id','recetas.codreceta', 'recetas.descripcion', 'rproductos.referencia'])
            ->addOrderBy(['ordenes.fecha', 'ordenes.hora', 'ordenes.id'], 'date', 2)
            ->addOrderBy(['ordenes.vencimiento', 'ordenes.fecha', 'ordenes.hora', 'ordenes.id'], 'expiration')
            ->addOrderBy(['recetas.descripcion'], 'recipe')
            ->addOrderBy(['ordenes.id'], 'code')
            // FILTERS
            ->addFilterPeriod('date', 'creation-date', 'ordenes.fecha')
            ->addFilterAutocomplete('nick', 'created-by', 'ordenes.nick', 'users', 'nick')
            ->addFilterSelectWhere('status', [
                ['label' => $i18n->trans('pending'), 'where' => [new DataBaseWhere('estado', implode(',', $pending), 'IN')]],
                ['label' => $i18n->trans('only-not-started'),   'where' => [new DataBaseWhere('estado', OrdenProduccion::STATUS_PENDING)]],
                ['label' => $i18n->trans('only-started'), 'where' => [new DataBaseWhere('estado', OrdenProduccion::STATUS_STARTED)]],
                ['label' => $i18n->trans('only-finished'), 'where' => [new DataBaseWhere('estado', OrdenProduccion::STATUS_FINISHED)]],
                ['label' => $i18n->trans('only-cancelled'), 'where' => [new DataBaseWhere('estado', OrdenProduccion::STATUS_CANCELLED)]],
                ['label' => $i18n->trans('all'), 'where' => []],
            ])
            ->addFilterSelectWhere('expiration', [
                ['label' => $i18n->trans('all'), 'where' => []],
                ['label' => $i18n->trans('no-expiration'), 'where' => [new DataBaseWhere('vencimiento', null)]],
                ['label' => $i18n->trans('with-expiration'),   'where' => [new DataBaseWhere('vencimiento', null, 'IS NOT')]],
                ['label' => $i18n->trans('expired'), 'where' => [new DataBaseWhere('vencimiento', Tools::date(), '<=')]],
            ])
            ->addFilterPeriod('manufactured-date', 'manufactured-date', 'ordenes.fechafabricacion')
            ->addFilterAutocomplete('manufactured-by', 'manufactured-by', 'ordenes.nickfabricacion', 'users', 'nick')
            ->addFilterAutocomplete('reference', 'reference', 'rproductos.referencia', 'productos', 'referencia', 'descripcion');
    }

    /**
     * Create views for the serial numbers list.
     *
     * @return void
     */
    private function createViewsNumSeries(): void
    {
        $this->addView(self::VIEW_NUMSERIE, 'Join\NumSerie', 'num-series', 'fa-solid fa-barcode')
            // SETTINGS
            ->setSettings('clickable', false)
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false)
            // SEARCH AND ORDER
            ->addSearchFields(['numseries.reference', 'numseries.numserie', 'productos.descripcion'])
            ->addOrderBy(['numseries.reference'], 'reference')
            ->addOrderBy(['numseries.numserie'], 'numserie')
            ->addOrderBy(['ordenes.fecha', 'ordenes.hora', 'numseries.numserie'], 'date')
            ->addOrderBy(['numseries.verifydate', 'numseries.reference', 'numseries.numserie'], 'verified')
            ->addOrderBy(['numseries.codalmacen'], 'warehouse')
            // FILTERS
            ->addFilterPeriod('date', 'date', 'ordenes.fecha')
            ->addFilterPeriod('verified', 'verified', 'numseries.verifydate', true)
            ->addFilterAutocomplete('product', 'product', 'numseries.reference', 'Variante', 'referencia', 'descripcion')
            ->addFilterAutocomplete('nick', 'manufactured-by', 'ordenes.nick', 'users', 'nick')
            ->addFilterAutocomplete('verifynick', 'verified-by', 'numseries.verifynick', 'users', 'nick')
            ->addFilterAutocomplete('deliverynote', 'delivery-note', 'numseries.iddelivery', 'albaranescli', 'idalbaran', 'codigo')
			->addFilterSelectWhere('status', [
                ['label' => Tools::lang()->trans('all'), 'where' => []],
                ['label' => Tools::lang()->trans('assigneds'), 'where' => [new DataBaseWhere('numseries.iddelivery', null, 'IS NOT')]],
                ['label' => Tools::lang()->trans('unassigneds'), 'where' => [new DataBaseWhere('numseries.iddelivery', null, 'IS')]],
            ]);
    }

    /**
     * Create views for the production list.
     */
    private function createViewsProduction(): void
    {
        $this->addView(self::VIEW_PRODUCCION, 'Join\ProductoProduccion', 'manufactured', 'fa-solid fa-boxes')
            // SETTINGS
            ->setSettings('clickable', false)
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false)
            // SEARCH AND ORDER
            ->addSearchFields(['ordenes.fecha','ordenes.hora', 'productos.descripcion', 'producidos.referencia'])
            ->addOrderBy(['ordenes.fecha', 'ordenes.hora'], 'date', 2)
            ->addOrderBy(['ordenes.estado'], 'status')
            ->addOrderBy(['productos.descripcion'], 'description')
            ->addOrderBy(['producidos.referencia'], 'reference')
            ->addOrderBy(['recetas.descripcion'], 'recipe')
            // FILTERS
            ->addFilterPeriod('date', 'date', 'ordenes.fecha')
            ->addFilterPeriod('manufactured-date', 'manufactured-date', 'ordenes.fechafabricacion')
            ->addFilterNumber('min-total', 'quantity', 'producidos.cantidad')
            ->addFilterNumber('max-total', 'quantity', 'producidos.cantidad', '<=')
            ->addFilterAutocomplete('recipe', 'recipe', 'ordenes.idreceta', 'produccion_recetas', 'idreceta', 'descripcion')
            ->addFilterAutocomplete('product', 'product', 'productos.referencia', 'productos', 'referencia', 'descripcion')
            ->addFilterAutocomplete('variant', 'variant', 'variantes.referencia', 'variantes', 'referencia')
            ->addFilterAutocomplete('manufactured-by', 'manufactured-by', 'ordenes.nickfabricacion', 'users', 'nick');
    }

    /**
     * Create views for the recipe list.
     *
     * @param string $viewName
     * @throws Exception
     */
    private function createViewsRecipe(string $viewName = self::VIEW_RECIPE): void
    {
        $this->addView($viewName, 'Join\Receta', 'recipes', 'fa-solid fa-clipboard-list');
        $this->addSearchFields($viewName, ['recetas.codreceta', 'recetas.descripcion', 'rproductos.referencia']);

        $this->addOrderBy($viewName, ['recetas.codreceta'], 'code', 1);
        $this->addOrderBy($viewName, ['recetas.descripcion'], 'description');
        $this->addOrderBy($viewName, ['recetas.ultimaproduccion'], 'last-production');

        $this->addFilterPeriod($viewName, 'production', 'last-production', 'recetas.ultimaproduccion');
        $this->addFilterAutocomplete($viewName, 'wsource', 'warehouse-source', 'recetas.codalmacen', 'almacenes', 'codalmacen', 'nombre');
        $this->addFilterAutocomplete($viewName, 'wtarget', 'warehouse-target', 'recetas.codalmacen2', 'almacenes', 'codalmacen', 'nombre');
        $this->addFilterAutocomplete($viewName, 'reference', 'reference', 'rproductos.referencia', 'productos', 'referencia', 'descripcion');

        if ($this->hasProductPackModule()) {
            $this->addButton($viewName, [
                'action' => 'new-from-pack',
                'color' => 'success',
                'icon' => 'fa-solid fa-plus',
                'label' => 'from-box',
                'type' => 'modal',
            ]);
        }
        $this->addButton($viewName, [
            'action' => 'change-reference',
            'color' => 'warning',
            'icon' => 'fa-solid fa-arrow-right-arrow-left',
            'label' => 'reference',
            'type' => 'modal',
        ]);
    }

    /**
     * Check if Product Pack module is installed.
     *
     * @return bool
     */
    private function hasProductPackModule(): bool
    {
        return class_exists('\\FacturaScripts\\Dinamic\\Model\\ProductPack');
    }

    /**
     * Create new recipe from product pack.
     *
     * @return void
     */
    private function newFromPack(): void
    {
        $idpack = $this->request->request->get('source_pack', 0);
        $idwarehouse = $this->request->request->get('source_warehouse', '');
        if (empty($idpack) || empty($idwarehouse)) {
            return;
        }

        $packModel = '\\FacturaScripts\\Dinamic\\Model\\ProductPack';
        $pack = new $packModel();
        if (false === $pack->load($idpack)) {
            return;
        }

        $recipe = new Receta();
        $recipe->codreceta = $pack->reference;
        $recipe->descripcion = $pack->name;
        $recipe->codalmacen = $idwarehouse;
        $recipe->codalmacen2 = $idwarehouse;
        if ($recipe->save()) {
            $product = new RecetaProducto();
            $product->idreceta = $recipe->idreceta;
            $product->referencia = $pack->reference;
            $product->save();

            foreach ($pack->getDetail() as $packLine) {
                $line = new LineaReceta();
                $line->idreceta = $recipe->idreceta;
                $line->referencia = $packLine->reference;
                $line->cantidad = $packLine->quantity;
                $line->save();
            }
        }
    }
}
