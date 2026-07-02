<?php
/**
 * This file is part of Amortizaciones plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Amortizaciones  Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\Amortizaciones\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Amortizacion;
use FacturaScripts\Dinamic\Model\Asiento;

/**
 * Lineas de Amortización List model
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class LineaAmortizacion extends ModelClass
{

    use ModelTrait;

    /** @var float */
    public $amortizado;

    /** @var int */
    public $ano;

    /** @var float */
    public $cantidad;

    /** @var float */
    public $capital;

    /** @var string */
    public $fecha;

    /**
     * Link to Amotizacion Model.
     *
     * @var int
     */
    public $idamortizacion;

    /**
     * Link to Asiento Model.
     *
     * @var int
     */
    public $idasiento;

    /**
     * Primary key.
     *
     * @var int
     */
    public $idlinea;

    /** @var float */
    public $interes;

    /** @var int */
    public $periodo;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->ano = Date('Y');
        $this->amortizado = 0.00;
        $this->cantidad = 0.00;
        $this->capital = 0.00;
        $this->fecha = Date(self::DATE_STYLE);
        $this->interes = 0.00;
        $this->periodo = 0;
    }

    /**
     * Returns the Amortizacion object linked to this LineaAmortizacion.
     *
     * @return Amortizacion
     */
    public function getAmortizacion(): Amortizacion
    {
        $amortizacion = new Amortizacion();
        $amortizacion->loadFromCode($this->idamortizacion);
        return $amortizacion;
    }

    /**
     * Returns the Asiento object linked to this LineaAmortizacion.
     *
     * @return Asiento
     */
    public function getAsiento(): Asiento
    {
        $asiento = new Asiento();
        $asiento->loadFromCode($this->idasiento);
        return $asiento;
    }

    /**
     * This function is called when creating the model table. Returns the SQL
     * that will be executed after the creation of the table. Useful to insert values
     * default.
     *
     * @return string
     */
    public function install(): string
    {
        new Amortizacion();
        new Asiento();
        return parent::install();
    }

    /**
     * Returns the name of the column that is the model's primary key.
     *
     * @return string
     */
    public static function primaryColumn(): string
    {
        return 'idlinea';
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'lineasamortizaciones';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        if (false === $this->checkFields(['idamortizacion', 'periodo', 'ano'])) {
            return false;
        }

        $this->fecha = ($this->periodo === 99) ? $this->fecha : $this->getDateFromPeriod();
        if (empty($this->fecha)) {
            Tools::log()->error('date-amortization-error');
            return false;
        }

        if (empty($this->idasiento)) {
            $this->amortizado = 0.00;
        }

        return parent::test();
    }

    /**
     * Remove the model data from the database.
     *
     * @return bool
     */
    public function delete(): bool
    {
        if (false === empty($this->idasiento)) {
            $entry = new Asiento();
            $entry->loadFromCode($this->idasiento);
            if (false === $entry->delete()) {
                return false;
            }

            $amortization = $this->getAmortizacion();
            if (false === $amortization->save()) {
                return false;
            }
        }
        return parent::delete();
    }

    /**
     * Update the model data in the database.
     *
     * @param array $values
     *
     * @return bool
     */
    protected function saveUpdate(array $values = []): bool
    {
        if (false === parent::saveUpdate($values)) {
            return false;
        }

        // recalculate the residual value of the amortization
        $amortization = $this->getAmortizacion();
        if (false === $amortization->save()) {
            return false;
        }
        return true;
    }

    /**
     * check that the list of fields have value.
     *
     * @param array $fields
     * @return bool
     */
    private function checkFields(array $fields): bool
    {
        foreach ($fields as $field) {
            if (empty($this->{$field})) {
                Tools::log()->error('field-can-not-be-null', [
                    '%fieldName% ' => $field,
                    '%tableName%' => 'lineasamortizaciones',
                ]);
                return false;
            }
        }
        return true;
    }

    /**
     * Gets the end date for the period and year of the amortization.
     *
     * @return string
     */
    private function getDateFromPeriod(): string
    {
        switch ($this->getAmortizacion()->contabilizacion) {
            case Amortizacion::CONTABILIZACION_ANNUAL:
                return $this->ano . '-12-31';

            case Amortizacion::CONTABILIZACION_MONTHLY:
                $startDate = strtotime($this->ano . '-' . $this->periodo . '-01');
                return $this->ano . '-' . $this->periodo . '-' . date('t', $startDate);

            case Amortizacion::CONTABILIZACION_QUARTERLY:
                switch ($this->periodo) {
                    case 1:
                        return $this->ano . '-03-31';

                    case 2:
                        return $this->ano . '-06-30';

                    case 3:
                        return $this->ano . '-09-30';

                    case 4:
                        return $this->ano . '-12-31';
                }
                return '';

            default:
                return '';
        }
    }
}
