<?php
/**
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TPVneo\Lib\TPVneo;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\DivisaTools;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Model\AttachedFile;
use FacturaScripts\Dinamic\Model\Divisa;
use FacturaScripts\Dinamic\Model\Familia;
use FacturaScripts\Dinamic\Model\Stock;
use FacturaScripts\Dinamic\Model\TpvTerminal;
use FacturaScripts\Dinamic\Model\Variante;

/**
 *
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ProductList
{

    use TPVTrait;

    /**
     * @var string
     */
    protected static $codalmacen = '';

    /**
     * @var string
     */
    protected static $codfamilia = '-1';

    /**
     * @var int
     */
    protected static $limit = 0;

    /**
     * @var string
     */
    protected static $orden = '';

    /**
     * @var string
     */
    protected static $query = '';

    public static function apply(array $formData)
    {
        self::$codalmacen = $formData['codalmacen'] ?? ToolBox::appSettings()::get('default', 'codalmacen');
        self::$codfamilia = $formData['codfamilia'];
        self::$limit = (int)$formData['productlimit'];
        self::$query = $formData['query'];
    }

    public static function render(TpvTerminal $tpv, string $codalmacen = ''): string
    {
        self::changeDivisa($tpv->coddivisa);
        if ($codalmacen !== '') {
            self::$codalmacen = $codalmacen;
        }
        self::$limit = $tpv->productlimit;
        return self::familyList() . self::productList();
    }

    protected static function changeDivisa(string $coddivisa)
    {
        $divisa = new Divisa();
        $divisa->loadFromCode($coddivisa);

        $divisaTools = new DivisaTools();
        $divisaTools->findDivisa($divisa);
    }

    protected static function tiendaCode(): string
    {
        return 'TIENDA';
    }

    protected static function getTiendaFamilyCodes(): array
    {
        $db = new DataBase();
        $codes = [];
        $visited = [];
        $queue = [self::tiendaCode()];

        while (!empty($queue)) {
            $parent = array_shift($queue);
            if (isset($visited[$parent])) {
                continue;
            }

            $visited[$parent] = true;
            $codes[$parent] = true;

            $sql = 'SELECT codfamilia FROM familias WHERE madre = ' . $db->var2str($parent);
            foreach ($db->select($sql) as $row) {
                $child = $row['codfamilia'] ?? '';
                if ($child !== '' && false === isset($visited[$child])) {
                    $queue[] = $child;
                }
            }
        }

        return array_keys($codes);
    }

	#MOD ERICK
    protected static function getProducts(): array
    {
        $dataBase = new DataBase();
        $sql = 'SELECT p.tpvsort, p.referencia, p.descripcion, v.precio, i.iva, COALESCE(s.disponible, 0) as disponible,
                p.nostock, p.observaciones, p.referencia as productref, v.idvariante, v.idproducto'
            . ' FROM variantes as v'
            . ' LEFT JOIN productos as p ON v.idproducto = p.idproducto'
            . ' LEFT JOIN impuestos as i ON p.codimpuesto = i.codimpuesto'
            . ' LEFT JOIN stocks as s ON v.referencia = s.referencia AND s.codalmacen = ' . $dataBase->var2str(self::$codalmacen)
            . ' WHERE p.sevende = true AND p.bloqueado = false';

        if (self::$query) {
            $sql .= " AND (LOWER(v.codbarras) = LOWER(" . $dataBase->var2str(self::$query) . ")"
                . " OR LOWER(v.referencia) LIKE LOWER(" . $dataBase->var2str('%' . self::$query . '%') . ")"
                . " OR LOWER(p.descripcion) LIKE LOWER(" . $dataBase->var2str('%' . self::$query . '%') . "))";
        }

        if (self::$codfamilia != '-1' && self::$codfamilia != '0') {
            $sql .= ' AND p.codfamilia = ' . $dataBase->var2str(self::$codfamilia);
        }

        $sql .= " ORDER BY p.tpvsort ASC";

        if (self::$limit > 0) {
            return $dataBase->selectLimit($sql, self::$limit);
        }

        $sql .= ';';
        return $dataBase->select($sql);
    }

    protected static function familyList(): string
    {
        $html = '';
        $familyModel = new Familia();

        if (self::$codfamilia == '-1') {
            $html .= '<div class="product-card">'
                . '<div class="card shadow-sm mb-3 cursor-pointer text-center d-flex flex-column" onclick="return showFamily(\'0\')">'
                . '<div class="text-info mt-3"><i class="far fa-folder fa-fw fa-4x"></i></div>'
                . '<div class="card-footer p-0 mt-auto">' . ToolBox::i18n()->trans('families') . '</div>'
                . '</div>'
                . '</div> ';
        }

        if (self::$query) {
            return $html;
        }

        if (self::$codfamilia != '-1') {
            $html .= '<div class="product-card">'
                . '<div class="card shadow-sm mb-3 cursor-pointer text-center d-flex flex-column" onclick="return showFamily(\'-1\')">'
                . '<div class="text-danger mt-3"><i class="fas fa-backspace fa-fw fa-4x"></i></div>'
                . '<div class="card-footer p-0 mt-auto">' . ToolBox::i18n()->trans('home') . '</div>'
                . '</div>'
                . '</div> ';
        }

        if (self::$codfamilia != '-1' && self::$codfamilia == '0') {
            $where = [
                new DataBaseWhere('madre', null, 'IS'),
                new DataBaseWhere('madre', '', '=', 'OR'),
            ];
            foreach ($familyModel->all($where, ['descripcion' => 'ASC'], 0, 0) as $family) {
                $html .= '<div class="product-card">'
                    . '<div class="card shadow-sm mb-3 cursor-pointer text-center d-flex flex-column" onclick="return showFamily(\'' . $family->codfamilia . '\')">'
                    . '<div class="text-info mt-3"><i class="far fa-folder fa-fw fa-4x"></i></div>'
                    . '<div class="card-footer p-0 mt-auto">' . $family->descripcion . '</div>'
                    . '</div>'
                    . '</div> ';
            }
        }

        if (self::$codfamilia != '-1' && self::$codfamilia != '0') {
            $familyModel->loadFromCode(self::$codfamilia);
            $parentFamily = ($familyModel->madre == '') ? 0 : $familyModel->madre;
            $html .= '<div class="product-card">'
                . '<div class="card shadow-sm mb-3 cursor-pointer text-center d-flex flex-column" onclick="return showFamily(\'' . $parentFamily . '\')">'
                . '<div class="text-danger mt-3"><i class="fas fa-backspace fa-fw fa-4x"></i></div>'
                . '<div class="card-footer p-0 text-center mt-auto">' . $familyModel->descripcion . '</div>'
                . '</div>'
                . '</div> ';

            $where = [new DataBaseWhere('madre', self::$codfamilia)];
            foreach ($familyModel->all($where, ['descripcion' => 'ASC'], 0, 0) as $family) {
                $html .= '<div class="product-card">'
                    . '<div class="card shadow-sm mb-3 cursor-pointer text-center d-flex flex-column" onclick="return showFamily(\'' . $family->codfamilia . '\')">'
                    . '<div class="text-info mt-3"><i class="far fa-folder fa-fw fa-4x"></i></div>'
                    . '<div class="card-footer p-0 mt-auto">' . $family->descripcion . '</div>'
                    . '</div>'
                    . '</div> ';
            }
        }

        return $html;
    }

    protected static function productInfoModal(array $product, string $nameModal): string
    {
        $variant = new Variante();
        if (false === $variant->loadFromCode($product['idvariante'])) {
            return '';
        }

        if (floatval($product['disponible']) > 0 || in_array($product['nostock'], ['1', 't'])) {
            $cssTr = 'table-success';
            $cssBtn = 'btn-success';
        } else {
            $cssTr = 'table-warning';
            $cssBtn = 'btn-warning';
        }

        $qtyPtrecibir = 0;
        if (in_array($product['nostock'], ['1', 't'])) {
            $qtyStock = '∞';
            $qtyPtrecibir = '∞';
        } elseif (floatval($product['disponible']) > 0) {
            $qtyStock = $product['disponible'];
        } else {
            $qtyStock = 0;
        }

        if ($qtyPtrecibir == 0 && in_array($product['nostock'], ['0', 'f'])) {
            $stock = new Stock();
            $whereStock = [new DataBaseWhere('referencia', $variant->referencia)];
            if ($stock->loadFromCode('', $whereStock)) {
                $qtyPtrecibir = $stock->pterecibir;
            }
        }

        $price = floatval($variant->precio) * (100 + floatval($product['iva'])) / 100;
        $html = '<div class="modal fade modalProductInfo" id="' . $nameModal . '" tabindex="-1" aria-labelledby="' . $nameModal . 'Label" aria-hidden="true">'
            . '<div class="modal-dialog modal-lg">'
            . '<div class="modal-content text-left">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title" id="' . $nameModal . 'Label">' . self::getImage($variant, 'photo-modal mr-2') . ToolBox::i18n()->trans('variant') . ' ' . $product['referencia'] . '</h5>'
            . '<button type="button" class="close" data-dismiss="modal" aria-label="' . ToolBox::i18n()->trans('close') . '">'
            . '<span aria-hidden="true">&times;</span>'
            . '</button>'
            . '</div>'
            . '<div class="modal-description px-3 pt-2">'
            . '<strong>' . ToolBox::i18n()->trans('description') . '</strong>'
            . '<p>' . $product['descripcion'] . '</p>'
            . '</div>';

        if ($product['observaciones']) {
            $nameCollapse = 'productCollapse' . $product['idvariante'];
            $html .= '<div class="modal-observations px-3">'
                . '<strong data-toggle="collapse" href="#' . $nameCollapse . '" role="button" aria-expanded="false" aria-controls="' . $nameCollapse . '">'
                . ToolBox::i18n()->trans('observations')
                . '<i class="fas fa-eye fa-xs ml-1"></i>'
                . '</strong>'
                . '<div class="collapse" id="' . $nameCollapse . '">'
                . '<p>' . $product['observaciones'] . '</p>'
                . '</div>'
                . '</div>';
        }

        $attributes = $variant->description(true);
        if ($attributes) {
            $html .= '<div class="modal-attributes px-3 pt-3">'
                . '<strong>' . ToolBox::i18n()->trans('attributes') . '</strong>'
                . '<p>' . $variant->description(true) . '</p>'
                . '</div>';
        }

        $html .= '<div class="table-responsive">'
            . '<table class="table mb-0">'
            . '<thead>'
            . '<tr>'
            . '<th>' . ToolBox::i18n()->trans('available') . '</th>'
            . '<th class="text-right">' . ToolBox::i18n()->trans('pending-reception') . '</th>'
            . '<th class="text-center">' . ToolBox::i18n()->trans('price') . '</th>'
            . '</tr>'
            . '</thead>'
            . '<tr class="' . $cssTr . '">'
            . '<td class="align-middle">' . $qtyStock . '</td>'
            . '<td class="text-right align-middle">' . $qtyPtrecibir . '</td>'
            . '<td class="align-middle text-nowrap"><button class="btn ' . $cssBtn . ' btn-block" onclick="return addProduct(\''
            . $variant->referencia . '\')"><i class="fas fa-shopping-cart mr-1"></i>' . ToolBox::coins()::format($price) . '</button></td>'
            . '</tr>'
            . '</table>'
            . '</div>';

        $variantModel = new Variante();
        $where = [
            new DataBaseWhere('idproducto', $product['idproducto']),
            new DataBaseWhere('referencia', $product['referencia'], '!=')
        ];
        $variants = $variantModel->all($where, ['referencia' => 'ASC'], 0, 0);

        if (empty($variants)) {
            $html .= '</div>'
                . '</div>'
                . '</div>';

            return $html;
        }

        $html .= '<strong class="text-center h5 mt-5">' . ToolBox::i18n()->trans('more') . ' ' . strtolower(ToolBox::i18n()->trans('variants')) . '</strong>'
            . '<div class="table-responsive border-top">'
            . '<table class="table mb-0">'
            . '<thead>'
            . '<tr>'
            . '<th>' . ToolBox::i18n()->trans('image') . '</th>'
            . '<th>' . ToolBox::i18n()->trans('variant') . '</th>'
            . '<th>' . ToolBox::i18n()->trans('attributes') . '</th>'
            . '<th class="text-right">' . ToolBox::i18n()->trans('available') . '</th>'
            . '<th class="text-right">' . ToolBox::i18n()->trans('pending-reception') . '</th>'
            . '<th class="text-center">' . ToolBox::i18n()->trans('price') . '</th>'
            . '</tr>'
            . '</thead>';

        foreach ($variants as $variant) {
            $qtyStock = 0;
            $qtyPtrecibir = 0;

            if (in_array($product['nostock'], ['1', 't'])) {
                $qtyStock = '∞';
                $qtyPtrecibir = '∞';
            } else {
                $stock = new Stock();
                $whereStock = [new DataBaseWhere('referencia', $variant->referencia)];
                if ($stock->loadFromCode('', $whereStock)) {
                    $qtyStock = $stock->cantidad;
                    $qtyPtrecibir = $stock->pterecibir;
                }
            }

            if (floatval($qtyStock) > 0 || in_array($product['nostock'], ['1', 't'])) {
                $cssTr = 'table-success';
                $cssBtn = 'btn-success';
            } else {
                $cssTr = 'table-warning';
                $cssBtn = 'btn-warning';
            }

            $price = floatval($variant->precio) * (100 + floatval($product['iva'])) / 100;
            $html .= '<tr class="' . $cssTr . '">'
                . '<td class="align-middle">' . self::getImage($variant, 'photo-modal') . '</td>'
                . '<td class="align-middle">' . $variant->referencia . '</td>'
                . '<td class="align-middle">' . $variant->description(true) . '</td>'
                . '<td class="text-right align-middle">' . $qtyStock . '</td>'
                . '<td class="text-right align-middle">' . $qtyPtrecibir . '</td>'
                . '<td class="align-middle text-nowrap"><button class="btn ' . $cssBtn . ' btn-block" onclick="return addProduct(\''
                . $variant->referencia . '\')"><i class="fas fa-shopping-cart mr-1"></i>' . ToolBox::coins()::format($price) . '</button></td>'
                . '</tr>';
        }

        $html .= '</table>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';

        return $html;
    }

    protected static function productList(): string
    {
        $html = '';

        foreach (self::getProducts() as $row) {
            $variant = new Variante();
            $variant->loadFromCode($row['idvariante']);

            $price = floatval($row['precio']) * (100 + floatval($row['iva'])) / 100;
            $descripcion = ToolBox::utils()->trueTextBreak($row['descripcion'], 100);

            if (floatval($row['disponible']) > 0 || in_array($row['nostock'], ['1', 't'])) {
                $cssBorder = 'border-success';
                $cssCoin = 'table-success';
            } else {
                $cssBorder = 'border-warning';
                $cssCoin = 'table-warning';
            }

            $nameModal = 'productModal' . $row['idvariante'];
            $html .= '<div class="product-card">'
                . '<div class="' . $cssBorder . ' card shadow-sm mb-3 text-center">'
                . '<div class="cursor-pointer add-product" onclick="return addProduct(\'' . $row['referencia'] . '\')">';

            $img = self::getImage($variant, 'photo-default');
            if (false === empty($img)) {
                $html .= '<div class="photo">' . $img . '</div>';
            }

            $html .= '<div class="h5 mt-2 text-primary pl-1 pr-1">' . $row['referencia'] . '</div>';

            if (empty($img)) {
                $html .= '<p class="small mb-0 pl-1 pr-1">' . $descripcion . '</p>';
            }

            $html .= '</div>'
                . '<div class="' . $cssCoin . ' mt-auto">'
                . '<div class="float-left pl-1 text-left">' . ToolBox::coins()::format($price) . '</div>'
                . '<a href="#" data-toggle="modal" data-target="#' . $nameModal . '" class="float-right pr-1 text-right">'
                . '+ ' . ToolBox::i18n()->trans('detail') . '</a>'
                . '</div>'
                . '</div>'
                . self::productInfoModal($row, $nameModal)
                . '</div> ';
        }

        return $html;
    }
}