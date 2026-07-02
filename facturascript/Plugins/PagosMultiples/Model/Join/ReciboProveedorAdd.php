<?php
/**
 * This file is part of PagosMultiples plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * PagosMultiples  Copyright (C) 2020-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\PagosMultiples\Model\Join;

use FacturaScripts\Core\Model\Base\JoinModel;
use FacturaScripts\Dinamic\Model\ReciboProveedor;

/**
 * Class for read receipt and auxiliar data.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class ReciboProveedorAdd extends JoinModel
{

    /**
     * Constructor and class initializer.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        $this->setMasterModel(new ReciboProveedor());
    }

    /**
     * List of fields or columns to select clausule
     */
    protected function getFields(): array
    {
        return [
            'codproveedor' => 'recibos.codproveedor',
            'coddivisa' => 'recibos.coddivisa',
            'codpago' => 'recibos.codpago',
            'idempresa' => 'recibos.idempresa',
            'idfactura' => 'recibos.idfactura',
            'idmultiple' => 'recibos.idmultiple',
            'idrecibo' => 'recibos.idrecibo',
            'importe' => 'recibos.importe',
            'fecha' => 'recibos.fecha',
            'fechapago' => 'recibos.fechapago',
            'liquidado' => 'recibos.liquidado',
            'nick' => 'recibos.nick',
            'pagado' => 'recibos.pagado',
            'numero' => 'recibos.numero',
            'observaciones' => 'recibos.observaciones',
            'vencimiento' => 'recibos.vencimiento',
            'vencido' => 'recibos.vencido',

            'codigo' => 'facturas.codigo',
            'codserie' => 'facturas.codserie',

            'nombre' => 'subject.nombre',
            'razonsocial' => 'subject.razonsocial',
        ];
    }

    /**
     * List of tables related to from clausule
     */
    protected function getSQLFrom(): string
    {
        return 'recibospagosprov recibos'
            . ' INNER JOIN facturasprov facturas ON facturas.idfactura = recibos.idfactura'
            . ' INNER JOIN proveedores subject ON subject.codproveedor = recibos.codproveedor';
    }

    /**
     * List of tables required for the execution of the view.
     */
    protected function getTables(): array
    {
        return [
            'recibospagosprov',
            'facturasprov',
            'proveedores',
        ];
    }
}
