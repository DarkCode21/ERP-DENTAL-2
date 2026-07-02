<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\Export;

use FacturaScripts\Core\Lib\Export\PDFExport;
use FacturaScripts\Core\Tools;

/**
 * PDF export data from ModelReport.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class PDFReportExport extends PDFExport
{

    /**
     * Adds a new page with the model data.
     *
     * @param array   $cursor
     * @param array   $columns
     * @param string  $title
     *
     * @return bool
     */
    public function addModelPage($cursor, $columns, $title = ''): bool
    {
        $this->setFileName($title);

        $orientation = 'portrait';
        $tableCols = [];
        $tableColsTitle = [];
        $tableOptions = ['cols' => [], 'shadeCol' => [0.95, 0.95, 0.95], 'shadeHeadingCol' => [0.95, 0.95, 0.95]];
        $longTitles = [];
        $tableData = [];

        /// turns widget columns into necessary arrays
        $this->setTableColumns($columns, $tableCols, $tableColsTitle, $tableOptions);
        if (count($tableCols) > 5) {
            $orientation = 'landscape';
            $this->removeLongTitles($longTitles, $tableColsTitle);
        }

        $this->newPage($orientation);
        $tableOptions['width'] = $this->tableWidth;
        $this->insertHeader();

        if (empty($cursor)) {
            $this->pdf->ezTable($tableData, $tableColsTitle, '', $tableOptions);
            return true;
        }

        $tableData = $this->getTableData($cursor, $tableCols, $tableOptions);
        $this->removeEmptyCols($tableData, $tableColsTitle, Tools::number(0));
        $this->pdf->ezTable($tableData, $tableColsTitle, $title, $tableOptions);
        $this->newLongTitles($longTitles, $tableColsTitle);
        $this->insertFooter();
        return true;
    }
}
