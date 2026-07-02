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
use FacturaScripts\Plugins\Produccion\Model\OrdenIngrediente;

/**
 * It contains the main data of the raw material product
 * and its auxiliary data.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 *
 * @property string $cantidad
 * @property string $referencia
 * @property string $idorden
 * @property string $fecha
 * @property string $hora
 * @property string $codalmacen
 * @property string $idproducto
 * @property string $nostock
 */
class Ingrediente extends JoinModel
{
    /**
     * For filtering by user nick.
     *
     * @var string|null
     */
    public ?string $nick = '';

    public function __construct(array $data = [])
    {
        parent::__construct($data);

        new OrdenIngrediente();
    }

    /**
     * List of fields or columns to select clausule
     *
     * @return array
     */
    protected function getFields(): array
    {
        return [
            'idorden' => 'ingredientes.idorden',
            'cantidad' => 'ingredientes.cantidad',
            'referencia' => 'ingredientes.referencia',

            'estado' => 'ordenes.estado',
            'fecha' => 'ordenes.fecha',
            'hora' => 'ordenes.hora',
            'idreceta' => 'ordenes.idreceta',
            'nick' => 'ordenes.nick',

            'codalmacen' => 'recetas.codalmacen2',
            'receta' => 'recetas.descripcion',

            'idproducto' => 'variantes.idproducto',

            'producto' => 'productos.descripcion',
        ];
    }

    /**
     * List of tables related to from clausule
     *
     * @return string
     */
    protected function getSQLFrom(): string
    {
        return 'produccion_ordenesingredientes ingredientes'
            . ' INNER JOIN produccion_ordenes ordenes ON ordenes.id = ingredientes.idorden'
            . ' INNER JOIN produccion_recetas recetas ON recetas.idreceta = ordenes.idreceta'
            . ' INNER JOIN variantes ON variantes.referencia = ingredientes.referencia'
            . ' INNER JOIN productos ON productos.idproducto = variantes.idproducto';
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
     * Assign the values of the $data array to the model view properties.
     *
     * @param array $data
     */
    protected function loadFromData(array $data)
    {
        parent::loadFromData($data);
        $this->nick = $data['nick'] ?? '';
    }
}
