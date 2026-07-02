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
use FacturaScripts\Dinamic\Lib\Export\CSVExport;

/**
 * CSV export data from ModelReport.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class CSVReportExport extends CSVExport
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
        $data = $this->getCursorRawData($cursor, array_keys($fields));

        $this->setFileName($title);
        $this->writeData($data, $fields);
        return false;
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
                $fields[$col] = $col;
                continue;
            }

            if (isset($col->columns)) {
                $this->setFieldsFromColumns($fields, $col->columns);
                continue;
            }

            if (!$col->hidden()) {
                $fields[$col->widget->fieldname] = Tools::lang()->trans($col->title);
            }
        }
    }
}
