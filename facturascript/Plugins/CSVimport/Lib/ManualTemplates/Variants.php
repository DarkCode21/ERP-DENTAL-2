<?php
/**
 * Copyright (C) 2020-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\ManualTemplates;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Atributo;
use FacturaScripts\Dinamic\Model\AtributoValor;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Tarifa;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\CSVimport\Contract\ManualTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class Variants extends Products implements ManualTemplateInterface
{
    public function getDataFields(): array
    {
        $fields = [
            'variantes.codbarras' => ['title' => 'barcode'],
            'variantes.coste' => ['title' => 'cost-price'],
            'variantes.idatributovalor1' => ['title' => 'attribute-value-1'],
            'variantes.idatributovalor2' => ['title' => 'attribute-value-2'],
            'variantes.idatributovalor3' => ['title' => 'attribute-value-3'],
            'variantes.idatributovalor4' => ['title' => 'attribute-value-4'],
            'variantes.idproducto' => ['title' => 'product'],
            'variantes.idvariante' => ['title' => 'variant'],
            'variantes.margen' => ['title' => 'margin'],
            'variantes.precio' => ['title' => 'price'],
            'variantes.precioconiva' => ['title' => 'price-with-tax'],
            'variantes.referencia' => ['title' => 'reference'],
            'stocks.cantidad' => ['title' => 'stock'],
            'stocks.codalmacen' => ['title' => 'warehouse'],
            'productos.descripcion' => ['title' => 'description'],
            'productos.observaciones' => ['title' => 'observations'],
            'productos.codimpuesto' => ['title' => 'tax-code'],
            'productos.pctimpuesto' => ['title' => 'pct-vat'],
            'productos.codfamilia' => ['title' => 'family'],
            'productos.codfabricante' => ['title' => 'manufacturer'],
            'productos.codsubcuentacom' => ['title' => 'subaccount-purchases'],
            'productos.codsubcuentaven' => ['title' => 'subaccount-sales'],
            'productosprov.codproveedor' => ['title' => 'supplier'],
            'productosprov.preciocompra' => ['title' => 'purchase-price'],
            'productosprov.dtopor' => ['title' => 'purchase-discount']
        ];

        // añadimos una columna por cada atributo
        $attributes = new Atributo();
        foreach ($attributes->all([], [], 0, 0) as $attribute) {
            $fields['atributos.' . $attribute->codatributo] = ['title' => $attribute->nombre];
        }

        if (Plugins::isEnabled('TarifasAvanzadas')) {
            $tarifaModel = new Tarifa();
            foreach ($tarifaModel->all([], [], 0, 0) as $tarifa) {
                $fields['articulostarifas.precio|' . $tarifa->codtarifa] = ['title' => $tarifa->nombre . ' - ' . Tools::lang()->trans('price')];
                $fields['articulostarifas.precioconiva|' . $tarifa->codtarifa] = ['title' => $tarifa->nombre . ' - ' . Tools::lang()->trans('price-with-tax')];
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
        return 'variants';
    }

    public function getRequiredFieldsAnd(): array
    {
        return [];
    }

    public function getRequiredFieldsOr(): array
    {
        return ['variantes.referencia', 'variantes.idproducto'];
    }

    public function importItem(array $item): bool
    {
        $where = [];
        if (isset($item['variantes.referencia']) && !empty($item['variantes.referencia'])) {
            $where[] = new DataBaseWhere('referencia', $item['variantes.referencia']);
        } elseif (isset($item['variantes.idproducto']) && !empty($item['variantes.idproducto'])) {
            $where[] = new DataBaseWhere('descripcion', $item['variantes.idproducto']);
        }
        if (empty($where)) {
            return false;
        }

        $variant = new Variante();
        if ($variant->loadFromCode('', $where) && $this->model->mode === CsvFileTools::INSERT_MODE
            || false === $variant->loadFromCode('', $where) && $this->model->mode === CsvFileTools::UPDATE_MODE) {
            return false;
        }

        if (false === $this->setModelValues($variant, $item, 'variantes.')) {
            return false;
        }

        // ¿Referencia vacía?
        if (empty($variant->referencia)) {
            $variant->referencia = $variant->newCode('referencia');
        }

        if (empty($variant->idproducto) && $this->model->mode === CsvFileTools::UPDATE_MODE) {
            // no hay producto, pero no podemos crear nada
            return false;
        }
        if (false === $this->setProduct($variant, $item)) {
            // no se pudo crear el producto
            return false;
        }

        $product = new Producto();
        $product->loadFromCode($variant->idproducto);
        $this->setPrice($product, $variant, $item);
        $this->setAttributes($variant, $item, 'atributos.');

        if ($variant->save()) {
            $this->importStock($product, $variant, $item);
            $this->importProveedor($variant, $item);
            $this->importTarifas($variant->referencia, $item);
            return true;
        }

        return false;
    }

    protected function setAttributes(&$model, $values, $prefix)
    {
        $attributes = new Atributo();
        foreach ($attributes->all([], [], 0, 0) as $attribute) {
            if (!isset($values[$prefix . $attribute->codatributo]) || empty($values[$prefix . $attribute->codatributo])) {
                continue;
            }

            $attrValue = new AtributoValor();
            $where = [
                new DataBaseWhere('valor', $values[$prefix . $attribute->codatributo]),
                new DataBaseWhere('codatributo', $attribute->codatributo)
            ];

            // si no encuentra el valor, lo crea
            if (false === $attrValue->loadFromCode('', $where)) {
                $attrValue->codatributo = $attribute->codatributo;
                $attrValue->valor = $values[$prefix . $attribute->codatributo];
                $attrValue->save();
            }

            // busca uno de los 4 atributos posible que este vacío para asignar el valor
            if (empty($model->idatributovalor1)) {
                $model->idatributovalor1 = $attrValue->id;
            } elseif (empty($model->idatributovalor2)) {
                $model->idatributovalor2 = $attrValue->id;
            } elseif (empty($model->idatributovalor3)) {
                $model->idatributovalor3 = $attrValue->id;
            } elseif (empty($model->idatributovalor4)) {
                $model->idatributovalor4 = $attrValue->id;
            }
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
                case 'idatributovalor1':
                case 'idatributovalor2':
                case 'idatributovalor3':
                case 'idatributovalor4':
                    // buscamos si existe el valor del atributo
                    $attrValue = new AtributoValor();
                    $where = [new DataBaseWhere('id', $values[$prefix . $key])];
                    if ($attrValue->loadFromCode('', $where)) {
                        $model->{$key} = $attrValue->id;
                        break;
                    }

                    // no encontró nada dejamos a null
                    $model->{$key} = null;
                    break;
            }
        }
        return true;
    }

    protected function setProduct(&$variant, $item): bool
    {
        $product = new Producto();

        // buscamos si existe el producto
        if ($product->loadFromCode($variant->idproducto)) {
            $variant->idproducto = $product->idproducto;
            return true;
        }

        // creamos el nuevo producto
        if (false === $this->setModelValues($product, $item, 'productos.')) {
            return false;
        }

        if (false === empty($variant->idproducto)) {
            $product->idproducto = $variant->idproducto;
        }

        // empty reference?
        if (empty($product->referencia)) {
            $product->referencia = $variant->referencia;
        }

        if (false === $this->findFamily($product->codfamilia)) {
            $product->codfamilia = null;
        }

        if (false === $this->findManufacturer($product->codfabricante)) {
            $product->codfabricante = null;
        }

        $this->setImpuesto($product, $item);
        if (false === $product->save()) {
            return false;
        }

        foreach ($product->getVariants() as $v) {
            $variant->idvariante = $v->idvariante;
        }

        $variant->idproducto = $product->idproducto;
        return true;
    }
}
