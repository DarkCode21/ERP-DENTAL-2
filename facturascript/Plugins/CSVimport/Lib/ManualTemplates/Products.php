<?php
/**
 * Copyright (C) 2020-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\ManualTemplates;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Almacenes;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ProductType;
use FacturaScripts\Dinamic\Lib\RegimenIVA;
use FacturaScripts\Dinamic\Model\ConteoStock;
use FacturaScripts\Dinamic\Model\Fabricante;
use FacturaScripts\Dinamic\Model\Familia;
use FacturaScripts\Dinamic\Model\Impuesto;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\ProductoProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Dinamic\Model\Stock;
use FacturaScripts\Dinamic\Model\Subcuenta;
use FacturaScripts\Dinamic\Model\Tarifa;
use FacturaScripts\Dinamic\Model\TarifaProducto;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\CSVimport\Contract\ManualTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class Products extends ManualTemplateClass implements ManualTemplateInterface
{
    /** @var ConteoStock */
    protected $conteo;

    /** @var Familia[] */
    protected $families = [];

    /** @var Fabricante[] */
    protected $manufacturers = [];

    public function getDataFields(): array
    {
        $fields = [
            'productos.referencia' => ['title' => 'reference'],
            'variantes.codbarras' => ['title' => 'barcode'],
            'productos.descripcion' => ['title' => 'description'],
            'productos.observaciones' => ['title' => 'observations'],
            'variantes.precio' => ['title' => 'price'],
            'variantes.precioconiva' => ['title' => 'price-with-tax'],
            'productos.codimpuesto' => ['title' => 'tax-code'],
            'productos.codfabricante' => ['title' => 'manufacturer'],
            'productos.codfamilia' => ['title' => 'family'],
            'productos.pctimpuesto' => ['title' => 'pct-vat'],
            'productos.codsubcuentacom' => ['title' => 'subaccount-purchases'],
            'productos.codsubcuentaven' => ['title' => 'subaccount-sales'],
            'productos.tipo' => ['title' => 'type'],
            'productos.excepcioniva' => ['title' => 'vat-exception'],
            'productos.nostock' => ['title' => 'no-stock'],
            'productos.secompra' => ['title' => 'for-purchase'],
            'productos.sevende' => ['title' => 'for-sale'],
            'productos.ventasinstock' => ['title' => 'allow-sale-without-stock'],
            'productos.bloqueado' => ['title' => 'blocked'],
            'productos.publico' => ['title' => 'public'],
            'variantes.coste' => ['title' => 'cost-price'],
            'variantes.margen' => ['title' => 'margin'],
            'stocks.cantidad' => ['title' => 'stock'],
            'stocks.codalmacen' => ['title' => 'warehouse'],
            'productosprov.codproveedor' => ['title' => 'supplier'],
            'productosprov.preciocompra' => ['title' => 'purchase-price'],
            'productosprov.dtopor' => ['title' => 'purchase-discount']
        ];

        if (Plugins::isEnabled('TarifasAvanzadas')) {
            foreach (Tarifa::all([], [], 0, 0) as $tarifa) {
                $fields['articulostarifas.precio|' . $tarifa->codtarifa] = [
                    'title' => $tarifa->nombre . ' - ' . Tools::lang()->trans('price')
                ];
                $fields['articulostarifas.precioconiva|' . $tarifa->codtarifa] = [
                    'title' => $tarifa->nombre . ' - ' . Tools::lang()->trans('price-with-tax')
                ];
            }
        }

        return $fields;
    }

    public function getFieldsToColumn(): array
    {
        return [];
    }

    public static function getProfile(): string
    {
        return 'products';
    }

    public function getRequiredFieldsAnd(): array
    {
        return [];
    }

    public function getRequiredFieldsOr(): array
    {
        return ['productos.referencia', 'productos.descripcion'];
    }

    public function import(): array
    {
        $data = parent::import();

        // actualizamos el conteo de stock si existe
        if (Plugins::isEnabled('StockAvanzado')) {
            $conteo = $this->getConteo();
            if ($conteo->exists()) {
                $conteo->updateStock();
                $this->conteo = null;
            }
        }

        return $data;
    }

    public function importItem(array $item): bool
    {
        $where = [];
        if (isset($item['productos.referencia']) && !empty($item['productos.referencia'])) {
            $where[] = new DataBaseWhere('referencia', $item['productos.referencia']);
        } elseif (isset($item['productos.descripcion']) && !empty($item['productos.descripcion'])) {
            $where[] = new DataBaseWhere('descripcion', $item['productos.descripcion']);
        }
        if (empty($where)) {
            return false;
        }

        $product = new Producto();
        if ($product->loadFromCode('', $where) && $this->model->mode === CsvFileTools::INSERT_MODE
            || false === $product->loadFromCode('', $where) && $this->model->mode === CsvFileTools::UPDATE_MODE) {
            return false;
        }

        if (false === $this->setModelValues($product, $item, 'productos.')) {
            return false;
        }

        // empty reference?
        if (empty($product->referencia)) {
            $product->referencia = $product->newCode('referencia');
        }

        if (false === $this->findFamily($product->codfamilia)) {
            $product->codfamilia = null;
        }

        if (false === $this->findManufacturer($product->codfabricante)) {
            $product->codfabricante = null;
        }

        $this->setImpuesto($product, $item);
        if ($product->save()) {
            $this->importVariant($product, $item);
            $this->importTarifas($product->referencia, $item);
            return true;
        }

        return false;
    }

    /**
     * @param string $code
     * @param int $len
     *
     * @return string
     */
    protected function cleanCode($code, $len): string
    {
        $table = [
            'Š' => 'S', 'š' => 's', 'Đ' => 'Dj', 'đ' => 'dj', 'Ž' => 'Z', 'ž' => 'z', 'Č' => 'C', 'č' => 'c', 'Ć' => 'C', 'ć' => 'c',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E',
            'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O',
            'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'B', 'ß' => 'Ss',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'a', 'ç' => 'c', 'è' => 'e', 'é' => 'e',
            'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o',
            'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ý' => 'y', 'þ' => 'b',
            'ÿ' => 'y', 'Ŕ' => 'R', 'ŕ' => 'r',
        ];
        $text = preg_replace('/[^a-z0-9\-_]/i', '', strtr($code, $table));
        return strlen($text) > $len ? substr($text, 0, $len) : $text;
    }

    /**
     * @param string $codfamilia
     *
     * @return bool
     */
    protected function findFamily(&$codfamilia): bool
    {
        if (empty($codfamilia)) {
            return false;
        }

        // find in cache
        $newDescripcion = Tools::noHtml($codfamilia);
        $newCodfamilia = $this->cleanCode($newDescripcion, 8);
        foreach ($this->families as $fam) {
            if ($fam->codfamilia == $newCodfamilia || $fam->descripcion == $newDescripcion) {
                $codfamilia = $fam->codfamilia;
                return true;
            }
        }

        // find in database
        $family = new Familia();
        $where = [
            new DataBaseWhere('codfamilia', $newCodfamilia, '=', 'OR'),
            new DataBaseWhere('descripcion', $newDescripcion, '=', 'OR')
        ];
        if ($family->loadFromCode('', $where)) {
            $codfamilia = $family->codfamilia;
            $this->families[$codfamilia] = $family;
            return true;
        }

        // create family
        $family->codfamilia = $newCodfamilia;
        $family->descripcion = $newDescripcion;
        if ($family->save()) {
            $codfamilia = $family->codfamilia;
            $this->families[$codfamilia] = $family;
            return true;
        }

        return false;
    }

    /**
     * @param string $codfabricante
     *
     * @return bool
     */
    protected function findManufacturer(&$codfabricante): bool
    {
        if (empty($codfabricante)) {
            return false;
        }

        // find in cache
        $newNombre = Tools::noHtml($codfabricante);
        $newCodfabricante = $this->cleanCode($codfabricante, 8);
        foreach ($this->manufacturers as $man) {
            if ($man->codfabricante == $newCodfabricante || $man->nombre == $newNombre) {
                $codfabricante = $man->codfabricante;
                return true;
            }
        }

        // find in database
        $manufacturer = new Fabricante();
        $where = [
            new DataBaseWhere('codfabricante', $newCodfabricante, '=', 'OR'),
            new DataBaseWhere('nombre', $newNombre, '=', 'OR')
        ];
        if ($manufacturer->loadFromCode('', $where)) {
            $codfabricante = $manufacturer->codfabricante;
            $this->manufacturers[$codfabricante] = $manufacturer;
            return true;
        }

        // create manufacturer
        $manufacturer->codfabricante = $newCodfabricante;
        $manufacturer->nombre = $newNombre;
        if ($manufacturer->save()) {
            $codfabricante = $manufacturer->codfabricante;
            $this->manufacturers[$codfabricante] = $manufacturer;
            return true;
        }

        return false;
    }

    protected function getConteo(?string $codalmacen = null): ConteoStock
    {
        if (empty($this->conteo)) {
            $this->conteo = new ConteoStock();
            $this->conteo->codalmacen = $codalmacen;
            $this->conteo->observaciones = 'CSVimport Products';
            $this->conteo->save();
        }

        return $this->conteo;
    }

    protected function importProveedor(Variante $variant, array $item): bool
    {
        if (empty($item['productosprov.codproveedor'] ?? '')) {
            return true;
        }

        // buscar si existe el proveedor
        $proveedor = new Proveedor();
        if (false === $proveedor->loadFromCode($item['productosprov.codproveedor'])) {
            return true;
        }

        // buscar si existe el producto en el proveedor
        $productoProv = new ProductoProveedor();
        $where = [
            new DataBaseWhere('codproveedor', $item['productosprov.codproveedor']),
            new DataBaseWhere('referencia', $variant->referencia)
        ];
        if (false === $productoProv->loadFromCode('', $where)) {
            // si no existe, lo creamos
            $productoProv->codproveedor = $proveedor->codproveedor;
            $productoProv->coddivisa = Tools::settings('default', 'coddivisa');
            $productoProv->idproducto = $variant->idproducto;
            $productoProv->referencia = $variant->referencia;
        }

        if (isset($item['productosprov.dtopor']) && $item['productosprov.dtopor']) {
            $productoProv->dtopor = CsvFileTools::formatFloat($item['productosprov.dtopor']);
        }

        if (isset($item['productosprov.preciocompra']) && $item['productosprov.preciocompra']) {
            $productoProv->precio = CsvFileTools::formatFloat($item['productosprov.preciocompra']);
        } else {
            $productoProv->precio = empty($variant->coste) ? $variant->precio : $variant->coste;
        }

        $productoProv->neto = $productoProv->precio;
        return $productoProv->save();
    }

    protected function importStock(Producto $product, Variante $variant, array $item): bool
    {
        // si el producto no controla stock, no hacemos nada
        if ($product->nostock) {
            return true;
        }

        // si el stock no es numérico, no hacemos nada
        if (false === is_numeric($item['stocks.cantidad'] ?? '')) {
            return true;
        }

        // si tenemos un almacén, lo buscamos, si no usamos el predeterminado
        $codalmacen = isset($item['stocks.codalmacen']) && $item['stocks.codalmacen']
            ? $item['stocks.codalmacen']
            : Tools::settings('default', 'codalmacen');

        $warehouse = Almacenes::get($codalmacen);
        if (empty($warehouse->primaryColumnValue())) {
            return true;
        }

        // si tenemos el plugin StockAvanzado activo creamos un conteo de stock
        if (Plugins::isEnabled('StockAvanzado')) {
            // añadimos el producto al conteo
            $this->getConteo($codalmacen)->addLine(
                $variant->referencia,
                $variant->idproducto,
                CsvFileTools::formatFloat($item['stocks.cantidad'])
            );
            return true;
        }

        // si no tenemos el plugin StockAvanzado activo, creamos o actualizamos el stock directamente
        // buscamos el stock
        $stock = new Stock();
        $where = [
            new DataBaseWhere('codalmacen', $codalmacen),
            new DataBaseWhere('referencia', $variant->referencia)
        ];
        if (false === $stock->loadFromCode('', $where)) {
            // si no lo encontramos, lo creamos
            $stock->codalmacen = $codalmacen;
            $stock->idproducto = $variant->getProducto()->idproducto;
            $stock->referencia = $variant->referencia;
        }
        return $this->setModelValues($stock, $item, 'stocks.') && $stock->save();
    }

    protected function importTarifas(string $referencia, array $item)
    {
        if (false === Plugins::isEnabled('TarifasAvanzadas')) {
            return;
        }

        $prefix = 'articulostarifas.';

        // buscamos si en el array item existe algún campo que su key empiece por el prefijo
        $tarifas = array_filter($item, function ($key) use ($prefix) {
            return strpos($key, $prefix) === 0;
        }, ARRAY_FILTER_USE_KEY);

        // recorremos las tarifas del item
        foreach ($tarifas as $key => $value) {
            // si el valor está vacío continuamos
            if ($value == '') {
                continue;
            }

            // eliminamos de la key el prefijo
            $key = str_replace($prefix, '', $key);

            // obtenemos el nombre de la columna y el codtarifa
            list($column, $codtarifa) = explode('|', $key);

            // comprobamos que existe la tarifa
            $tarifaModel = new Tarifa();
            if (false === $tarifaModel->loadFromCode($codtarifa)) {
                continue;
            }

            // buscamos si existe la tarifa en el producto
            $tarifaProducto = new TarifaProducto();
            $where = [
                new DataBaseWhere('codtarifa', $codtarifa),
                new DataBaseWhere('referencia', $referencia)
            ];
            if (false === $tarifaProducto->loadFromCode('', $where)) {
                // si no existe, la creamos
                $tarifaProducto->codtarifa = $codtarifa;
                $tarifaProducto->referencia = $referencia;
            }

            switch ($column) {
                case 'precio':
                    $tarifaProducto->pvp = CsvFileTools::formatFloat($value);
                    break;
                case 'precioconiva':
                    $tarifaProducto->setPriceWithTax(CsvFileTools::formatFloat($value));
                    break;
            }

            $tarifaProducto->save();
        }
    }

    protected function importVariant(Producto $product, array $item): bool
    {
        foreach ($product->getVariants() as $variant) {
            if ($variant->referencia != $product->referencia) {
                continue;
            }

            if (false === $this->setModelValues($variant, $item, 'variantes.')) {
                return false;
            }
            $this->setPrice($product, $variant, $item);
            if (false === $variant->save()) {
                return false;
            }

            return $this->importStock($product, $variant, $item) && $this->importProveedor($variant, $item);
        }

        return false;
    }

    protected function setImpuesto(&$product, $item)
    {
        if (false === isset($item['productos.pctimpuesto']) || empty($item['productos.pctimpuesto'])) {
            return;
        }

        // buscamos si existe el % del impuesto
        $impuesto = new Impuesto();
        $where = [
            new DataBaseWhere('iva', $item['productos.pctimpuesto']),
            new DataBaseWhere('tipo', 1)
        ];
        if ($impuesto->loadFromCode('', $where)) {
            $product->codimpuesto = $impuesto->codimpuesto;
        }
    }

    protected function setModelValues(ModelClass &$model, array $values, string $prefix): bool
    {
        if (false === parent::setModelValues($model, $values, $prefix)) {
            return false;
        }

        foreach ($model->getModelFields() as $key => $field) {
            if (!isset($values[$prefix . $key])) {
                continue;
            }

            switch ($field['name']) {
                case 'nostock':
                case 'secompra':
                case 'sevende':
                case 'ventasinstock':
                case 'bloqueado':
                case 'publico':
                    $model->{$key} = CsvFileTools::formatBool($values[$prefix . $key]);
                    break;

                case 'codsubcuentacom':
                case 'codsubcuentaven':
                    $subaccount = new Subcuenta();
                    $where = [
                        new DataBaseWhere('codsubcuenta', $values[$prefix . $key]),
                        new DataBaseWhere('codejercicio', date('Y'))
                    ];
                    if ($subaccount->loadFromCode('', $where)) {
                        $model->{$key} = $subaccount->codsubcuenta;
                    }
                    break;

                case 'tipo':
                    // comprobamos si el tipo existe
                    foreach (ProductType::all() as $k => $v) {
                        if (in_array($values[$prefix . $key], [$k, $v])) {
                            $model->{$key} = $k;
                            break;
                        }
                    }
                    break;

                case 'excepcioniva':
                    // comprobamos si la excepción existe
                    foreach (RegimenIVA::allExceptions() as $k => $v) {
                        if (in_array($values[$prefix . $key], [$k, $v])) {
                            $model->{$key} = $k;
                            break;
                        }
                    }
                    break;
            }
        }
        return true;
    }

    protected function setPrice(Producto $product, Variante &$variant, array $item): void
    {
        if (isset($item['variantes.precioconiva']) && $item['variantes.precioconiva']) {
            $pvp = (100 * CsvFileTools::formatFloat($item['variantes.precioconiva'])) / (100 + $product->getTax()->iva);
            $variant->precio = $pvp;
        }
    }
}
