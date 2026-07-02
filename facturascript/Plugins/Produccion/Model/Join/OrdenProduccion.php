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
use FacturaScripts\Plugins\Produccion\Model\OrdenProduccion as ParentModel;

/**
 * It contains the main data of the production order and its auxiliary data.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class OrdenProduccion extends JoinModel
{
    /**
     * For filtering by user nick.
     *
     * @var string|null
     */
    public ?string $nick = '';

    /**
     * Constructor and class initializer.
     *
     * @param array $data
     */
    public function __construct($data = array())
    {
        parent::__construct($data);

        $this->setMasterModel(new ParentModel());
    }

    /**
     * List of fields or columns to select clausule
     *
     * @return array
     */
    protected function getFields(): array
    {
        return [
            'estado' => 'ordenes.estado',
            'fecha' => 'ordenes.fecha',
            'fechafabricacion' => 'ordenes.fechafabricacion',
            'hora' => 'ordenes.hora',
            'horafabricacion' => 'ordenes.horafabricacion',
            'id' => 'ordenes.id',
            'idreceta' => 'ordenes.idreceta',
            'nick' => 'ordenes.nick',
            'nickfabricacion' => 'ordenes.nickfabricacion',
            'observaciones' => 'ordenes.observaciones',
            'usarstock' => 'ordenes.usarstock',
            'vencimiento' => 'ordenes.vencimiento',

            'codreceta' => 'recetas.codreceta',
            'receta' => 'recetas.descripcion',

            'referencia' => 'rproductos.referencia',
            'cantidad' => 'oproductos.cantidad',
        ];
    }

    /**
     * List of tables related to from clausule
     *
     * @return string
     */
    protected function getSQLFrom(): string
    {
        return 'produccion_ordenes ordenes'
            . ' INNER JOIN produccion_recetas recetas ON recetas.idreceta = ordenes.idreceta'
            . ' LEFT JOIN produccion_recetasproductos rproductos ON rproductos.idreceta = recetas.idreceta AND rproductos.principal'
            . ' LEFT JOIN produccion_ordenesproductos oproductos ON oproductos.idorden = ordenes.id AND oproductos.referencia = rproductos.referencia';
    }

    /**
     * List of tables required for the execution of the view.
     *
     * @return array
     */
    protected function getTables(): array
    {
        return [
            'produccion_ordenes',
            'produccion_recetas',
        ];
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
