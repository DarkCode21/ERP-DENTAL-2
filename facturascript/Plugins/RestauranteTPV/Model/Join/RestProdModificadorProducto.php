<?php
/**
 * This file is part of RestauranteTPV plugin for FacturaScripts
 * Copyright (C) 2026 Interibérica Informática
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 */

namespace FacturaScripts\Plugins\RestauranteTPV\Model\Join;

use FacturaScripts\Core\Model\Base\JoinModel;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestProdModificador;

/**
 * Asignaciones producto→modificador con nombre del producto y del modificador.
 */
class RestProdModificadorProducto extends JoinModel
{
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        $this->setMasterModel(new RestProdModificador());
    }

    protected function getFields(): array
    {
        return [
            'id'                  => 'rpm.id',
            'referencia'          => 'rpm.referencia',
            'descripcion'         => 'p.descripcion',
            'idmodificador'       => 'rpm.idmodificador',
            'nombre_modificador'  => 'rm.nombre',
        ];
    }

    protected function getSQLFrom(): string
    {
        return 'rest_prod_modificadores rpm'
            . ' LEFT JOIN variantes v ON v.referencia = rpm.referencia'
            . ' LEFT JOIN productos p ON p.idproducto = v.idproducto'
            . ' LEFT JOIN rest_modificadores rm ON rm.idmodificador = rpm.idmodificador';
    }

    protected function getTables(): array
    {
        return ['rest_prod_modificadores', 'variantes', 'productos', 'rest_modificadores'];
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        if ($type === 'list') {
            return 'ListRestModificador';
        }
        // Para editar, delegar al master = RestProdModificador
        $master = new RestProdModificador();
        $master->id = $this->id;
        return $master->url($type, $list);
    }
}
