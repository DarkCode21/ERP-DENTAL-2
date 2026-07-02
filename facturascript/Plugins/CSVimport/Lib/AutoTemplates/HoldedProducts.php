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
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Stock;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\CSVimport\Contract\AutoTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

class HoldedProducts implements AutoTemplateInterface
{
    const LIMIT_IMPORT = 500;

    /** @var ConteoStock */
    private $conteo;

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
        if ($profile !== 'products') {
            return false;
        }

        // probamos empezando desde las líneas 0 a la 6
        foreach (range(0, 6) as $start) {
            $this->start = $start;
            $csv = CsvFileTools::read($filePath, $start, 0, 1);
            $this->total_lines = CsvFileTools::getTotalLines();

            if ($csv['titles'][0] === 'Creado' &&
                $csv['titles'][1] === 'Nombre' &&
                $csv['titles'][2] === 'Descripción' &&
                $csv['titles'][3] === 'SKU') {
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
            $product = $this->findProduct($row);
            if ($product->exists()) {
                continue;
            }

            if (false === $this->importProduct($product, $row)) {
                $this->continue = false;
                continue;
            }

            $this->continue = true;
            $saveLines++;

            // obtenemos la variante por defecto del producto
            $variant = new Variante();
            $where = [new DataBaseWhere('referencia', $product->referencia)];
            if (false === $variant->loadFromCode('', $where)) {
                continue;
            }

            if (false === $this->importVariant($variant, $row)) {
                $this->continue = false;
                continue;
            }

            $this->importStock($product, $variant, $row);
        }

        // actualizamos el conteo de stock si existe
        if (Plugins::isEnabled('StockAvanzado')) {
            $conteo = $this->getConteo();
            if ($conteo->exists()) {
                $conteo->updateStock();
                $this->conteo = null;
            }
        }

        $offset += static::LIMIT_IMPORT;
        return true;
    }

    protected function findProduct(array $row): Producto
    {
        $producto = new Producto();

        // buscamos por SKU
        if (!empty($row['SKU'])) {
            $where = [new DataBaseWhere('referencia', Tools::noHtml($row['SKU']))];
            if ($producto->loadFromCode('', $where)) {
                return $producto;
            }
        }

        // buscamos por código de barras
        if (!empty($row['Código'])) {
            foreach (explode(' ', $row['Código']) as $code) {
                $variante = new Variante();
                $where = [new DataBaseWhere('codbarras', Tools::noHtml($code))];
                if ($variante->loadFromCode('', $where)) {
                    return $variante->getProducto();
                }
            }
        }

        // buscamos por descripción
        if (!empty($row['Nombre'])) {
            $where = [new DataBaseWhere('descripcion', Tools::noHtml($row['Nombre']))];
            $producto->loadFromCode('', $where);
        }

        return $producto;
    }

    protected function getConteo(?string $codalmacen = null): ConteoStock
    {
        if (empty($this->conteo)) {
            $this->conteo = new ConteoStock();
            $this->conteo->codalmacen = $codalmacen;
            $this->conteo->observaciones = 'CSVimport HoldedProducts';
            $this->conteo->save();
        }

        return $this->conteo;
    }

    protected function importProduct(Producto &$product, array $row): bool
    {
        $product->descripcion = $row['Nombre'];
        $product->referencia = $row['SKU'];
        $product->fechaalta = CsvFileTools::formatDate($row['Creado']);
        $product->observaciones = $row['Descripción'] . ' ' . $row['Tags'];
        $product->precio = CsvFileTools::formatFloat($row['Subtotal']);
        return $product->save();
    }

    protected function importStock(Producto $product, Variante $variant, array $row): void
    {
        // si el producto no controla stock, no hacemos nada
        if ($product->nostock) {
            return;
        }

        $warehouse = Almacenes::get(Tools::settings('default', 'codalmacen'));
        if (empty($warehouse->primaryColumnValue())) {
            return;
        }

        // si tenemos el plugin StockAvanzado activo creamos un conteo de stock
        if (Plugins::isEnabled('StockAvanzado')) {
            // añadimos el producto al conteo
            $this->getConteo($warehouse->codalmacen)->addLine(
                $variant->referencia,
                $variant->idproducto,
                CsvFileTools::formatFloat($row['Stock'])
            );
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
            $stock->cantidad = CsvFileTools::formatFloat($row['Stock']);
        } else {
            // si lo encontramos, actualizamos la cantidad
            $stock->cantidad = CsvFileTools::formatFloat($row['Stock']);
        }
        $stock->save();
    }

    protected function importVariant(Variante &$variant, array $row): bool
    {
        $variant->coste = CsvFileTools::formatFloat($row['Coste']);

        // asignamos el código de barras
        if (!empty($row['Código'])) {
            foreach (explode(' ', $row['Código']) as $code) {
                if (strlen(Tools::noHtml($code)) < 20) {
                    $variant->codbarras = $code;
                    break;
                }
            }
        }

        return $variant->save();
    }
}
