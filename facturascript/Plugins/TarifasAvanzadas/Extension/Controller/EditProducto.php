<?php
/**
 * Copyright (C) 2020-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TarifasAvanzadas\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\AssetManager;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Tarifa;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\TarifasAvanzadas\Model\TarifaProducto;

/**
 * Description of EditProducto
 *
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditProducto
{
    public function createViews(): Closure
    {
        return function () {
            $this->createViewsPrice();
        };
    }

    protected function createViewsPrice(): Closure
    {
        return function (string $viewName = 'tarifas') {
            if (Session::user()->level < Tools::settings('default', 'leveltarifasav', 0)) {
                return;
            }

            $icon = Tools::settings('default', 'coddivisa') === 'EUR' ?
                'fas fa-euro-sign' :
                'fas fa-dollar-sign';

            $this->addHtmlView($viewName, 'Tab/TarifaProducto', 'TarifaProducto', 'prices', $icon);

            // desactivamos los campos de precios en la pestaña de variantes
            $this->views['EditVariante']->disableColumn('cost-price');
            $this->views['EditVariante']->disableColumn('margin');
            $this->views['EditVariante']->disableColumn('price');

            // add javascript
            AssetManager::add('js', FS_ROUTE . '/Dinamic/Assets/JS/EditProductoPricesTab.js?v=' . Tools::date());
        };
    }

    protected function execPreviousAction(): Closure
    {
        return function ($action) {
            if ($this->request->get('ajax', false)) {
                $this->setTemplate(false);

                switch ($action) {
                    case 'changeCost':
                    case 'changeMargin':
                    case 'changePrice':
                    case 'changePriceTax':
                        $data = $this->recalculateVariantAction($action);
                        break;

                    case 'resetRates':
                        $data = $this->resetRatesAction();
                        break;

                    case 'saveVariant':
                        $data = $this->saveVariantAction();
                        break;
                }

                $content = array_merge(
                    ['messages' => $this->getMessages()],
                    $data ?? []
                );
                $this->response->setContent(json_encode($content));
                return;
            }
        };
    }

    protected function getMessages(): Closure
    {
        return function (): array {
            $logLevels = ['critical', 'error', 'info', 'notice', 'warning'];
            return Tools::log()->read('master', $logLevels);
        };
    }

    protected function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName === 'tarifas') {
                // obtenemos todas las tarifas
                $rateModel = new Tarifa();
                $rates = $rateModel->all([], [], 0, 0);

                // obtenemos las variantes del producto
                $items = [];
                $product = $this->getModel();
                foreach ($product->getVariants() as $variant) {
                    $copyVariant = clone $variant;
                    $items[] = [
                        'pricetax' => $this->roundPrice($variant->priceWithTax()),
                        'rates' => $this->renderRatesAction($copyVariant, $product, $rates),
                        'variant' => $variant
                    ];
                }


                $view->cursor = $items;
                $view->count = count($items);
            }
        };
    }

    protected function recalculateVariantAction(): Closure
    {
        return function (string $action): array {
            if (false === $this->permissions->allowUpdate) {
                Tools::log()->warning('not-allowed-modify');
                return [$action => false];
            }

            $results = [];
            $variants = json_decode($this->request->get('variants'), true);
            foreach ($variants as $variantData) {
                $variant = new Variante();
                if (false === $variant->loadFromCode($variantData['idvariante'])) {
                    continue;
                }

                switch ($action) {
                    case 'changeCost':
                    case 'changeMargin':
                        $variant->coste = (float)$variantData['coste'];
                        $variant->margen = (float)$variantData['margen'];
                        if ($variant->margen > 0) {
                            $variant->precio = $variant->coste * (100 + $variant->margen) / 100;
                        }
                        break;

                    case 'changePrice':
                        $variant->precio = (float)$variantData['precio'];
                        $variant->margen = 0;
                        break;

                    case 'changePriceTax':
                        $variant->margen = 0;
                        $variant->setPriceWithTax((float)$variantData['precioimp']);
                        break;
                }

                $extension = $this->pipe('recalculateVariant', $variant, $variantData, $action);
                if ($extension && $extension instanceof Variante) {
                    $variant = $extension;
                }

                $data = $variant->toArray();
                foreach ($variant->getModelFields() as $key => $value) {
                    if (in_array($value['type'], ['float', 'double', 'double precision'])) {
                        $data[$key] = $this->roundPrice($data[$key]);
                    }
                }
                $data['precioimp'] = $this->roundPrice($variant->priceWithTax());
                $results[] = $data;
            }

            return [$action => true, 'variants' => $results];
        };
    }

    protected function resetRatesAction(): Closure
    {
        return function () {
            if (false === $this->permissions->allowUpdate) {
                Tools::log()->warning('not-allowed-modify');
                return ['resetRates' => false];
            }

            $ratesModel = new Tarifa();
            $rates = $ratesModel->all([], [], 0, 0);

            $results = [];
            $variants = json_decode($this->request->get('variants'), true);
            foreach ($variants as $variantData) {
                $variant = new Variante();
                if (false === $variant->loadFromCode($variantData['idvariante'])) {
                    continue;
                }

                foreach ($rates as $rate) {
                    $tarifaProducto = new TarifaProducto();
                    $where = [
                        new DataBaseWhere('codtarifa', $rate->codtarifa),
                        new DataBaseWhere('referencia', $variant->referencia)
                    ];
                    if ($tarifaProducto->loadFromCode('', $where)) {
                        if (false === $tarifaProducto->delete()) {
                            Tools::log()->warning('record-save-error');
                            return ['resetRates' => false];
                        }
                    }
                }

                $product = $variant->getProducto();
                $results[] = [
                    'idvariante' => $variant->idvariante,
                    'rates' => $this->renderRatesAction($variant, $product, $rates),
                ];
            }

            Tools::log()->notice('record-updated-correctly');
            return ['resetRates' => true, 'variants' => $results];
        };
    }

    protected function renderRatesAction(): Closure
    {
        return function(Variante $variant, Producto $product, array $rates) {
            $html = '';

            $originPrice = $variant->precio;
            foreach ($rates as $rate) {
                $variant->precio = $rate->applyTo($variant, $product);

                $extension = $this->pipe('renderRates', $variant, $rate);
                if ($extension && $extension instanceof Variante) {
                    $variant = $extension;
                }

                $html .= '<tr data-codtarifa="' . $rate->codtarifa . '">'
                    . '<td><input type="text" value="' . $rate->nombre . '" class="form-control" readonly/></td>'
                    . '<td><input type="text" value="' . $rate->explainTo($variant, $product) . '" class="form-control" readonly/></td>'
                    . '<td><input type="text" placeholder="' . $this->roundPrice($variant->precio, $rate->decimales) . '" class="form-control text-right rate-price" /></td>'
                    . '<td><input type="text" placeholder="' . $this->roundPrice($variant->priceWithTax(), $rate->decimales) . '" class="form-control text-right rate-pricetax" /></td>';

                $variant->precio = $originPrice;
            }

            return $html;
        };
    }

    protected function roundPrice(): Closure
    {
        return function (float $number, int $decimals = FS_NF0): string {
            return number_format($number, $decimals, '.', '');
        };
    }

    protected function saveVariantAction(): Closure
    {
        return function () {
            if (false === $this->permissions->allowUpdate) {
                Tools::log()->warning('not-allowed-modify');
                return ['saveVariant' => false];
            }

            $variants = json_decode($this->request->get('variants', []), true);
            foreach ($variants as $variantData) {
                $variant = new Variante();
                if (false === $variant->loadFromCode($variantData['idvariante'])) {
                    continue;
                }

                // actualizamos la variante
                foreach ($variantData as $key => $value) {
                    if (property_exists($variant, $key)) {
                        $variant->{$key} = $value;
                    }
                }

                $priceWithTax = $variantData['precioimp'];
                if (!empty($priceWithTax)) {
                    $variant->setPriceWithTax((float)$priceWithTax);
                }

                $extension = $this->pipe('saveVariant', $variant, $variantData);
                if ($extension && $extension instanceof Variante) {
                    $variant = $extension;
                }

                if (false === $variant->save()) {
                    Tools::log()->warning('record-save-error');
                    return ['saveVariant' => false];
                }

                foreach ($variantData['rates'] as $rate) {
                    $precio = $rate['precio'];
                    $precioimp = $rate['precioimp'];
                    if (empty($precio) && empty($precioimp)) {
                        continue;
                    }

                    $tarifaProducto = new TarifaProducto();
                    $where = [
                        new DataBaseWhere('codtarifa', $rate['codtarifa']),
                        new DataBaseWhere('referencia', $variant->referencia)
                    ];
                    if (false === $tarifaProducto->loadFromCode('', $where)) {
                        $tarifaProducto->codtarifa = $rate['codtarifa'];
                        $tarifaProducto->referencia = $variant->referencia;
                    }

                    $tarifaProducto->pvp = $precio;
                    if (!empty($precioimp)) {
                        $tarifaProducto->setPriceWithTax((float)$precioimp);
                    }

                    $extensionRate = $this->pipe('saveVariantRate', $tarifaProducto, $rate);
                    if ($extensionRate && $extensionRate instanceof Variante) {
                        $tarifaProducto = $extensionRate;
                    }

                    $tarifaProducto->save();
                }
            }

            Tools::log()->notice('record-updated-correctly');
            return ['saveVariant' => true];
        };
    }
}
