<?php
/**
 * This file is part of Amortizaciones plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Amortizaciones  Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\Amortizaciones\Model\Join;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Base\JoinModel;
use FacturaScripts\Plugins\Amortizaciones\Model\Amortizacion as ParentModel;

/**
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * @property string $periodDescription
 * @property int $periodos
 * @property string $tipo
 */
class Amortizacion extends JoinModel
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
    }

    /**
     * List of fields or columns to select clausule
     */
    protected function getFields(): array
    {
        return [
            'canal' => 'amortizaciones.canal',
            'coddivisa' => 'amortizaciones.coddivisa',
            'codsubcuentabeneficios' => 'amortizaciones.codsubcuentabeneficios',
            'codsubcuentacierre' => 'amortizaciones.codsubcuentacierre',
            'codsubcuentadebe' => 'amortizaciones.codsubcuentadebe',
            'codsubcuentahaber' => 'amortizaciones.codsubcuentahaber',
            'codsubcuentaperdidas' => 'amortizaciones.codsubcuentaperdidas',
            'contabilizacion' => 'amortizaciones.contabilizacion',
            'descripcion' => 'amortizaciones.descripcion',
            'fechafin' => 'amortizaciones.fechafin',
            'fechafinvidautil' => 'amortizaciones.fechafinvidautil',
            'fechainicio' => 'amortizaciones.fechainicio',
            'idamortizacion' => 'amortizaciones.idamortizacion',
            'idasientofinvida' => 'amortizaciones.idasientofinvida',
            'idempresa' => 'amortizaciones.idempresa',
            'idfactura' => 'amortizaciones.idfactura',
            'idfacturaventa' => 'amortizaciones.idfacturaventa',
            'observaciones' => 'amortizaciones.observaciones',
            'periodos' => 'amortizaciones.periodos',
            'residual' => 'amortizaciones.residual',
            'tasaanual' => 'amortizaciones.tasaanual',
            'porcamort' => 'amortizaciones.porcamort',
            'tipo' => 'amortizaciones.tipo',
            'valor' => 'amortizaciones.valor',

            'nombrecorto' => 'empresas.nombrecorto',
        ];
    }

    /**
     * List of tables related to from clausule
     */
    protected function getSQLFrom(): string
    {
        return 'amortizaciones'
            . ' JOIN empresas ON amortizaciones.idempresa = empresas.idempresa';
    }

    /**
     * List of tables required for the execution of the view.
     */
    protected function getTables(): array
    {
        return [
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
        $this->periodDescription = ($this->tipo === ParentModel::TYPE_BANKING)
            ? $this->periodos . ' ' . Tools::lang()->trans('months')
            : $this->periodos . ' ' . Tools::lang()->trans('years');
    }
}
