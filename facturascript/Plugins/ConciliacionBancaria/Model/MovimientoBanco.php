<?php
/**
 * Copyright (C) 2023-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\ConciliacionBancaria\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\CuentaBanco;
use FacturaScripts\Dinamic\Model\ReciboCliente;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class MovimientoBanco extends ModelClass
{
    use ModelTrait;

    /** @var float */
    public $amount;

    /** @var string */
    public $codcuenta;

    /** @var string */
    public $creationdate;

    /** @var string */
    public $date;

    /** @var int */
    public $id;

    /** @var string */
    public $lastnick;

    /** @var string */
    public $lastupdate;

    /** @var string */
    public $nick;

    /** @var string */
    public $observations;

    /** @var bool */
    public $reconciled;

    public function clear()
    {
        parent::clear();
        $this->reconciled = false;
    }

    public function getAccountingEntries(): array
    {
        $entryModel = new Asiento();
        $where = [new DataBaseWhere('idbankmovement', $this->id)];
        return $entryModel->all($where, [], 0, 0);
    }

    public function getCuenta(): CuentaBanco
    {
        $cuenta = new CuentaBanco();
        $cuenta->loadFromCode($this->codcuenta);
        return $cuenta;
    }

    public function getReceipts(): array
    {
        $receiptModel = new ReciboCliente();
        $where = [new DataBaseWhere('idbankmovement', $this->id)];
        return $receiptModel->all($where, [], 0, 0);
    }

    public static function primaryColumn(): string
    {
        return "id";
    }

    public static function tableName(): string
    {
        return "cuentasbanco_movimientos";
    }

    public function test(): bool
    {
        if ($this->primaryColumnValue()) {
            $this->lastnick = Session::user()->nick;
            $this->lastupdate = Tools::dateTime();
        } else {
            $this->creationdate = Tools::dateTime();
            $this->lastnick = null;
            $this->lastupdate = null;
            $this->nick = Session::user()->nick;
        }

        $this->codcuenta = Tools::noHtml($this->codcuenta);
        $this->observations = Tools::noHtml($this->observations);

        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        if ($type === 'list') {
            return $this->getCuenta()->url() . '&activetab=ListMovimientoBanco';
        }

        return parent::url($type, $list);
    }
}
