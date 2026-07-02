<?php
/**
 * Copyright (C) 2019-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PlantillasPDF\Model;

use FacturaScripts\Core\Model\Base;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Serie;

/**
 * Model to personalize the impression of sales and buy documents.
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class FormatoDocumento extends Base\ModelClass
{
    use Base\ModelTrait;

    const SETTINGS_NAME = 'plantillaspdf';

    /** @var bool */
    public $autoaplicar;

    /** @var string */
    public $codserie;

    /** @var string */
    public $color1;

    /** @var string */
    public $footertext;

    /** @var bool */
    public $hidebillingaddress;

    /** @var bool */
    public $hideobservations;

    /** @var bool */
    public $hidepaymentmethods;

    /** @var bool */
    public $hidereceipts;

    /** @var bool */
    public $hidetotals;

    /** @var bool */
    public $hideshippingaddress;

    /** @var bool */
    public $hide_breakdowns;

    /** @var bool */
    public $hide_vat_breakdown;

    /** @var int */
    public $id;

    /** @var int */
    public $idempresa;

    /** @var int */
    public $idimagefooter;

    /** @var int */
    public $idimagetext;

    /** @var int */
    public $idlogo;

    /** @var string */
    public $linecolalignments;

    /** @var string */
    public $linecols;

    /** @var string */
    public $linecoltypes;

    /** @var float */
    public $linesheight;

    /** @var string */
    public $nombre;

    /** @var string */
    public $orientation;

    /** @var bool */
    public $primarynumero2;

    /** @var string */
    public $size;

    /** @var string */
    public $texto;

    /** @var string */
    public $thankstext;

    /** @var string */
    public $thankstitle;

    /** @var string */
    public $tipodoc;

    /** @var string */
    public $titulo;

    public function clear()
    {
        parent::clear();
        $this->autoaplicar = true;
        $this->hideobservations = false;
        $this->hidetotals = false;
        $this->hide_breakdowns = false;
        $this->hide_vat_breakdown = false;
        $this->primarynumero2 = false;
        $this->texto = Tools::settings(self::SETTINGS_NAME, 'endtext');

        $fields = ['color1', 'linecolalignments', 'linecols', 'linecoltypes', 'linesheight'];
        foreach ($fields as $field) {
            $this->{$field} = Tools::settings(self::SETTINGS_NAME, $field);
        }
    }

    public function install(): string
    {
        // needed dependencies
        new Serie();
        new Empresa();

        return parent::install();
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'nombre';
    }

    public static function tableName(): string
    {
        return 'formatos_documentos';
    }

    public function test(): bool
    {
        $this->nombre = empty($this->nombre) ? Tools::noHtml($this->titulo) : Tools::noHtml($this->nombre);

        $fields = [
            'color1', 'footertext', 'linecolalignments', 'linecols', 'linecoltypes', 'orientation', 'size',
            'texto', 'thankstext', 'thankstitle', 'titulo'
        ];
        foreach ($fields as $field) {
            $this->{$field} = Tools::noHtml($this->{$field});
        }

        if (empty($this->idempresa)) {
            $this->idempresa = Tools::settings('default', 'idempresa');
        }

        $bottomMargin = (int)Tools::settings('plantillaspdf', 'bottommargin', 0);
        if ($this->footertext && strlen($this->footertext) > 200 && $bottomMargin <= 20) {
            Tools::log()->warning('footer-text-long');
        }

        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'AdminPlantillasPDF?activetab=List'): string
    {
        return parent::url($type, $list);
    }
}
