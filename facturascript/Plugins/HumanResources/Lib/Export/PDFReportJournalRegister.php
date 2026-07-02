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
use FacturaScripts\Plugins\HumanResources\Lib\Export\PDFReportExport;

/**
 * Description of PDFReportJournalRegister
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class PDFReportJournalRegister extends PDFExport
{

    private const COLUMNS_FIELD = [
        'day',
    ];

    private const COLUMNS_TITLE = [
        'day',
    ];

    private const COLUMNS_OPTIONS = [
        'day' => [],
    ];

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
        $tableOptions = ['cols' => [], 'shadeCol' => [0.95, 0.95, 0.95], 'shadeHeadingCol' => [0.95, 0.95, 0.95]];

        $this->newPage($orientation);
        $tableOptions['width'] = $this->tableWidth;

        $this->insertHeader();

        if (empty($cursor)) {
            $this->pdf->ezTable([], self::COLUMNS_TITLE, '', self::COLUMNS_OPTIONS);
            return true;
        }

        $tableData = $this->getTableData($cursor, self::COLUMNS_FIELD, self::COLUMNS_OPTIONS);
        $this->pdf->ezTable($tableData, self::COLUMNS_TITLE, $title, self::COLUMNS_OPTIONS);
        $this->insertFooter();
        return true;
    }

    /**
     *
     * @param JournalRegister[] $cursor
     * @param array $tableCols
     * @param array $tableOptions
     * @return array
     */
    protected function getTableData(array $cursor, array $tableCols, array $tableOptions): array {
        $tableData = [];

        foreach ($cursor as $key => $row) {
            foreach ($tableCols as $col) {
                $value = $row->{$col} ?? '';
                $tableData[$key][$col] = $this->fixValue($value);
            }
        }

        return $tableData;
    }
}
