<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\Export;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Export\XLSExport;

/**
 * XML export data from ModelReport.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class XLSReportExport extends XLSExport
{

    /**
     * Adds a new page with the model data.
     *
     * @param array $cursor
     * @param array $columns
     * @param string $title
     *
     * @return bool
     */
    public function addModelPage($cursor, $columns, $title = ''): bool
    {
        $fields = [];
        $this->setFieldsFromColumns($fields, $columns);

        $headers = [];
        $this->setHeadersFromColumns($headers, $columns);

        $this->setFileName($title);
        $rows = $this->getCursorRawData($cursor, $fields);
        $this->writer->writeSheet($rows, $title, $headers);
        return true;
    }

    /**
     * Set the fields from columns list.
     *
     * @param array $fields
     * @param array $columns
     */
    private function setFieldsFromColumns(&$fields, &$columns)
    {
        foreach ($columns as $col) {
            if (is_string($col)) {
                $fields[] = $col;
                continue;
            }

            if (isset($col->columns)) {
                $this->setFieldsFromColumns($fields, $col->columns);
                continue;
            }

            if (!$col->hidden()) {
                $fields[] = $col->widget->fieldname;
            }
        }
    }

    /**
     * Set the headers from columns list.
     *
     * @param array $fields
     * @param array $columns
     */
    private function setHeadersFromColumns(&$headers, &$columns)
    {
        foreach ($columns as $col) {
            if (is_string($col)) {
                $headers[$col] = 'string';
                continue;
            }

            if (isset($col->columns)) {
                $this->setHeadersFromColumns($headers, $col->columns);
                continue;
            }

            if (!$col->hidden()) {
                $type = $this->getTypeForWidget($col->widget);
                $headers[Tools::lang()->trans($col->title)] = $type;
            }
        }
    }

    /**
     * Get XLS Column type from Widget type.
     *
     * @param BaseWidget $widget
     * @return string
     */
    private function getTypeForWidget($widget): string
    {
        switch ($widget->getType()) {
            case 'number':
                return $widget->decimal == 0 ? 'integer' : 'price';

            case 'money':
                return 'price';

            default:
                return 'string';
        }
    }
}
