<?php
/**
 * This file is part of Amortizaciones plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Amortizaciones  Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\Amortizaciones\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Model\Base\CompanyRelationTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\LineaAmortizacion;

/**
 * Amortizaciones List model
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class Amortizacion extends ModelClass
{
    public const CONTABILIZACION_ANNUAL = 'anual';
    public const CONTABILIZACION_MONTHLY = 'mensual';
    public const CONTABILIZACION_QUARTERLY = 'trimestral';

    public const TYPE_BANKING = 'bancario';
    public const TYPE_CONSTANT = 'constante';
    public const TYPE_LINEAL = 'lineal';

    use ModelTrait;
    use CompanyRelationTrait;

    /** @var int */
    public $canal;

    /**
     * Link to Divisa Model.
     *
     * @var string
     */
    public $coddivisa;

    /** @var string */
    public $codsubcuentabeneficios;

    /** @var string */
    public $codsubcuentacierre;

    /** @var string */
    public $codsubcuentadebe;

    /** @var string */
    public $codsubcuentahaber;

    /** @var string */
    public $codsubcuentainteres;

    /** @var string */
    public $codsubcuentaperdidas;

    /** @var string */
    public $contabilizacion;

    /** @var string */
    public $descripcion;

    /** @var string */
    public $fechafin;

    /** @var string */
    public $fechafinvidautil;

    /** @var string */
    public $fechainicio;

    /**
     * Primary key.
     *
     * @var int
     */
    public $idamortizacion;

    /** @var int */
    public $idasientofinvida;

    /**
     * Link to Empresa Model.
     *
     * @var int
     */
    public $idempresa;

    /** @var int */
    public $idfactura;

    /** @var int */
    public $idfacturaventa;

    /** @var string */
    public $observaciones;

    /** @var int */
    public $periodos;

    /** @var float */
    public $residual;

    /** @var float */
    public $tasaanual;

    /** @var float **/
    public $porcamort;

    /** @var string */
    public $tipo;

    /** @var float */
    public $valor;

    /**
     * Returns the number of amortizations that are made in an
     * accounting period.
     *
     * @return int
     */
    public function amortizationsByPeriod(): int
    {
        switch ($this->contabilizacion) {
            case self::CONTABILIZACION_ANNUAL:
                return 1;

            case self::CONTABILIZACION_MONTHLY:
                return 12;

            case self::CONTABILIZACION_QUARTERLY:
                return 4;
        }
        return 0;
    }

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->coddivisa = Tools::settings('default', 'coddivisa');
        $this->contabilizacion = self::CONTABILIZACION_ANNUAL;
        $this->idempresa = Tools::settings('default', 'idempresa');
        $this->fechainicio = Date(self::DATE_STYLE);
        $this->periodos = 0;
        $this->residual = 0.00;
        $this->tasaanual = 0.00;
        $this->porcamort = 0.00;
        $this->tipo = self::TYPE_CONSTANT;
        $this->valor = 0.00;
    }

    /**
     * Remove the model data from the database.
     *
     * @return bool
     */
    public function delete(): bool
    {
        foreach ($this->getLines() as $line) {
            if (false === empty($line->idasiento)) {
                Tools::log()->warning('cant-delete-amortization');
                return false;
            }
        }

        return parent::delete();
    }

    /**
     * Return all lines of amortization plan.
     *
     * @return LineaAmortizacion[]
     */
    public function getLines(): array
    {
        $where = [ new DataBaseWhere('idamortizacion', $this->idamortizacion) ];
        $lineModel = new LineaAmortizacion();
        return $lineModel->all($where, ['idlinea' => 'ASC'], 0, 0);
    }

    /**
     * Return all pending lines of amortization plan.
     *
     * @param array $order
     * @return LineaAmortizacion[]
     */
    public function getPendingLines(array $order = ['idlinea' => 'ASC']): array
    {
        $where = [
            new DataBaseWhere('idamortizacion', $this->idamortizacion),
            new DataBaseWhere('idasiento', null),
        ];
        $lineModel = new LineaAmortizacion();
        return $lineModel->all($where, $order, 0, 0);
    }

    /**
     * Return the pending amount of the amortization plan.
     *
     * @return float
     */
    public function getPendingAmount(): float
    {
        return round($this->valor - $this->getTotalAmortized(), FS_NF0);
    }

    /**
     * Return the sell invoice of the product.
     *
     * @return FacturaCliente
     */
    public function getSellInvoice(): FacturaCliente
    {
        $invoice = new FacturaCliente();
        $invoice->loadFromCode($this->idfacturaventa);
        return $invoice;
    }

    /**
     * Return the total amortized amount of the amortization plan.
     *
     * @return float
     */
    public function getTotalAmortized(): float
    {
        $result = 0.00;
        foreach ($this->getLines() as $line) {
            if (false === empty($line->idasiento)) {
                $result += $line->cantidad;
            }
        }
        return round($result, FS_NF0);
    }

    /**
     * Returns the name of the column that is the model's primary key.
     *
     * @return string
     */
    public static function primaryColumn(): string
    {
        return 'idamortizacion';
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'amortizaciones';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        if (empty($this->idempresa)) {
            $this->idempresa = Tools::settings('default', 'idempresa');
        }
        $this->descripcion = Tools::noHtml($this->descripcion);
        $this->observaciones = Tools::noHtml($this->observaciones);
        $this->fechafin = $this->getEndDate();

        if ($this->tipo === self::TYPE_BANKING) {
            $this->contabilizacion = self::CONTABILIZACION_MONTHLY;
        }

        $this->residual = $this->valor - $this->getTotalAmortized();
        if ($this->residual < 0) {
            $this->residual = 0;
        }
        return parent::test();
    }

    /**
     *
     * @return string
     */
    private function getEndDate(): string
    {
        $period = ($this->tipo === self::TYPE_BANKING) ? ' month' : ' year';
        $time = '+' . $this->periodos . $period;
        $startDate = strtotime($this->fechainicio);
        $endDate = strtotime($time, $startDate);
        return date(self::DATE_STYLE, $endDate);
    }
}
