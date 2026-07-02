<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\HumanResources;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ListFilter\BaseFilter;

/**
 * Description of ModelReport
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
abstract class ModelReport
{

    /**
     * It provides direct access to the database.
     *
     * @var DataBase
     */
    protected static $dataBase;

    /**
     *
     * @var array
     */
    public $data = [];

    /**
     *
     * @var array
     */
    protected $totals = [];

    /**
     * Return array with data of report.
     *
     * @param BaseFilter[] $filters
     * @param DataBaseWhere[] $where
     * @param array $order
     * @param int $offset
     * @param int $limit
     * @return array
     */
    abstract public function all(array $filters, array $where, array $order, int $offset, int $limit): array;

    /**
     * Class constructor.
     */
    public function __construct()
    {
        if (self::$dataBase === null) {
            self::$dataBase = new DataBase();
        }
    }

    /**
     * Accumulate the amount in the indicated total field.
     *
     * @param string $fieldName
     * @param float $amount
     */
    public function accumulate(string $fieldName, float $amount)
    {
        $value = $this->getTotal($fieldName);
        $this->totals[$fieldName] = $value + $amount;
    }

    /**
     * Returns the accumulated amount of the requested field.
     *
     * @param string $fieldName
     * @return float
     */
    public function getTotal(string $fieldName): float
    {
        return (float)$this->totals[$fieldName] ?? 0.00;
    }

    /**
     * Calculate DataBaseWhere from key list of filters
     *
     * @param BaseFilter[] $filters
     * @param string[] $keys
     * @return DataBaseWhere[]
     */
    protected function getFiltersWhere(array $filters, array $keys = []): array
    {
        $result = [];
        $keys_values = empty($keys) ? array_keys($filters) : $keys;
        foreach ($keys_values as $filterKey) {
            $filters[$filterKey]->getDataBaseWhere($result);
        }
        return $result;
    }
}
