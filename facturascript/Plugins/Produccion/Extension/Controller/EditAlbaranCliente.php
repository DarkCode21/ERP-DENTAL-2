<?php
/**
 * This file is part of the Produccion plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 * All Rights Reserved.
 */
namespace FacturaScripts\Plugins\Produccion\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\NumSerieManager;
use FacturaScripts\Plugins\Produccion\Model\NumSerieCounter;

/**
 * Class to manager the numserie into Delivery Notes edit view
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * @property mixed $request
 * @property array $views
 * @method addHtmlView(string $viewName, string $fileName, string $modelName, string $viewTitle, string $viewIcon): HtmlView
 * @method getModel(): AlbaranCliente
 * @method setTemplate(bool $template): void
 */
class EditAlbaranCliente
{
    /**
     * Load views
     */
    public function createViews(): Closure
    {
        return function () {
            $numSerieCounter = new NumSerieCounter();
            if ($numSerieCounter->count() === 0) {
                return;
            }

            $this->addHtmlView('EditAlbaranClienteNumSerie', 'Tab/EditAlbaranClienteNumSerie', 'Join\NumSerieAlbaranCliente', 'serial-numbers', 'fa-solid fa-barcode');
        };
    }

    public function execPreviousAction(): Closure
    {
        return function ($action): ?bool {
            if ($action == 'saveNumSeries') {
                $data = $this->request->request->all();
                NumSerieManager::assignNumSerie($data);
                return true;
            }

            if ($action === 'unassignNumSeries') {
                $data = $this->request->request->all();
                NumSerieManager::unAssignNumSerie($data);
                return true;
            }
            return null;
        };
    }

    /**
     * Load view data procedure
     *
     * @return Closure
     */
    public function loadData(): Closure
    {
        return function ($viewName, $view): void {
            if ($viewName == 'EditAlbaranClienteNumSerie') {
                $view->cursor = $view->model->all(
                    [ new DataBaseWhere('docs.idalbaran', $this->getModel()->idalbaran)]
                );
                $view->count = count($view->cursor['assigned']) + count($view->cursor['unassigned']) ?? 0;
            }
        };
    }
}
