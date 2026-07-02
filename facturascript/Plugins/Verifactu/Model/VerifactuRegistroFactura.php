<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Model;

use FacturaScripts\Core\Session;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FacturaCliente;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class VerifactuRegistroFactura extends ModelClass
{
    use ModelTrait;

    const EVENT_ALTA = 'alta';
    const EVENT_ANULACION = 'anulacion';
    const EVENT_SUBSANACION = 'subsanacion';

    /** @var string */
    public $codejercicio;

    /** @var string */
    public $creation_date;

    /** @var string */
    public $event;

    /** @var string */
    public $file_json;

    /** @var string */
    public $hash;

    /** @var int */
    public $id;

    /** @var int */
    public $idempresa;

    /** @var int */
    public $idfactura;

    /** @var string */
    public $mode;

    /** @var string */
    public $nick;

    /** @var string */
    public $status;

    public function delete(): bool
    {
        Tools::log()->error('not-permitted-delete');
        return false;
    }

    public function getCompany(): Empresa
    {
        $company = new Empresa();
        $company->loadFromCode($this->idempresa);
        return $company;
    }

    public function getExercise(): Ejercicio
    {
        $exercise = new Ejercicio();
        $exercise->loadFromCode($this->codejercicio);
        return $exercise;
    }

    public function getInvoice(): FacturaCliente
    {
        $invoice = new FacturaCliente();
        $invoice->loadFromCode($this->idfactura);
        return $invoice;
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'verifactu_registros_facturas';
    }

    public function test(): bool
    {
        $this->creation_date = $this->creation_date ?? Tools::dateTime();
        $this->event = Tools::noHtml($this->event);
        $this->file_json = Tools::noHtml($this->file_json);
        $this->hash = Tools::noHtml($this->hash);
        $this->mode = Tools::noHtml($this->mode);
        $this->nick = $this->nick ?? Session::user()->nick;
        $this->status = Tools::noHtml($this->status);
        return parent::test();
    }
}
