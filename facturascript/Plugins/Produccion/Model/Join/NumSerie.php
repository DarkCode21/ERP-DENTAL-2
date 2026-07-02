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
use FacturaScripts\Plugins\Produccion\Model\OrdenNumSerie;

/**
 * Data of Numserie produced reference and auxiliar data.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 *
 * @property string $reference
 * @property string $producto
 */
class NumSerie extends JoinModel
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
        new OrdenNumSerie();
    }

    /**
     * List of fields or columns to select clausule
     *
     * @return array
     */
    protected function getFields(): array
    {
        return [
            'id' => 'numseries.id',
            'iddelivery' => 'numseries.iddelivery',
            'idorden' => 'numseries.idorden',
            'idusedinorder' => 'numseries.idusedinorder',
            'idline' => 'numseries.idline',
            'numserie'=> 'numseries.numserie',
            'reference' => 'numseries.reference',
            'verified' => 'numseries.verified',
            'verifydate' => 'numseries.verifydate',
            'verifynick' => 'numseries.verifynick',

            'estado' => 'ordenes.estado',
            'fecha' => 'ordenes.fecha',
            'hora' => 'ordenes.hora',
            'idreceta' => 'ordenes.idreceta',
            'nick' => 'ordenes.nick',

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
        return 'produccion_ordenesnumseries numseries'
            . ' INNER JOIN produccion_ordenes ordenes ON ordenes.id = numseries.idorden'
            . ' INNER JOIN variantes ON variantes.referencia = numseries.reference'
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
