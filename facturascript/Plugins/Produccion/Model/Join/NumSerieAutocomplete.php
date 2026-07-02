<?php
/**
 * This file is part of the Produccion plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 * All Rights Reserved.
 */
namespace FacturaScripts\Plugins\Produccion\Model\Join;

use FacturaScripts\Dinamic\Model\Base\JoinModel;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\CommonAutocomplete;

/**
 * Data of Numserie produced reference and auxiliar data.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class NumSerieAutocomplete extends JoinModel
{
    /**
     * Return an array for autocomplete action.
     *
     * @param string $fieldCode
     * @param string $fieldTitle
     * @param string $term
     * @param array $where
     * @return array
     */
    public static function autocomplete(string $fieldCode, string $fieldTitle, string $term, array $where = []): array
    {
        $model = new self();
        $fields = $model->getFields();
        return CommonAutocomplete::autocomplete(
            $model, $where, $fields, $fieldCode, $fieldTitle, $term, '1'
        );
    }

    /**
     * List of fields or columns to select clausule
     *
     * @return array
     */
    protected function getFields(): array
    {
        return [
            'id' => 'products.id',
            'idorden' => 'products.idorden',
            'referencia' => 'products.referencia',
        ];
    }

    /**
     * List of tables related to from clausule
     *
     * @return string
     */
    protected function getSQLFrom(): string
    {
        return 'produccion_ordenesproductos products';
    }

    /**
     * List of tables required for the execution of the view.
     *
     * @return array
     */
    protected function getTables(): array
    {
        return [];
    }
}
