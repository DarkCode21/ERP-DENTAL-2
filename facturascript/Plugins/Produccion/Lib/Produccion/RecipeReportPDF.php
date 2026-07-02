<?php
/**
 * This file is part of the Produccion plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 * All Rights Reserved.
 */
namespace FacturaScripts\Plugins\Produccion\Lib\Produccion;

use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Lib\ExtendedController\EditListView;
use FacturaScripts\Dinamic\Model\CodeModel;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * Description of RecipeReportPDF
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class RecipeReportPDF
{
    private const VIEW_RECIPE = 'EditReceta';

    private const VIEW_RECIPE_LINES = 'EditLineaReceta';

    /** @var bool */
    protected bool $printCost;

    /** @var mixed */
    protected $response;

    /** @var array */
    protected array $totals;

    /** @var BaseView[] */
    protected array $views;

    /**
     * Class constructor.
     *
     * @param BaseView[] $views
     * @param mixed $response
     */
    public function __construct(array $views, $response)
    {
        $this->response = $response;
        $this->views = $views;
        $this->totals = [
            'cost' => 0.00,
            'items' => 0,
        ];
        $this->printCost = (bool)Tools::settings('production', 'printrecipecost', true) ?? true;
    }

    /**
     * Generate recipe PDF info.
     *
     * @return bool
     */
    public function generatePDF(): bool
    {
        $recipeView = $this->views[ self::VIEW_RECIPE ];

        $exportManager = new ExportManager();
        $exportManager->newDoc('PDF', $recipeView->model->descripcion);
        $this->insertRecipeModel($exportManager);
        $this->insertRecipeNotes($exportManager);
        $this->insertRecipeProducts($exportManager);
        $this->insertRecipeLines($exportManager, $this->views[self::VIEW_RECIPE_LINES]);
        $this->insertTotals($exportManager);
        $exportManager->show($this->response);
        return true;
    }

    /**
     * Add recipe data to report.
     *
     * @param ExportManager $exportManager
     */
    protected function insertRecipeModel(ExportManager $exportManager): void
    {
        $recipeView = $this->views[ self::VIEW_RECIPE ];
        $columnsView = $recipeView->getColumns();
        $columns = [];
        $this->setRecipeColumns($columnsView, $columns);

        $title = Tools::lang()->trans('recipe');
        $exportManager->addModelPage($recipeView->model, $columns, $title);
    }

    /**
     * Add recipe notes to report.
     *
     * @param ExportManager $exportManager
     */
    protected function insertRecipeNotes(ExportManager $exportManager): void
    {
        $recipeView = $this->views[ self::VIEW_RECIPE ];
        $data = $recipeView->model->observaciones;
        if (empty($data)) {
            return;
        }

        $title = Tools::lang()->trans('notes');
        $exportManager->addTablePage([$title], [[$title => $data]]);
    }

    /**
     * Add the products of recipe to report.
     *
     * @param ExportManager $exportManager
     */
    protected function insertRecipeProducts(ExportManager $exportManager): void
    {
        $modelAux = new CodeModel();
        $i18n = Tools::lang();
        $title = '<strong>' . $i18n->trans('products') . '</strong>';
        $header = [
            $i18n->trans('reference'),
            $i18n->trans('product'),
            $i18n->trans('quantity'),
        ];
        $options = [
            $header[2] => ['display' => 'center'],
        ];

        $data = [];
        $recipe = $this->views[ self::VIEW_RECIPE ]->model;
        foreach ($recipe->getProducts() as $row) {
            $productName = $modelAux->getDescription(
                Producto::tableName(),
                'referencia',
                $row->referencia,
                'descripcion'
            );
            $data[] = [
                $header[0] => $row->referencia,
                $header[1] => $productName,
                $header[2] => Tools::number($row->cantidad),
            ];
        }
        $exportManager->addTablePage($header, $data, $options, $title);
    }

    /**
     * Add the totals of recipe to report.
     *
     * @param ExportManager $exportManager
     */
    protected function insertTotals(ExportManager $exportManager): void
    {
        $i18n = Tools::lang();
        $lblIngredients = $i18n->trans('ingredients');
        $lblCost        = $i18n->trans('cost');

        $header = array_merge(
            [$lblIngredients],
            $this->printCost ? [$lblCost] : []
        );

        $data = [
            $lblIngredients => Tools::number($this->totals['items'], 0),
        ];

        $options = [
            $lblIngredients => ['display' => 'center'],
        ];

        if ($this->printCost) {
            $data[$lblCost] = Tools::money($this->totals['cost']);
            $options[$lblCost] = ['display' => 'center'];
        }

        $title = '<strong>' . $i18n->trans('totals') . '</strong>';
        $exportManager->addTablePage($header, [$data], $options, $title);
    }

    /**
     * Add the ingredients of recipe to report.
     *
     * @param ExportManager $exportManager
     * @param BaseView|EditListView $view;
     */
    private function insertRecipeLines(ExportManager $exportManager, $view): void
    {
        $variant = new Variante();
        $i18n = Tools::lang();
        $title = '<strong>' . $i18n->trans($view->title) . '</strong>';

        // keys columns translations
        $lblReference = $i18n->trans('reference');
        $lblProduct   = $i18n->trans('product');
        $lblQuantity  = $i18n->trans('quantity');
        $lblCost      = $i18n->trans('cost');
        $lblTotal     = $i18n->trans('total');
        $lblStock     = $i18n->trans('stock');
        $lblAvailable = $i18n->trans('available');

        $header = array_merge(
            [$lblReference, $lblProduct, $lblQuantity],
            $this->printCost ? [$lblCost, $lblTotal] : [],
            [$lblStock, $lblAvailable]
        );

        $options = [
            $lblQuantity  => ['display' => 'center'],
            $lblStock     => ['display' => 'center'],
            $lblAvailable => ['display' => 'center'],
        ];

        if ($this->printCost) {
            $options[$lblCost] = ['display' => 'right'];
            $options[$lblTotal] = ['display' => 'right'];
        }

        $data = [];
        foreach ($view->cursor as $row) {
            $line = [
                $lblReference => $row->referencia,
                $lblProduct   => $row->productname,
                $lblQuantity  => Tools::number($row->cantidad),
            ];

            if ($this->printCost) {
                $variantCost = 0.0;
                if ($variant->loadWhereEq('referencia', $row->referencia)) {
                    $variantCost = (float) $variant->coste;
                }

                $lineTotalCost = $variantCost * (float) $row->cantidad;
                $line[$lblCost] = Tools::money($variantCost) . ' ';
                $line[$lblTotal] = Tools::money($lineTotalCost) . ' ';
                $this->totals['cost']  += $lineTotalCost;
            }

            $line[$lblStock]     = Tools::number($row->stock ?? 0, 0);
            $line[$lblAvailable] = Tools::number($row->available ?? 0, 0);

            $data[] = $line;
            $this->totals['items'] += 1;
        }
        $exportManager->addTablePage($header, $data, $options, $title);
    }

    /**
     * Set the columns to print of the recipe data.
     *
     * @param array $columns
     * @param array $tableCols
     */
    private function setRecipeColumns(array $columns, array &$tableCols): void
    {
        $excludedColumns = ['description', 'reference', 'product', 'quantity', 'notes'];
        $printCost = (bool)Tools::settings('production', 'printrecipecost', true) ?? true;
        if (empty($printCost)) {
            $excludedColumns[] = 'coste';
        }
        foreach ($columns as $col) {
            if (isset($col->columns)) {
                $this->setRecipeColumns($col->columns, $tableCols);
                continue;
            }

            if ($col->hidden()) {
                continue;
            }

            if (in_array($col->name, $excludedColumns)) {
                continue;
            }

            $tableCols[] = $col;
        }
    }
}
