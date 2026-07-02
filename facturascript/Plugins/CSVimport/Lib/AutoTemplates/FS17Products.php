<?php
/**
 * Copyright (C) 2024-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\AutoTemplates;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\Utils;
use FacturaScripts\Core\DataSrc\Almacenes;
use FacturaScripts\Core\DataSrc\Impuestos;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\ConteoStock;
use FacturaScripts\Dinamic\Model\Fabricante;
use FacturaScripts\Dinamic\Model\Familia;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Stock;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\CSVimport\Contract\AutoTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class FS17Products implements AutoTemplateInterface
{
    const LIMIT_IMPORT = 500;

    /** @var ConteoStock */
    private $conteo;

    /** @var bool */
    private $continue = false;

    /** @var Familia[] */
    protected static $families = [];

    /** @var Fabricante[] */
    protected static $manufacturers = [];

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

            if ($csv['titles'][0] === 'referencia' && $csv['titles'][1] === 'codfamilia' && $csv['titles'][2] === 'codfabricante') {
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
            $product = new Producto();
            $where = [new DataBaseWhere('referencia', Tools::noHtml($row['referencia']))];
            if (empty($row['referencia']) ||
                $product->loadFromCode('', $where) && $mode === CsvFileTools::INSERT_MODE ||
                false === $product->loadFromCode('', $where) && $mode === CsvFileTools::UPDATE_MODE) {
                // product found
                continue;
            }

            if (false === $this->importProduct($product, $row)) {
                continue;
            }

            $this->continue = true;
            $offset++;
            $saveLines++;

            // obtenemos la variante por defecto del producto
            $variant = new Variante();
            if (false === $variant->loadFromCode('', $where)) {
                continue;
            }

            if (false === $this->importVariant($variant, $row)) {
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

        return true;
    }

    protected static function findFamily(string $codfamilia): bool
    {
        if (empty($codfamilia)) {
            return false;
        }

        // find in cache
        foreach (static::$families as $fam) {
            if ($fam->codfamilia === $codfamilia) {
                return true;
            }
        }

        // find in database
        $family = new Familia();
        if ($family->loadFromCode($codfamilia)) {
            static::$families[$codfamilia] = $family;
            return true;
        }

        return false;
    }

    protected static function findManufacturer(string $codfabricante): bool
    {
        if (empty($codfabricante)) {
            return false;
        }

        // find in cache
        foreach (static::$manufacturers as $man) {
            if ($man->codfabricante == $codfabricante) {
                return true;
            }
        }

        // find in database
        $manufacturer = new Fabricante();
        if ($manufacturer->loadFromCode($codfabricante)) {
            static::$manufacturers[$codfabricante] = $manufacturer;
            return true;
        }

        return false;
    }

    protected function getConteo(?string $codalmacen = null): ConteoStock
    {
        if (empty($this->conteo)) {
            $this->conteo = new ConteoStock();
            $this->conteo->codalmacen = $codalmacen;
            $this->conteo->observaciones = 'CSVimport FS17Products';
            $this->conteo->save();
        }

        return $this->conteo;
    }

    protected function importProduct(Producto &$product, array $row): bool
    {
        $product->loadFromData($row, ['idproducto']);

        // check family, manufacturer and tax
        if (false === static::findFamily($product->codfamilia)) {
            $product->codfamilia = null;
        }

        if (false === static::findManufacturer($product->codfabricante)) {
            $product->codfabricante = null;
        }

        foreach (Impuestos::all() as $tax) {
            if (Utils::floatcmp(CsvFileTools::formatFloat($row['iva']), $tax->iva)) {
                $product->codimpuesto = $tax->codimpuesto;
                break;
            }
        }

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
                CsvFileTools::formatFloat($row['stock'])
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
            $stock->cantidad = CsvFileTools::formatFloat($row['stock']);
        } else {
            // si lo encontramos, actualizamos la cantidad
            $stock->cantidad = CsvFileTools::formatFloat($row['stock']);
        }
        $stock->save();
    }

    protected function importVariant(Variante &$variant, array $row): bool
    {
        $variant->codbarras = $row['codbarras'];
        $variant->coste = CsvFileTools::formatFloat($row['coste']);
        $variant->precio = CsvFileTools::formatFloat($row['pvp']);
        return $variant->save();
    }
}
