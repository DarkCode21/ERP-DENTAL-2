<?php
/**
 * Copyright (C) 2026
 */

namespace FacturaScripts\Plugins\TPVneo\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

class TpvCajaMovimiento extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $concepto;

    /** @var string */
    public $fecha;

    /** @var int */
    public $idcaja;

    /** @var int */
    public $idmov;

    /** @var int */
    public $idtpv;

    /** @var float */
    public $importe;

    /** @var string */
    public $nick;

    /** @var string */
    public $tipo;

    public function clear()
    {
        parent::clear();
        $this->fecha = date(self::DATETIME_STYLE);
        $this->importe = 0.0;
        $this->tipo = 'in';
    }

    public static function primaryColumn(): string
    {
        return 'idmov';
    }

    public static function tableName(): string
    {
        return 'tpvsneo_caja_movimientos';
    }

    public function test(): bool
    {
        $this->concepto = self::toolBox()::utils()::noHtml((string) $this->concepto);
        $this->tipo = $this->tipo === 'out' ? 'out' : 'in';
        $this->importe = abs((float) $this->importe);

        if ($this->importe <= 0) {
            self::toolBox()::i18nLog()->warning('amount-is-required');
            return false;
        }

        if (empty(trim((string) $this->concepto))) {
            self::toolBox()::i18nLog()->warning('description-is-required');
            return false;
        }

        return parent::test();
    }
}
