<?php
/**
 * This file is part of RestauranteTPV plugin for FacturaScripts
 * Copyright (C) 2026 Interiberica Informatica
 */

namespace FacturaScripts\Plugins\RestauranteTPV\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestCajaFactura;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestCajaMovimiento;

class RestCaja extends ModelClass
{
    use ModelTrait;

    public $codserie;
    public $diferencia;
    public $dinerofin;
    public $dineroini;
    public $fechafin;
    public $fechaini;
    public $idcaja;
    public $ingresos;
    public $nick;
    public $numtickets;
    public $observaciones;

    public function clear(): void
    {
        parent::clear();
        $this->codserie = '';
        $this->diferencia = 0.0;
        $this->dinerofin = 0.0;
        $this->dineroini = 0.0;
        $this->fechafin = null;
        $this->fechaini = date(self::DATETIME_STYLE);
        $this->ingresos = 0.0;
        $this->numtickets = 0;
        $this->observaciones = '';
    }

    public function close(float $finalAmount): void
    {
        $this->dinerofin = $finalAmount;
        $this->diferencia = $finalAmount - $this->getTotalInBox();
        $this->fechafin = date(self::DATETIME_STYLE);
    }

    public function addMovement(string $type, float $amount, string $concept, ?string $nick = null): bool
    {
        $movement = new RestCajaMovimiento();
        $movement->idcaja = $this->idcaja;
        $movement->tipo = $type === 'out' ? 'out' : 'in';
        $movement->importe = abs($amount);
        $movement->concepto = $concept;
        $movement->nick = $nick;
        return $movement->save();
    }

    public function addSale(int $idfactura, float $importe, string $nick): bool
    {
        $entry = new RestCajaFactura();
        $where = [new DataBaseWhere('idfactura', $idfactura)];
        if ($entry->loadFromCode('', $where)) {
            return false;
        }

        $entry->idcaja = $this->idcaja;
        $entry->idfactura = $idfactura;
        $entry->importe = $importe;
        $entry->nick = $nick;
        if (false === $entry->save()) {
            return false;
        }

        $this->ingresos += $importe;
        $this->numtickets += 1;
        return $this->save();
    }

    /**
     * @return RestCajaMovimiento[]
     */
    public function getMovements(): array
    {
        $movement = new RestCajaMovimiento();
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

    public static function primaryColumn(): string
    {
        return 'idcaja';
    }

    public static function tableName(): string
    {
        return 'rest_cajas';
    }
}
