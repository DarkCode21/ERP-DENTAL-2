<?php
/**
 * Copyright (C) 2024-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\AutoTemplates;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Almacenes;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\ConteoStock;
use FacturaScripts\Dinamic\Model\Stock;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\CSVimport\Contract\AutoTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class FSVariants implements AutoTemplateInterface
{
    const LIMIT_IMPORT = 500;

    /** @var bool */
    private $continue = false;

    /** @var int */
    private $start = 0;

    /** @var int */
    private $total_lines = 0;

    public function continue(): bool
    {
        return $this->continue;
    }

    public function getTotalLines(): int
    {
        return $this->total_lines;
    }

    public function isValid(string $filePath, string $profile): bool
    {
        if ($profile !== 'variants') {
            return false;
        }

        // probamos empezando desde las líneas 0 a la 6
        foreach (range(0, 6) as $start) {
            $this->start = $start;
            $csv = CsvFileTools::read($filePath, $start, 0, 1);
            $this->total_lines = CsvFileTools::getTotalLines();

            if ($csv['titles'][0] === 'codbarras' && $csv['titles'][1] === 'coste') {
                return true;
            }
        }

        return false;
    }

    public function run(string $filePath, string $profile, string $mode, int &$offset, int &$saveLines): bool
    {
        $csv = CsvFileTools::read($filePath, $this->start, $offset, static::LIMIT_IMPORT);
        $this->total_lines = CsvFileTools::getTotalLines();
        $this->continue = false;

        foreach ($csv['data'] as $row) {
            $variant = new Variante();
            $where = [new DataBaseWhere('referencia', Tools::noHtml($row['referencia']))];
            if (empty($row['referencia'])
                || $variant->loadFromCode('', $where) && $mode === CsvFileTools::INSERT_MODE
                || false === $variant->loadFromCode('', $where) && $mode === CsvFileTools::UPDATE_MODE) {
                continue;
            }

            if (false === $this->importVariant($variant, $row)) {
                continue;
            }

            $this->importStock($variant);
            $this->continue = true;
            $offset++;
            $saveLines++;
        }

        return true;
    }

    protected function importStock(Variante $variant): void
    {
        // si el producto no controla stock, no hacemos nada
        if ($variant->getProducto()->nostock) {
            return;
        }

        $warehouse = Almacenes::get(Tools::settings('default', 'codalmacen'));
        if (empty($warehouse->primaryColumnValue())) {
            return;
        }

        // si tenemos el plugin StockAvanzado activo creamos un conteo de stock
        if (Plugins::isEnabled('StockAvanzado')) {
            // creamos el conteo de stock
            $conteo = new ConteoStock();
            $conteo->codalmacen = $warehouse->codalmacen;
            $conteo->observaciones = 'CSVimport FSVariants';
            if (false === $conteo->save()) {
                return;
            }

            // añadimos el producto al conteo
            $line = $conteo->addLine($variant->referencia, $variant->idproducto, CsvFileTools::formatFloat($variant->stockfis));
            if (empty($line->primaryColumnValue())) {
                return;
            }

            // ejecutamos el conteo
            $conteo->updateStock();
            return;
        }

        // si no tenemos el plugin StockAvanzado activo, creamos o actualizamos el stock directamente
        // buscamos el stock
        $stock = new Stock();
        $where = [
            new DataBaseWhere('codalmacen', $warehouse->codalmacen),
            new DataBaseWhere('referencia', $variant->referencia)
        ];
        if (false === $stock->loadFromCode('', $where)) {
            // si no lo encontramos, lo creamos
            $stock->codalmacen = $warehouse->codalmacen;
            $stock->idproducto = $variant->getProducto()->idproducto;
            $stock->referencia = $variant->referencia;
            $stock->cantidad = CsvFileTools::formatFloat($variant->stockfis);
        } else {
            // si lo encontramos, actualizamos la cantidad
            $stock->cantidad = CsvFileTools::formatFloat($variant->stockfis);
        }
        $stock->save();
    }

    protected function importVariant(Variante &$variant, array $row): bool
    {
        $variant->loadFromData($row, ['idvariante']);
        return $variant->save();
    }
}
