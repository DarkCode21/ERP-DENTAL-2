<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\ConteoStock;
use FacturaScripts\Dinamic\Model\LineaConteoStock;
use FacturaScripts\Dinamic\Model\LineaConteoStockTraza as DinLineaConteoStockTraza;
use FacturaScripts\Dinamic\Model\ProductoLote as DinProductoLote;
use FacturaScripts\Dinamic\Model\User;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class LineaConteoStockTraza extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $creation_date;

    /** @var int */
    public $id;

    /** @var int */
    public $idconteo;

    /** @var int */
    public $idlinea;

    /** @var int */
    public $idlote;

    /** @var string */
    public $last_nick;

    /** @var string */
    public $last_update;

    /** @var string */
    public $nick;

    /** @var float */
    public $quantity;

    public function clear(): void
    {
        parent::clear();
        $this->quantity = 0;
    }

    public function getCounting(): ConteoStock
    {
        $counting = new ConteoStock();
        $counting->loadFromCode($this->idconteo);
        return $counting;
    }

    public function getCountingLine(): LineaConteoStock
    {
        $line = new LineaConteoStock();
        $line->loadFromCode($this->idlinea);
        return $line;
    }

    public function getLote(): DinProductoLote
    {
        $lote = new DinProductoLote();
        $lote->loadFromCode($this->idlote);
        return $lote;
    }

    public function install(): string
    {
        new User();
        new DinProductoLote();
        new ConteoStock();
        new LineaConteoStock();
        return parent::install();
    }

    public static function primaryColumn(): string
    {
        return "id";
    }

    public static function tableName(): string
    {
        return "stocks_lineasconteos_traza";
    }

    public function test(): bool
    {
        $this->creation_date = $this->creationdate ?? Tools::dateTime();
        $this->nick = $this->nick ?? Session::user()->nick;

        // comprobamos campos obligatorios
        if (empty($this->idlote)) {
            Tools::log()->warning('empty-lote');
            return false;
        } elseif (empty($this->idconteo)) {
            Tools::log()->warning('empty-counting');
            return false;
        } elseif (empty($this->idlinea)) {
            Tools::log()->warning('empty-counting-line');
            return false;
        }

        // obtenemos la línea del conteo
        $lineCounting = $this->getCountingLine();
        if (empty($lineCounting->primaryColumnValue())) {
            Tools::log()->warning('counting-line-not-found');
            return false;
        }

        // sumamos todas las líneas de trazabilidad que tiene esta línea de conteo
        $sum = $this->quantity;
        $where = [
            new DataBaseWhere('idlinea', $this->idlinea),
            new DataBaseWhere('idconteo', $this->idconteo),
            new DataBaseWhere('id', $this->id, '!='),
        ];
        foreach (DinLineaConteoStockTraza::all($where, [], 0, 0) as $lineTraza) {
            $sum += $lineTraza->quantity;
        }

        // si la suma de toda la trazabilidad de la línea es mayor que la cantidad de la línea, no permitimos guardar
        if ($sum > $lineCounting->cantidad) {
            Tools::log()->warning('quantity-exceeds-counting-line');
            return false;
        }

        return parent::test();
    }

    protected function saveUpdate(array $values = []): bool
    {
        $this->last_nick = Session::user()->nick;
        $this->last_update = Tools::dateTime();
        return parent::saveUpdate($values);
    }
}
