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
use FacturaScripts\Core\KernelException;
use FacturaScripts\Dinamic\Model\Base\JoinModel;
use FacturaScripts\Plugins\Produccion\Model\OrdenNumSerie;
use FacturaScripts\Plugins\Produccion\Model\OrdenProduccion;

/**
 * Special class model for obtain num-serie for a delivery note.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 *
 * @property int $idline
 * @property ?string $numserie
 * @property int $quantity
 * @property string $reference
 */
class NumSerieAlbaranCliente extends JoinModel
{
    public array $availables = [];

    /**
     * Get all rows matching the where clausule.
     * It returns two arrays, one with assigned num-series and other with unassigned.
     *
     * @param array $where
     * @param array $order
     * @param int $offset
     * @param int $limit
     * @return array[]
     * @throws KernelException
     */
    public function all(array $where, array $order = [], int $offset = 0, int $limit = 0): array
    {
        $order = array_merge([
            'doclines.referencia' => 'ASC',
            'doclines.idlinea' => 'ASC',
            'numseries.iddelivery' => 'DESC'
        ], $order);

        $assigned = [];
        $unassigned = [];

        $lastKey = '';
        $assignedCount = 0;

        foreach (parent::all($where, $order, $offset, $limit) as $row) {
            $key = $row->reference . '_' . $row->idline;
            if ($key !== $lastKey) {
                $assignedCount = 0;
                $lastKey = $key;
            }

            if (false === empty($row->iddelivery)) {
                $assigned[] = $row;
                $assignedCount++;
                continue;
            }

            if ($assignedCount < $row->quantity) {
                $row->numserie = null;
                $row->availables = $this->getNumSerieAvailable($row->reference);
                for ($x = $assignedCount; $x < $row->quantity; $x++) {
                    $unassigned[] = clone $row;
                }
                $assignedCount = $row->quantity;
            }
        }
        return [
            'assigned' => $assigned,
            'unassigned' => $unassigned,
        ];
    }

    /**
     * List of fields or columns to select clausule
     *
     * @return array
     */
    protected function getFields(): array
    {
        return [
            'document' => 'docs.codigo',

            'iddocument' => 'doclines.idalbaran',
            'idline' => 'doclines.idlinea',
            'product' => 'doclines.descripcion',
            'quantity' => 'doclines.cantidad',
            'reference' => 'doclines.referencia',

            'idnumserie' => 'numseries.id',
            'numserie' => 'numseries.numserie',
            'iddelivery' => 'numseries.iddelivery',
        ];
    }

    /**
     * List of tables related to from clausule
     *
     * @return string
     */
    protected function getSQLFrom(): string
    {
        $joinNumSeries = ' INNER JOIN produccion_ordenesnumseries numseries'
            . ' ON numseries.reference = doclines.referencia'
            . ' AND numseries.verified'
            . ' AND (numseries.iddelivery IS NULL OR numseries.iddelivery = docs.idalbaran)';

        return 'albaranescli docs'
            . ' INNER JOIN lineasalbaranescli doclines ON doclines.idalbaran = docs.idalbaran'
            . $joinNumSeries
            . ' INNER JOIN produccion_ordenes ordenes ON ordenes.id = numseries.idorden AND ordenes.estado = ' . OrdenProduccion::STATUS_FINISHED
            . ' INNER JOIN produccion_recetas recetas ON recetas.idreceta = ordenes.idreceta AND recetas.codalmacen2 = docs.codalmacen';
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

    /**
     * Get available num-series for a product reference.
     *
     * @param string $reference
     * @return array
     * @throws KernelException
     */
    private function getNumSerieAvailable(string $reference): array
    {
        $sql = 'SELECT id, numserie FROM ' . OrdenNumSerie::tableName()
            . ' WHERE reference = ' . self::$dataBase->var2str($reference)
            . ' AND iddelivery IS NULL'
            . ' ORDER BY numserie ASC';

        return self::$dataBase->select($sql);
    }
}
