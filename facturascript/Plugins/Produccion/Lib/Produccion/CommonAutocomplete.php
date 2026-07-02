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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\JoinModel;
use FacturaScripts\Core\Tools;

final class CommonAutocomplete
{
    /**
     * @param JoinModel $model
     * @param array $where
     * @param array $fields
     * @param string $fieldCode
     * @param string $fieldTitle
     * @param string $term
     * @param string $strict
     * @return array
     */
    public static function autocomplete(
        $model,
        array $where,
        array $fields,
        string $fieldCode,
        string $fieldTitle,
        string $term,
        string $strict = '0'
    ): array
    {
        $order = [ $fields[$fieldTitle] => 'ASC' ];
        $whereTerm = [new DataBaseWhere(
            $fields[$fieldCode] . '|' . $fields[$fieldTitle],
            mb_strtolower($term, 'UTF8'),
            'LIKE'
        )];

        $results = [];
        foreach ($model->all(array_merge($where, $whereTerm), $order) as $value) {
            $results[] = [
                'key' => Tools::fixHtml($value->{$fieldCode}),
                'value' => Tools::fixHtml($value->{$fieldTitle}),
            ];
        }

        if (empty($results)) {
            $results[] = ('0' == $strict)
                ? ['key' => $term, 'value' => $term]
                : ['key' => null, 'value' => Tools::lang()->trans('no-data')];
        }

        return $results;
    }
}
