<?php
/**
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TPVneo\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Series;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\TicketPrinter as DinTicketPrinter;
use FacturaScripts\Dinamic\Model\TpvCaja as DinTpvCaja;
use FacturaScripts\Plugins\Tickets\Model\TicketPrinter;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class TpvTerminal extends ModelClass
{
    use ModelTrait;

    /** @var bool */
    public $active;

    /** @var bool */
    public $adddiscount;

    /** @var bool */
    public $addemptyline;

    /** @var int */
    public $budgetdateend;

    /** @var bool */
    public $changeprice;

    /** @var int */
    public $closeagent;

    /** @var string */
    public $codalmacen;

    /** @var string */
    public $codcliente;

    /** @var string */
    public $coddivisa;

    /** @var string */
    public $codpago;

    /** @var string */
    public $codserie;

    /** @var string */
    public $doctype;

    /** @var int */
    public $idtpv;

    /** @var int */
    public $idprinter;

    /** @var string */
    public $name;

    /** @var bool */
    public $sound;

    /** @var int */
    public $productlimit;

    /** @var string */
    public $ticketformat;

    public function clear()
    {
        parent::clear();
        $this->active = true;
        $this->addemptyline = false;
        $this->adddiscount = false;
        $this->budgetdateend = 1;
        $this->changeprice = false;
        $this->closeagent = 60;
        $this->codalmacen = self::toolBox()::appSettings()::get('default', 'codalmacen');
        $this->coddivisa = self::toolBox()::appSettings()::get('default', 'coddivisa');
        $this->codpago = self::toolBox()::appSettings()::get('default', 'codpago');
        $this->codserie = $this->getSimplifiedSerie();
        $this->doctype = 'FacturaCliente';
        $this->productlimit = 50;
        $this->sound = false;
    }

    /**
     * @return TpvCaja[]
     */
    public function getCajas(): array
    {
        $caja = new DinTpvCaja();
        $where = [new DataBaseWhere('idtpv', $this->idtpv)];
        $orderBy = ['fechaini' => 'DESC'];
        return $caja->all($where, $orderBy);
    }

    public function getMehodPayment(): FormaPago
    {
        $paymentMethod = new FormaPago();
        $paymentMethod->loadFromCode($this->codpago);
        return $paymentMethod;
    }

    public function getPrinter(): TicketPrinter
    {
        $printer = new DinTicketPrinter();
        $printer->loadFromCode($this->idprinter);
        return $printer;
    }

    public function install(): string
    {
        new TicketPrinter();
        return parent::install();
    }

    public function isOpen(): bool
    {
        foreach ($this->getCajas() as $caja) {
            if (empty($caja->fechafin)) {
                return true;
            }
        }

        return false;
    }

    public static function primaryColumn(): string
    {
        return 'idtpv';
    }

    public function save(): bool
    {
        if ($this->active === false) {
            // cerramos las cajas abiertas
            $modelCaja = new TpvCaja();
            $where = [
                new DataBaseWhere('idtpv', $this->idtpv),
                new DataBaseWhere('fechafin', null)
            ];
            foreach ($modelCaja->all($where, [], 0, 0) as $caja) {
                $caja->close(0.0);
                $caja->save();
            }
        }

        return parent::save();
    }

    public static function tableName(): string
    {
        return 'tpvsneo';
    }

    public function url(string $type = 'auto', string $list = 'ListTicketPrinter'): string
    {
        return parent::url($type, $list . '?activetab=List');
    }

    protected function getSimplifiedSerie(): string
    {
        foreach (Series::all() as $serie) {
            if (strtolower($serie->codserie) === 's') {
                return $serie->codserie;
            }
        }

        return self::toolBox()::appSettings()::get('default', 'codserie');
    }
}
