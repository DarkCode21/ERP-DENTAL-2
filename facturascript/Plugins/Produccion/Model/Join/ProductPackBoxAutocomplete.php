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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Base\JoinModel;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\CommonAutocomplete;

/**
 * It contains the main data of the product pack which are boxes.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class ProductPackBoxAutocomplete extends JoinModel
{
    /**
     * Return an array for autocomplete action.
     *
     * @param string $fieldCode
     * @param string $fieldTitle
     * @param string $term
     * @param string $strict
     * @return ProductPackBoxAutocomplete[]
     */
    public static function autocomplete(string $fieldCode, string $fieldTitle, string $term, string $strict = '0'): array
    {
        $model = new self();
        $fields = $model->getFields();
        $where = [new DataBaseWhere('isbox', true)];
        return CommonAutocomplete::autocomplete(
            $model, $where, $fields, $fieldCode, $fieldTitle, $term, $strict
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
            'id' => 'id',
            'name' => 'name',
        ];
    }

    /**
     * List of tables related to from clausule
     *
     * @return string
     */
    protected function getSQLFrom(): string
    {
        return 'productopack_pack';
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
