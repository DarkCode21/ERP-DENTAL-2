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
use FacturaScripts\Dinamic\Model\OrdenProduccion;
use FacturaScripts\Plugins\Produccion\Model\Join\LineaProduccion;
use FacturaScripts\Plugins\Produccion\Model\Join\NumSerie;

/**
 * Class for management the data of EditOrderNumSerie view.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class OrdenNumSerieViewData
{
    private OrdenProduccion $order;
    private ?array $producedNumSeries = null;
    private ?array $rawNumSeries = null;

    /**
     * Main constructor.
     * Need the main order with loaded data.
     *
     * @param OrdenProduccion $order
     */
    public function __construct(OrdenProduccion $order)
    {
        $this->order = $order;
    }

    /**
     * Return the total numserie to assign in the order.
     *
     * @return int
     */
    public function count(): int
    {
        $total = 0;
        foreach ($this->getIngredients() as $raw) {
            $total += count($raw['items']);
        }

        foreach ($this->getProduced() as $produced) {
            $total += count($produced['items']);
        }
        return $total;
    }

    /**
     * Indicate if the order has raw products with numserie.
     *
     * @return bool
     */
    public function hasIngredients(): bool
    {
        return !empty($this->getIngredients());
    }

    /**
     * Indicate if the order has produce products with numserie.
     *
     * @return bool
     */
    public function hasProduced(): bool
    {
        return !empty($this->getProduced());
    }

    /**
     * return the raw numseries for the production order.
     *   - [ref1] => [numserie1, numserie2, ...]
     *   - [ref2] => [numserie3, numserie4, ...]
     *
     * @return array
     */
    public function getIngredients(): array
    {
        if (empty($this->rawNumSeries)) {
            $this->rawNumSeries = $this->getRawProducts();
        }

        return $this->rawNumSeries;
    }

    /**
     * return the produced numseries group by reference.
     *   - [ref1] => [numserie1, numserie2, ...]
     *   - [ref2] => [numserie3, numserie4, ...]
     *
     * @return array
     */
    public function getProduced(): array
    {
        if (is_null($this->producedNumSeries)) {
            $where = [ new DataBaseWhere('idorden', $this->order->id()) ];
            $this->producedNumSeries = $this->groupByReference($where);
        }
        return $this->producedNumSeries;
    }

    /**
     * Return the structure of raw products with numserie.
     *   - [reference] => [reference, name, required, candidates[], items[]]
     *   - candidates => NumSerie[]
     *   - items => [id => idlinea, selected => NumSerie]
     *
     * @return array
     */
    private function getRawProducts(): array
    {
        $whereAssigned = [ new DataBaseWhere('idusedinorder', $this->order->id()) ];
        $assigned = $this->groupByReference($whereAssigned);

        $result = [];
        $where = [
            new DataBaseWhere('lineas.idorden', $this->order->id()),
            new DataBaseWhere('productos.numserie', true),
        ];
        $orderBy = ['lineas.referencia' => 'ASC', 'lineas.id' => 'ASC'];

        $reference = '';
        $model = new LineaProduccion();
        foreach ($model->all($where, $orderBy) as $linea) {
            if ($reference !== $linea->referencia) {
                $reference = $linea->referencia;
                $result[$reference] = [
                    'reference' => $linea->referencia,
                    'name' => $linea->descripcion,
                    'required' => 0,
                    'candidates' => $this->rawCandidatesForReference($reference, $this->order->id()),
                    'items' => [],
                ];
            }

            $result[$reference]['required'] += (int) $linea->cantidad;  // for reference into more one line
            for ($i = 0; $i < (int) $linea->cantidad; $i++) {
                $selected = null;
                if (!empty($assigned[$reference]['items'])) {
                    $selected = array_shift($assigned[$reference]['items']);
                }

                $key = $linea->idlinea . '_' . ($i + 1);
                $result[$reference]['items'][$key] = [
                    'key' => $key,
                    'id' => $linea->idlinea,
                    'selected' => $selected,
                ];
            }
        }

        return $result;
    }

    /**
     * return the numseries filter by where and group by reference.
     *   - [ref1] => [NumSerie, NumSerie, ...]
     *   - [ref2] => [NumSerie, NumSerie, ...]
     *
     * @param DataBaseWhere[] $where
     * @return array
     */
    private function groupByReference(array $where): array
    {
        $result = [];
        $orderBy = ['reference' => 'ASC', 'id' => 'ASC'];
        $reference = '';

        $model = new NumSerie();
        foreach ($model->all($where, $orderBy) as $numSerie) {
            if ($reference !== $numSerie->reference) {
                $reference = $numSerie->reference;
                $result[$reference] = [
                    'reference' => $numSerie->reference,
                    'name' => $numSerie->producto,
                    'items' => [],
                ];
            }
            $result[$reference]['items'][] = $numSerie;
        }
        return $result;
    }

    /**
     * Return a list of numserie available for reference.
     *
     * @param string $reference
     * @param int $idorder
     * @return array
     */
    private function rawCandidatesForReference(string $reference, int $idorder = 0): array
    {
        $where = [
            new DataBaseWhere('reference', $reference),
            new DataBaseWhere('verified', true),
            new DataBaseWhere('iddelivery', null),
            new DataBaseWhere('idusedinorder', null, 'IS'),
            new DataBaseWhere('idusedinorder', $idorder, '=', 'OR'),
        ];

        $orderBy = ['idorden' => 'ASC', 'id' => 'ASC'];
        $model = new NumSerie();
        return $model->all($where, $orderBy);
    }
}
