<?php
/**
 * Copyright (C) 2019-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PlazosPago\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;

/**
 * List of payment terms of a payment method
 *
 * @author Carlos García Gómez  <carlos@facturascripts.com>
 * @author Artex Trading sa     <jcuello@artextrading.com>
 */
class FormaPagoPlazo extends ModelClass
{
    use ModelTrait;

    /**
     * The percentage of the total amount of the document
     *
     * @var float
     */
    public $aplazado;

    /**
     * Link to the form of payment model.
     *
     * @var string
     */
    public $codpago;

    /**
     * Number of days from the date of the document
     *
     * @var int
     */
    public $dias;

    /**
     * Primary key.
     *
     * @var int
     */
    public $id;

    /**
     * Number of months from the date of the document
     *
     * @var int
     */
    public $meses;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->aplazado = 0.0;
        $this->dias = 0;
        $this->meses = 0;
    }

    /**
     * @param string $date
     *
     * @return string
     */
    public function getExpiration($date)
    {
        if ($this->meses > 0) {
            $date .= ' +' . $this->meses . ' months';
        }
        if ($this->dias > 0) {
            $date .= ' +' . $this->dias . ' days';
        }

        return date('d-m-Y', strtotime($date));
    }

    public function getFormaPago(): FormaPago
    {
        $formaPago = new FormaPago();
        $formaPago->loadFromCode($this->codpago);
        return $formaPago;
    }

    public function install(): string
    {
        // needed dependencies
        new FormaPago();

        return parent::install();
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'formaspago_plazos';
    }

    public function test(): bool
    {
        return parent::test() && $this->testTerms();
    }

    protected function testTerms(): bool
    {
        $total = $this->aplazado;
        foreach ($this->getFormaPago()->getPlazos() as $term) {
            if ($term->id != $this->id) {
                $total += $term->aplazado;
            }
        }

        if ($total > 100.0) {
            Tools::log()->warning('terms-exceed-100p');
            return false;
        }

        return true;
    }
}
