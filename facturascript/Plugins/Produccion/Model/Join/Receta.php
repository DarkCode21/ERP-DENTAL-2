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
use FacturaScripts\Plugins\Produccion\Model\Receta as ParentModel;
use FacturaScripts\Plugins\Produccion\Model\LineaReceta;
use FacturaScripts\Plugins\Produccion\Model\RecetaProducto;

/**
 * List of recipes.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class Receta extends JoinModel
{
    /**
     * Constructor and class initializer.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        $this->setMasterModel(new ParentModel());
        new LineaReceta();
        new RecetaProducto();
    }

    /**
     * List of fields or columns to select clausule
     */
    protected function getFields(): array
    {
        return [
            'codalmacen' => 'recetas.codalmacen',
            'codalmacen2' => 'recetas.codalmacen2',
            'codreceta' => 'recetas.codreceta',
            'coste' => 'recetas.coste',
            'descripcion' => 'recetas.descripcion',
            'idreceta' => 'recetas.idreceta',
            'observaciones' => 'recetas.observaciones',
            'ultimaproduccion' => 'recetas.ultimaproduccion',

            'referencia' => 'rproductos.referencia',
            'cantidad' => 'rproductos.cantidad',

            'items' => 'COALESCE((' . $this->getItemsSelect() . '), 0)',
            'priceitems' => 'COALESCE((' . $this->getPriceItems() . '), 0.00)'
        ];
    }

    /**
     * List of tables related to from clausule
     */
    protected function getSQLFrom(): string
    {
        return 'produccion_recetas recetas'
            . ' LEFT JOIN produccion_recetasproductos rproductos ON rproductos.idreceta = recetas.idreceta AND rproductos.principal';
    }

    /**
     * List of tables required for the execution of the view.
     */
    protected function getTables(): array
    {
        return [];
    }

    /**
     * Return SQL for get number of lines into a recipe
     *
     * @return string
     */
    private function getItemsSelect(): string
    {
        return 'SELECT COUNT(1)'
             . ' FROM produccion_lineasrecetas t1'
             . ' WHERE t1.idreceta = recetas.idreceta';
    }

    /**
     * Return SQL for get total price of lines into a recipe
     *
     * @return string
     */
    private function getPriceItems(): string
    {
        return 'SELECT SUM(t2.precio * t1.cantidad)'
             . ' FROM produccion_lineasrecetas t1'
             . ' INNER JOIN variantes t2 ON t2.referencia = t1.referencia'
             . ' WHERE t1.idreceta = recetas.idreceta';
    }
}
