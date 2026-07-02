<?php
/**
 * Copyright (C) 2020-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TarifasAvanzadas\Model;

use FacturaScripts\Core\Model\Base;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * Description of DescuentoCliente
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class DescuentoCliente extends Base\ModelClass
{
    use Base\ModelTrait;

    /**
     * @var bool
     */
    public $acumular;

    /**
     * @var string
     */
    public $codcliente;

    /**
     * @var string
     */
    public $codfamilia;

    /**
     * @var string
     */
    public $codgrupo;

    /**
     * @var string
     */
    public $fecha0;

    /**
     * @var string
     */
    public $fecha1;

    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $observaciones;

    /**
     * @var float
     */
    public $porcentaje;

    /**
     * @var int
     */
    public $prioridad;

    /**
     * @var string
     */
    public $referencia;

    /**
     * @param Cliente|Contacto $customer
     *
     * @return bool
     */
    public function appliesToCustomer($customer): bool
    {
        if ($this->codcliente && $this->codcliente == $customer->codcliente) {
            return true;
        }

        if ($this->codgrupo && $this->codgrupo == $customer->codgrupo) {
            return true;
        }

        return empty($this->codcliente) && empty($this->codgrupo);
    }

    /**
     * @param Producto $product
     * @param Variante $variant
     *
     * @return bool
     */
    public function appliesToProduct($product, $variant): bool
    {
        if ($this->referencia && $this->referencia == $variant->referencia) {
            return true;
        }

        if ($this->codfamilia && $this->codfamilia == $product->codfamilia) {
            return true;
        }

        return empty($this->codfamilia) && empty($this->referencia);
    }

    /**
     * @param float $current
     *
     * @return float
     */
    public function applyDiscount($current)
    {
        // accumulate
        $totalDto = 1.0;
        foreach ([$current, $this->porcentaje] as $dto) {
            $totalDto *= 1 - $dto / 100;
        }

        return 100 - ($totalDto * 100);
    }

    public function clear()
    {
        parent::clear();
        $this->acumular = false;
        $this->fecha0 = Tools::date();
        $this->porcentaje = 0.0;
        $this->prioridad = 0;
    }

    public function enabled(): bool
    {
        // not yet enabled
        if (strtotime($this->fecha0) > strtotime('today')) {
            return false;
        }

        // never expires
        if (empty($this->fecha1)) {
            return true;
        }

        return strtotime($this->fecha1) >= strtotime('today');
    }

    public function install(): string
    {
        // needed dependencies
        new Cliente();
        new Variante();

        return parent::install();
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'dtosclientes';
    }

    public function test(): bool
    {
        $this->observaciones = Tools::noHtml($this->observaciones);

        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'ListTarifa?activetab=List'): string
    {
        return parent::url($type, $list);
    }
}
