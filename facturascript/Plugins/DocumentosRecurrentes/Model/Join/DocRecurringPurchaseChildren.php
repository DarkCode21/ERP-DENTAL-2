<?php
/**
 * This file is part of DocumentosRecurrentes plugin for FacturaScripts.
 * FacturaScripts         Copyright (C) 2015-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DocumentosRecurrentes  Copyright (C) 2020-2021 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DocumentosRecurrentes\Model\Join;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Base\JoinModel;
use FacturaScripts\Dinamic\Model\DocRecurringPurchase;
use FacturaScripts\Plugins\DocumentosRecurrentes\Lib\DocumentosRecurrentes\DocRecurringTools;

/**
 * Auxiliary model to load a documents children of doc recurring.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class DocRecurringPurchaseChildren extends JoinModel
{

    /**
     * Load data for the indicated where.
     *
     * @param DataBaseWhere[] $where filters to apply to model records.
     * @param array $order fields to use in the sorting. For example ['code' => 'ASC']
     * @param int $offset
     * @param int $limit
     *
     * @return array
     */
    public function all(array $where, array $order = [], int $offset = 0, int $limit = 0): array
    {
        $result = [];
        $docRecurring = new DocRecurringPurchase();
        foreach ($docRecurring->all($where, $order, $offset, $limit) as $row) {
            $tools = new DocRecurringTools($row);
            foreach ($tools->childrenAllDocuments() as $document) {
                $result[] = $document;
            }
        }
        return $result;
    }

    /**
     * List of fields or columns to select clausule
     */
    protected function getFields(): array
    {
        return [ 'id' => 'id' ];
    }

    /**
     * List of tables related to from clausule
     */
    protected function getSQLFrom(): string
    {
        return DocRecurringPurchase::tableName();
    }

    /**
     * List of tables required for the execution of the view.
     */
    protected function getTables(): array
    {
        return [
            DocRecurringPurchase::tableName(),
        ];
    }
}
