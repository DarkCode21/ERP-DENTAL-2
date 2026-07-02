<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\HumanResources;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\ListView;
use FacturaScripts\Dinamic\Lib\AssetManager;
use FacturaScripts\Dinamic\Lib\ExportManager;

/**
 * View definition for its use in ReportController
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class ReportView extends ListView
{

    const DEFAULT_TEMPLATE = 'Master/ReportView.html.twig';

    /**
     * Model to use in this view.
     *
     * @var ModelReport
     */
    public $model;

    /**
     * Indicate the template to see the detail of the report data.
     *
     * @var string
     */
    public $templateData;

    /**
     * ReportView constructor and initialization.
     *
     * @param string $name
     * @param string $title
     * @param string $modelName
     * @param string $icon
     */
    public function __construct(string $name, string $title, string $modelName, string $icon)
    {
        parent::__construct($name, $title, $modelName, $icon);
        $this->templateData = '';
        $this->showFilters = true;
        $this->settings['clickable'] = false;
        $this->settings['megasearch'] = false;
    }

    /**
     * Method to export the view data.
     *
     * @param ExportManager $exportManager
     * @param mixed $codes
     *
     * @return bool
     */
    public function export(&$exportManager, $codes): bool
    {
        if ($this->count > 0) {
            return $exportManager->addModelPage($this->cursor, $this->getColumns(), $this->title);
        }

        return true;
    }

    /**
     * Loads the data in the cursor property, according to the where filter specified.
     *
     * @param string          $code
     * @param DataBaseWhere[] $where
     * @param array           $order
     * @param int             $offset
     * @param int             $limit
     */
    public function loadData($code = '', $where = [], $order = [], $offset = -1, $limit = FS_ITEM_LIMIT)
    {
        $this->offset = $offset < 0 ? $this->offset : $offset;
        $this->order = empty($order) ? $this->order : $order;
        $this->where = array_merge($where, $this->where);

        if (is_null($this->model)) {
            $this->cursor = [];
            $this->count = 0;
            return;
        }

        $this->cursor = $this->model->all($this->filters, $this->where, $this->order, $this->offset, $limit);
        $this->count = count($this->cursor);

        /// avoid overflow
        if ($this->offset > $this->count) {
            $this->offset = 0;
        }
    }

    /**
     * Adds assets to the asset manager.
     */
    protected function assets()
    {
        parent::assets();
        AssetManager::add('js', FS_ROUTE . '/Dinamic/Assets/JS/ReportView.js');
    }
}
