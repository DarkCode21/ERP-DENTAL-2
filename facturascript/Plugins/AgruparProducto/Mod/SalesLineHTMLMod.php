<?php
/**
 * This file is part of AgruparProducto plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * AgruparProducto Copyright (C) 2022-2022 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\AgruparProducto\Mod;

use FacturaScripts\Core\Base\Contract\SalesLineModInterface;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\Translator;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Model\Base\SalesDocumentLine;
use FacturaScripts\Dinamic\Model\CodeModel;
use FacturaScripts\Dinamic\Model\ProductGroupingLine;
use FacturaScripts\Dinamic\Model\Variante;

/**
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class SalesLineHTMLMod implements SalesLineModInterface
{

    public function apply(SalesDocument &$model, array &$lines, array $formData)
    {
    }

    public function applyToLine(array $formData, SalesDocumentLine &$line, string $id)
    {
        $this->setGroupings($line);
    }

    public function assets(): void
    {
    }

    public function getFastLine(SalesDocument $model, array $formData): ?SalesDocumentLine
    {
        // search variant from product grouping barcode.
        $whereBarcode = [new DataBaseWhere('barcode', $formData['fastli'])];
        $grouping = new ProductGroupingLine();
        if (false === $grouping->loadFromCode('', $whereBarcode)) {
            return null;
        }

        // search variant from product grouping id.
        $whereProduct = [new DataBaseWhere('idproducto', $grouping->idproduct)];
        $variant = new Variante();
        $variant->loadFromCode('', $whereProduct);

        // create new line, assign values from grouping and return it
        $newLine = $model->getNewProductLine($variant->referencia);
        $newLine->cantidad = $grouping->quantity;
        $newLine->dtopor = $grouping->discount;
        return $newLine;
    }

    public function map(array $lines, SalesDocument $model): array
    {
        return [];
    }

    public function newModalFields(): array
    {
        return ['groupings'];
    }

    public function newFields(): array
    {
        return [];
    }

    public function newTitles(): array
    {
        return [];
    }

    public function renderField(Translator $i18n, string $idlinea, SalesDocumentLine $line, SalesDocument $model, string $field): ?string
    {
        if ($field === 'groupings') {
            $this->setGroupings($line);
            return '<div class="col-6">'
            . '<div class="mb-2">' . $i18n->trans('product-grouping')
            . '<input type="text" name="groupings_' . $idlinea . '" value="' . $line->groupings . '" class="form-control" disabled />'
            . '</div>'
            . '</div>';
        }
        return null;
    }

    public function renderTitle(Translator $i18n, SalesDocument $model, string $field): ?string
    {
        return null;
    }

    private function setGroupings(&$line) {
        if (isset($line->groupings)) {
            return;
        }

        $quantities = [];
        $where = [new DataBaseWhere('idproduct', $line->idproducto)];
        foreach (CodeModel::all(ProductGroupingLine::tableName(),'quantity','quantity', false, $where) as $row) {
            $quantities[] = $row->code;
        }
        $line->groupings = \implode(' / ', $quantities);
    }
}