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

use FacturaScripts\Core\Model\Base\JoinModel;

/**
 * It contains the main data of the raw material product
 * and its auxiliary data.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 *
 * @property int|float $cantidad
 * @property string $descripcion
 * @property string $referencia
 * @property int $idorden
 * @property int $idlinea
 * @property int|null $idlote
 * @property string $fecha
 * @property string $hora
 * @property string $codalmacen
 * @property int $idproducto
 * @property bool $nostock
 * @property bool $ventasinstock
 * @property bool $numserie
 */
class LineaProduccion extends JoinModel
{
    /**
     * List of fields or columns to select clausule
     *
     * @return array
     */
    protected function getFields(): array
    {
        return [
            'cantidad' => 'lineas.cantidad',
            'idlinea' => 'lineas.id',
            'idlote' => 'lineas.idlote',
            'idorden' => 'lineas.idorden',
            'referencia' => 'lineas.referencia',

            'estado' => 'ordenes.estado',
            'hora' => 'ordenes.hora',
            'idreceta' => 'ordenes.idreceta',
            'fecha' => 'ordenes.fecha',
            'usarstock' => 'ordenes.usarstock',

            'codalmacen' => 'recetas.codalmacen',
            'codreceta' => 'recetas.codreceta',
            'receta' => 'recetas.descripcion',

            'coste' => 'variantes.coste',
            'idvariante' => 'variantes.idvariante',
            'idproducto' => 'variantes.idproducto',

            'bloqueado' => 'productos.bloqueado',
            'descripcion' => 'productos.descripcion',
            'numserie' => 'productos.numserie',
            'nostock' => 'productos.nostock',
            'ventasinstock' => 'productos.ventasinstock',

            'disponible' => 'stocks.disponible',
            'stock' => 'stocks.cantidad',
        ];
    }

    /**
     * List of tables related to from clausule
     *
     * @return string
     */
    protected function getSQLFrom(): string
    {
        return 'produccion_ordenesingredientes lineas'
            . ' INNER JOIN produccion_ordenes ordenes ON ordenes.id = lineas.idorden'
            . ' INNER JOIN produccion_recetas recetas ON recetas.idreceta = ordenes.idreceta'
            . ' LEFT JOIN variantes ON variantes.referencia = lineas.referencia'
            . ' LEFT JOIN productos ON productos.idproducto = variantes.idproducto'
            . ' LEFT JOIN stocks ON stocks.codalmacen = recetas.codalmacen AND stocks.referencia = lineas.referencia';
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
