<?php
/**
 * Copyright (C) 2019-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\RemesasSEPA\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\CuentaBanco;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Pais;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Plugins\RemesasSEPA\Lib\Accounting\RemesaToAccounting;

/**
 * Description of RemesaSEPA
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class RemesaSEPA extends Base\ModelOnChangeClass
{
    const STATUS_DONE = 'Realizada';
    const STATUS_NEW = 'Preparada';
    const STATUS_REVIEW = 'Revisar';
    const STATUS_WAIT = 'En trámite';
    const TYPE_B2B = 'B2B';
    const TYPE_CORE = 'CORE';
    const TYPE_COR1 = 'COR1';

    use Base\ModelTrait;

    /** @var bool */
    public $agrupar;

    /** @var string */
    public $codcuenta;

    /** @var string */
    public $coddivisa;

    /** @var string */
    public $codpago;

    /** @var string */
    public $creditorid;

    /** @var string */
    public $descripcion;

    /** @var string */
    public $estado;

    /** @var string */
    public $fecha;

    /** @var string */
    public $fechacargo;

    /** @var string */
    public $iban;

    /** @var int */
    public $idasiento;

    /** @var int */
    public $idempresa;

    /** @var int */
    public $idremesa;

    /** @var string */
    public $nick;

    /** @var string */
    public $nombre;

    /** @var string */
    public $swift;

    /** @var float */
    public $tasaconv;

    /** @var string */
    public $tipo;

    /** @var float */
    public $total;

    public function clear()
    {
        parent::clear();
        $this->agrupar = false;
        $this->coddivisa = Tools::settings('default', 'coddivisa');
        $this->estado = self::STATUS_NEW;
        $this->fecha = Tools::date();
        $this->fechacargo = Tools::date('+1 week');
        $this->idempresa = Tools::settings('default', 'idempresa');
        $this->nick = Session::user()->nick;
        $this->tasaconv = 1.0;
        $this->tipo = self::TYPE_CORE;
        $this->total = 0.0;
    }

    public function delete(): bool
    {
        // cannot delete a remittance with receipts
        $receipts = $this->getReceipts();
        if (count($receipts) > 0) {
            Tools::log()->warning('cant-delete-remittance-receipts');
            return false;
        }

        // remove accounting entry
        if ($this->idasiento && false === $this->getAccountingEntry()->delete()) {
            Tools::log()->warning('cant-remove-accounting-entry');
            return false;
        }

        return parent::delete();
    }

    public function getAccountingEntry(): Asiento
    {
        $accEntry = new Asiento();
        $accEntry->loadFromCode($this->idasiento);
        return $accEntry;
    }

    public function getBankAccount(): CuentaBanco
    {
        $bank = new CuentaBanco();
        $bank->loadFromCode($this->codcuenta);
        return $bank;
    }

    public function getCompany(): Empresa
    {
        $empresa = new Empresa();
        $empresa->loadFromCode($this->idempresa);
        return $empresa;
    }

    public function getNewCreditorID(): string
    {
        $empresa = $this->getCompany();
        $cif = str_replace([' ', '-'], ['', ''], ltrim($empresa->cifnif, '0'));

        // Remove ES from the beginning of the CIF if it exists
        if (substr(strtoupper($cif), 0, 2) === 'ES') {
            $cif = substr($cif, 2);
        }

        $pais = new Pais();
        $pais->loadFromCode($empresa->codpais);
        $codiso = empty($pais->codiso) ? 'ES' : $pais->codiso;

        // calculate control digits
        $cifAux = $this->words2numbers($cif . $codiso . '00');
        $total = 98 - ($cifAux % 97);

        $sufijo = empty($this->getBankAccount()->sufijosepa) ? '000' : $this->getBankAccount()->sufijosepa;
        return $codiso . sprintf('%02s', $total) . $sufijo . $cif;
    }

    public function getNombre(): string
    {
        // si ya tiene nombre, lo devolvemos
        if (false === empty($this->nombre)) {
            return Tools::noHtml($this->nombre);
        }

        // buscamos la última remesa para obtener su nombre
        $lastSEPA = new self();
        $where = [new DataBaseWhere('idempresa', $this->idempresa)];
        if ($lastSEPA->loadFromCode('', $where, ['idremesa' => 'DESC']) && false === empty($lastSEPA->nombre)) {
            return Tools::noHtml($lastSEPA->nombre);
        }

        // si no hay ninguna remesa, usamos el nombre de la empresa
        return Tools::noHtml($this->getCompany()->nombre);
    }

    public function getReceipts(): array
    {
        $receipt = new ReciboCliente();
        $where = [new DataBaseWhere('idremesa', $this->idremesa)];
        return $receipt->all($where, [], 0, 0);
    }

    public function install(): string
    {
        // needed dependencies
        new Asiento();

        return parent::install();
    }

    public static function primaryColumn(): string
    {
        return 'idremesa';
    }

    public static function tableName(): string
    {
        return 'remesas_sepa';
    }

    public function test(): bool
    {
        if (empty($this->creditorid)) {
            $this->creditorid = $this->getNewCreditorID();
        }

        if (empty($this->descripcion)) {
            $this->descripcion = $this->getBankAccount()->descripcion . ' ' . $this->fechacargo;
        }

        if (empty($this->iban)) {
            $this->iban = $this->getBankAccount()->iban;
        }

        if (empty($this->swift)) {
            $this->swift = $this->getBankAccount()->swift;
        }

        if (empty($this->estado)) {
            $this->estado = self::STATUS_NEW;
        }

        $this->descripcion = Tools::noHtml($this->descripcion);
        $this->nombre = $this->getNombre();

        return parent::test();
    }

    public function updateTotal(): void
    {
        if (false === $this->exists()) {
            return;
        }

        $total = 0.0;
        foreach ($this->getReceipts() as $receipt) {
            $total += $receipt->importe;
        }

        if ($total != $this->total) {
            $this->total = $total;
            $this->save();
        }
    }

    /**
     * @param string $field
     *
     * @return bool
     */
    protected function onChange($field)
    {
        switch ($field) {
            case 'codcuenta':
                $this->creditorid = $this->getNewCreditorID();
                $this->iban = $this->getBankAccount()->iban;
                $this->swift = $this->getBankAccount()->swift;
                break;

            case 'estado':
            case 'total':
                if ($this->idasiento && false === $this->getAccountingEntry()->delete()) {
                    return false;
                }
                $this->idasiento = null;
                if ($this->estado === self::STATUS_DONE) {
                    $accGenerator = new RemesaToAccounting();
                    $accGenerator->generate($this);
                }
                break;
        }

        return parent::onChange($field);
    }

    protected function setPreviousData(array $fields = [])
    {
        $more = ['codcuenta', 'estado', 'total'];
        parent::setPreviousData(array_merge($more, $fields));
    }

    /**
     * @param string $txt
     *
     * @return int
     */
    private function words2numbers($txt): int
    {
        $data = [
            'A' => 10, 'B' => 11, 'C' => 12, 'D' => 13, 'E' => 14, 'F' => 15, 'G' => 16, 'H' => 17,
            'I' => 18, 'J' => 19, 'K' => 20, 'L' => 21, 'M' => 22, 'N' => 23, 'O' => 24, 'P' => 25,
            'Q' => 26, 'R' => 27, 'S' => 28, 'T' => 29, 'U' => 30, 'V' => 31, 'W' => 32, 'X' => 33,
            'Y' => 34, 'Z' => 35
        ];

        $number = '';
        $pos = 0;
        while ($pos < strlen($txt)) {
            $char = substr($txt, $pos, 1);
            $number .= isset($data[$char]) ? $data[$char] : $char;
            $pos++;
        }

        return (int)$number;
    }
}
