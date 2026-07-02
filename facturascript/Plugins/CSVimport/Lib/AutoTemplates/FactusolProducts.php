<?php
/**
 * Copyright (C) 2024-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\AutoTemplates;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Almacenes;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Atributo;
use FacturaScripts\Dinamic\Model\AtributoValor;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Stock;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\CSVimport\Contract\AutoTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;
use FacturaScripts\Plugins\StockAvanzado\Model\ConteoStock;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class FactusolProducts implements AutoTemplateInterface
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

            if ($csv['titles'][0] === 'Código' &&
                $csv['titles'][1] === 'Descripción' &&
                $csv['titles'][2] === 'Referencia' &&
                $csv['titles'][3] === 'Prov.') {
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

        $last_id = 0;
        foreach ($csv['data'] as $row) {
            // Is this a variant?
            if (!empty($last_id) && empty($row['Código']) && empty($row['Descripción']) && !empty($row['Referencia'])) {
                $this->saveVariant($last_id, $row);
                continue;
            }

            // buscamos el producto
            $product = new Producto();
            $code = empty($row['Referencia']) ? $row['Código'] : $row['Referencia'];
            $where = [new DataBaseWhere('referencia', Tools::noHtml($code))];
            if (empty($code) ||
                ($product->loadFromCode('', $where) && $mode === CsvFileTools::INSERT_MODE) ||
                (false === $product->loadFromCode('', $where) && $mode === CsvFileTools::UPDATE_MODE)) {
                continue;
            }

            if (false === $this->importProduct($product, $code, $row)) {
                continue;
            }

            $last_id = $product->primaryColumnValue();
            $this->continue = true;
            $saveLines++;

            // obtenemos la variante por defecto del producto
            $variant = new Variante();
            $where = [new DataBaseWhere('referencia', $product->referencia)];
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

        $offset += static::LIMIT_IMPORT;
        return true;
    }

    protected function getConteo(?string $codalmacen = null): ConteoStock
    {
        if (empty($this->conteo)) {
            $this->conteo = new ConteoStock();
            $this->conteo->codalmacen = $codalmacen;
            $this->conteo->observaciones = 'CSVimport FactusolProducts';
            $this->conteo->save();
        }

        return $this->conteo;
    }

    protected function getIdAtributo($attName, $attValue)
    {
        $atributo = new Atributo();
        if (false === $atributo->loadFromCode($attName)) {
            $atributo->codatributo = $attName;
            $atributo->nombre = $attName;
            $atributo->save();
        }

        $atValor = new AtributoValor();
        $where = [
            new DataBaseWhere('codatributo', $attName),
            new DataBaseWhere('valor', $attValue)
        ];
        if (false === $atValor->loadFromCode('', $where)) {
            $atValor->codatributo = $attName;
            $atValor->valor = $attValue;
            $atValor->save();
        }

        return $atValor->primaryColumnValue();
    }

    protected function getNewReference($prefix): string
    {
        $variant = new Variante();
        for ($num = 1; $num < 100; $num++) {
            $ref = $prefix . $num;
            $where = [new DataBaseWhere('referencia', $ref)];
            if (false === $variant->loadFromCode('', $where)) {
                return $ref;
            }
        }

        return $prefix . mt_rand(101, 9999);
    }

    protected function importProduct(Producto &$product, string $code, array $row): bool
    {
        $product->referencia = $code;
        $product->descripcion = $row['Descripción'];
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
                CsvFileTools::formatFloat($row['Stock()'])
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
            $stock->cantidad = CsvFileTools::formatFloat($row['Stock()']);
        } else {
            // si lo encontramos, actualizamos la cantidad
            $stock->cantidad = CsvFileTools::formatFloat($row['Stock()']);
        }
        $stock->save();
    }

    protected function importVariant(Variante &$variant, array $row): bool
    {
        $variant->coste = CsvFileTools::formatFloat($row['Costo']);
        $variant->precio = CsvFileTools::formatFloat($row['Venta']);
        $variant->margen = CsvFileTools::formatFloat($row['Margen']);
        return $variant->save();
    }

    protected function saveVariant($idproduct, $line): void
    {
        if ($line['Referencia'] == 'Talla' && $line['Prov.'] == 'Color') {
            return;
        }

        $product = new Producto();
        if (false === $product->loadFromCode($idproduct)) {
            return;
        }

        $variant = new Variante();
        $variant->coste = CsvFileTools::formatFloat($line['Costo']);
        $variant->idatributovalor1 = $this->getIdAtributo('Talla', $line['Referencia']);
        $variant->idatributovalor2 = $this->getIdAtributo('Color', $line['Prov.']);
        $variant->idproducto = $idproduct;
        $variant->precio = CsvFileTools::formatFloat($line['Venta']);
        $variant->referencia = $this->getNewReference($product->referencia . '-');
        $variant->stockfis = CsvFileTools::formatFloat($line['Stock()']);
        $variant->save();
    }
}
