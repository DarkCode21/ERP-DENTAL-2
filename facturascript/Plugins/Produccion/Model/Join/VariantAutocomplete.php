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
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Base\JoinModel;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\CommonAutocomplete;

/**
 * It contains the main data of the products and their variants.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class VariantAutocomplete extends JoinModel
{
    /**
     * Return an array for autocomplete action.
     *
     * @param string $fieldCode
     * @param string $fieldTitle
     * @param string $term
     * @param string $strict
     * @return VariantAutocomplete[]
     */
    public static function autocomplete(string $fieldCode, string $fieldTitle, string $term, string $strict = '0'): array
    {
        $variant = new self();
        $fields = $variant->getFields();
        return CommonAutocomplete::autocomplete(
            $variant, [], $fields, $fieldCode, $fieldTitle, $term, $strict
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
            'referencia' => 'variantes.referencia',
            'idvariante' => 'variantes.idvariante',
            'idproducto' => 'variantes.idproducto',
            'descripcion' => 'productos.descripcion',
        ];
    }

    /**
     * List of tables related to from clausule
     *
     * @return string
     */
    protected function getSQLFrom(): string
    {
        return 'variantes'
            . ' LEFT JOIN productos ON productos.idproducto = variantes.idproducto';
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
