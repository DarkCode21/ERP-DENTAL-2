<?php
/**
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TPVneo\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Session;
use FacturaScripts\Dinamic\Model\TpvTerminal as DinTpvTerminal;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class TpvCaja extends ModelClass
{
    use ModelTrait;

    /** @var float */
    public $diferencia;

    /** @var float */
    public $dinerofin;

    /** @var float */
    public $dineroini;

    /** @var string */
    public $fechafin;

    /** @var string */
    public $fechaini;

    /** @var int */
    public $idcaja;

    /** @var int */
    public $idtpv;

    /** @var float */
    public $ingresos;

    /** @var string */
    public $nick;

    /** @var int */
    public $numtickets;

    /** @var string */
    public $observaciones;

    public function clear()
    {
        parent::clear();
        $this->diferencia = 0.0;
        $this->dinerofin = 0.0;
        $this->dineroini = 0.0;
        $this->fechaini = date(self::DATETIME_STYLE);
        $this->ingresos = 0.0;
        $this->numtickets = 0;
    }

    public function close(float $finalAmount): void
    {
        $this->dinerofin = $finalAmount;
        $this->diferencia = $finalAmount - $this->getTotalInBox();
        $this->fechafin = date(self::DATETIME_STYLE);
    }

    public function addMovement(string $type, float $amount, string $concept, ?string $nick = null): bool
    {
        $movement = new TpvCajaMovimiento();
        $movement->idcaja = $this->idcaja;
        $movement->idtpv = $this->idtpv;
        $movement->tipo = $type === 'out' ? 'out' : 'in';
        $movement->importe = abs($amount);
        $movement->concepto = $concept;
        $movement->nick = $nick ?: (Session::get('user')->nick ?? null);
        return $movement->save();
    }

    public function getDocs(?TpvTerminal $tpv = null): array
    {
        if (is_null($tpv)) {
            $tpv = $this->getTerminal();
        }
        if (false === $tpv->exists()) {
            return [];
        }

        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $tpv->doctype;
        $docModel = new $modelClass();
        $where = [new DataBaseWhere('idcaja', $this->idcaja)];
        $orderBy = ['fecha' => 'DESC', 'hora' => 'DESC'];
        return $docModel->all($where, $orderBy, 0, 0);
    }

    public function getPaymentBreakdown(): array
    {
        $payments = [];
        $tpv = $this->getTerminal();
        foreach ($this->getDocs($tpv) as $doc) {
            if ($tpv->doctype === 'AlbaranCliente') {
                $paymentMethod = $doc->getPaymentMethod();
                if (!isset($payments[$paymentMethod->codpago])) {
                    $payments[$paymentMethod->codpago] = [
                        'descripcion' => $paymentMethod->descripcion,
                        'total' => $doc->total,
                    ];
                    continue;
                }
                $payments[$paymentMethod->codpago]['total'] += $doc->total;
                continue;
            }

            foreach ($doc->getReceipts() as $receipt) {
                $paymentMethod = $receipt->getPaymentMethod();
                if (!isset($payments[$paymentMethod->codpago])) {
                    $payments[$paymentMethod->codpago] = [
                        'descripcion' => $paymentMethod->descripcion,
                        'total' => $receipt->importe,
                    ];
                    continue;
                }
                $payments[$paymentMethod->codpago]['total'] += $receipt->importe;
            }
        }
        return $payments;
    }

    /**
     * @return TpvCajaMovimiento[]
     */
    public function getMovements(): array
    {
        $movement = new TpvCajaMovimiento();
        $where = [new DataBaseWhere('idcaja', $this->idcaja)];
        $orderBy = ['fecha' => 'DESC', 'idmov' => 'DESC'];
        return $movement->all($where, $orderBy, 0, 0);
    }

    public function getManualIncomes(): float
    {
        $total = 0.0;
        foreach ($this->getMovements() as $movement) {
            if ($movement->tipo === 'in') {
                $total += (float) $movement->importe;
            }
        }
        return $total;
    }

    public function getManualOutcomes(): float
    {
        $total = 0.0;
        foreach ($this->getMovements() as $movement) {
            if ($movement->tipo === 'out') {
                $total += (float) $movement->importe;
            }
        }
        return $total;
    }

    public function getManualBalance(): float
    {
        return $this->getManualIncomes() - $this->getManualOutcomes();
    }

    public function getTotalInBox(): float
    {
        return (float) $this->dineroini + (float) $this->ingresos + $this->getManualBalance();
    }

    public function getTerminal(): TpvTerminal
    {
        $tpv = new DinTpvTerminal();
        $tpv->loadFromCode($this->idtpv);
        return $tpv;
    }

    public function getTotalEstimated(): string
    {
        $estimated = 0;
        $tpv = $this->getTerminal();
        foreach ($this->getDocs($tpv) as $doc) {
            if ($tpv->doctype === 'AlbaranCliente') {
                if ($doc->codpago === $tpv->codpago) {
                    $estimated += $doc->total;
                }
                continue;
            }

            foreach ($doc->getReceipts() as $receipt) {
                if ($receipt->codpago === $tpv->codpago) {
                    $estimated += $receipt->importe;
                }
            }
        }

        return $estimated;
    }

    public static function primaryColumn(): string
    {
        return 'idcaja';
    }

    public static function tableName(): string
    {
        return 'tpvsneo_cajas';
    }

    public function test(): bool
    {
        // escapamos el html de observaciones
        $this->observaciones = self::toolBox()::utils()::noHtml($this->observaciones);

        return parent::test();
    }
}