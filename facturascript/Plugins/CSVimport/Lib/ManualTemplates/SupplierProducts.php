<?php
/**
 * Copyright (C) 2020-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\ManualTemplates;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\ProductoProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\CSVimport\Contract\ManualTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class SupplierProducts extends ManualTemplateClass implements ManualTemplateInterface
{
    /** @var array */
    private $referencesFound = [];

    /** @var array */
    private $referencesSupplierFound = [];

    /** @var array */
    private $referencesNotFound = [];

    /** @var array */
    private $referencesSupplierNotFound = [];

    /** @var array */
    private $suppliersFound = [];

    /** @var array */
    private $suppliersNotFound = [];

    public function getDataFields(): array
    {
        return [
            'productosprov.codproveedor' => ['title' => 'supplier-code'],
            'productosprov.referencia' => ['title' => 'reference'],
            'productosprov.refproveedor' => ['title' => 'supplier-reference'],
            'productosprov.precio' => ['title' => 'price'],
            'productosprov.dtopor' => ['title' => 'purchase-discount'],
            'productosprov.stock' => ['title' => 'stock']
        ];
    }

    public function getFieldsToColumn(): array
    {
        return [
            'productosprov.codproveedor' => 'codproveedor'
        ];
    }

    public static function getProfile(): string
    {
        return 'supplier-products';
    }

    public function getRequiredFieldsAnd(): array
    {
        return ['productosprov.codproveedor'];
    }

    public function getRequiredFieldsOr(): array
    {
        return ['productosprov.referencia', 'productosprov.refproveedor'];
    }

    public function importItem(array $item): bool
    {
        // si no hemos pasado el código del proveedor,
        // lo cogemos del modelo CSVfile, este puede estar nulo
        if (empty($item['productosprov.codproveedor'])) {
            $item['productosprov.codproveedor'] = $this->model->codproveedor;
        }

        // comprobamos el proveedor
        if (false === $this->supplierExists($item['productosprov.codproveedor'])) {
            Tools::log()->error('supplier-not-found: ' . $item['productosprov.codproveedor']);
            return false;
        }

        $productSupplier = new ProductoProveedor();
        $where = [new DataBaseWhere('codproveedor', $item['productosprov.codproveedor'])];

        // comprobamos la variante del producto de proveedor
        if (false === empty($item['productosprov.refproveedor'])) {
            if ($this->variantSupplierExists($item['productosprov.codproveedor'], $item['productosprov.refproveedor'])) {
                $where[] = new DataBaseWhere('refproveedor', $item['productosprov.refproveedor']);
            } else {
                if ($this->model->mode !== CsvFileTools::UPDATE_MODE) {
                    Tools::log()->warning('variant-supplier-not-found: ' . $item['productosprov.refproveedor']);
                }

                return false;
            }
        } elseif (false === empty($item['productosprov.referencia'])) {
            // comprobamos la variante del producto
            if ($this->variantExists($item['productosprov.referencia'])) {
                $where[] = new DataBaseWhere('referencia', $item['productosprov.referencia']);
            } else {
                if ($this->model->mode !== CsvFileTools::UPDATE_MODE) {
                    Tools::log()->warning('variant-not-found: ' . $item['productosprov.referencia']);
                }

                return false;
            }
        } else {
            Tools::log()->error('variant-not-found');
            return false;
        }

        if ($productSupplier->loadFromCode('', $where) && $this->model->mode === CsvFileTools::INSERT_MODE ||
            false === $productSupplier->loadFromCode('', $where) && $this->model->mode === CsvFileTools::UPDATE_MODE) {
            return false;
        }

        if (false === $this->setModelValues($productSupplier, $item, 'productosprov.')) {
            return false;
        }

        return $productSupplier->save();
    }

    private function supplierExists(string $code): bool
    {
        if (in_array($code, $this->suppliersFound, true)) {
            return true;
        } elseif (in_array($code, $this->suppliersNotFound, true)) {
            return false;
        }

        $supplier = new Proveedor();
        if ($supplier->loadFromCode($code)) {
            $this->suppliersFound[] = $code;
            return true;
        }

        $this->suppliersNotFound[] = $code;
        return false;
    }

    private function variantExists(string $code): bool
    {
        if (in_array($code, $this->referencesFound, true)) {
            return true;
        } elseif (in_array($code, $this->referencesNotFound, true)) {
            return false;
        }

        $variant = new Variante();
        $where = [new DataBaseWhere('referencia', $code)];
        if ($variant->loadFromCode('', $where)) {
            $this->referencesFound[] = $code;
            return true;
        }

        $this->referencesNotFound[] = $code;
        return false;
    }

    private function variantSupplierExists(string $codproveedor, string $code): bool
    {
        $key = $codproveedor . '|' . $code;
        if (in_array($key, $this->referencesSupplierFound, true)) {
            return true;
        } elseif (in_array($key, $this->referencesSupplierNotFound, true)) {
            return false;
        }

        $productSupplier = new ProductoProveedor();
        $where = [
            new DataBaseWhere('codproveedor', $codproveedor),
            new DataBaseWhere('refproveedor', $code)
        ];
        if ($productSupplier->loadFromCode('', $where)) {
            $this->referencesSupplierFound[] = $key;
            return true;
        }

        $this->referencesSupplierNotFound[] = $key;
        return false;
    }
}
