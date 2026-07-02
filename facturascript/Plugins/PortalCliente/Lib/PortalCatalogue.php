<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Lib;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\MyFilesToken;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Almacen;
use FacturaScripts\Dinamic\Model\AttachedFileRelation;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\GrupoClientes;
use FacturaScripts\Dinamic\Model\PortalFavorite;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Stock;
use FacturaScripts\Dinamic\Model\Tarifa;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\Traducciones\Lib\TranslateModel;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalCatalogue
{
    const DEFAULT_NOT_IMAGE = 'Plugins/PortalCliente/Assets/Images/NotImage.jpg';
    const MAX_PRODUCTS = 30;

    public static function getGalleryImage(array $images, ?string $referencia): string
    {
        $cont = 0;
        $html = '';
        $gallery = 'gallery-' . Tools::randomString(10);

        // recorremos las imágenes
        foreach ($images as $image) {
            // si la referencia no coincide con la referencia de la imagen, continuamos
            if ($image->referencia !== $referencia) {
                continue;
            }

            // si no existe el thumbnail, continuamos
            $pathImageThumb = $image->getThumbnail(500, 500);
            if (empty($pathImageThumb)) {
                continue;
            }

            $pathImage = $image->getFile()->url('download');
            if (empty($pathImage)) {
                continue;
            }

            // si $pathImage no empieza por / se la añadimos
            if (substr($pathImage, 0, 1) !== '/') {
                $pathImage = '/' . $pathImage;
            }

            $display = $cont === 0 ? 'd-block modal-photo mr-2 float-left' : 'd-none';
            $html .= '<a class="' . $display . '" href="' . Tools::config('route') . $pathImage . '" data-fancybox="' . $gallery . '">'
                . '<img width="auto" height="50" src="' . Tools::config('route') . $pathImageThumb . '?myft=' . MyFilesToken::get($pathImageThumb, false) . '" loading="lazy">'
                . '</a>';

            $cont++;
        }

        return $html;
    }

    public static function render(Contacto $contact, int $currentPage, string $orderProductFilter, string $searchProductFilter = '', string $familiesProductFilter = '', string $priceMinProductFilter = '', string $priceMaxProductFilter = '', bool $favoriteProductFilter = false): array
    {
        $html = '';
        $rate = static::getRate($contact);
        $offset = $currentPage <= 1 ? 0 : ($currentPage - 1) * self::MAX_PRODUCTS;
        $currentProducts = static::getProducts($contact, $rate, $offset, self::MAX_PRODUCTS, $orderProductFilter, $searchProductFilter, $familiesProductFilter, $priceMinProductFilter, $priceMaxProductFilter, $favoriteProductFilter);
        $totalProducts = static::getProducts($contact, $rate, 0, 0, $orderProductFilter, $searchProductFilter, $familiesProductFilter, $priceMinProductFilter, $priceMaxProductFilter, $favoriteProductFilter);

        foreach ($currentProducts as $item) {
            $favorite = $item['favorite'];
            $product = $item['product'];
            $stocks = $item['stocks'];
            $variants = $item['variants'];
            $ratePriceMin = $item['ratePriceMin'];
            $ratePriceMax = $item['ratePriceMax'];

            // comprobamos el stock del producto
            $productStock = 0;
            foreach ($stocks as $referencia => $variantStocks) {
                foreach ($variantStocks as $stock) {
                    $productStock += $stock->disponible;
                }
            }

            // establecemos el css del card
            if ($productStock > 0 || $product->nostock) {
                $cssCard = 'border-success';
                $cssPrice = 'table-success';
            } else {
                $cssCard = 'border-warning';
                $cssPrice = 'table-warning';
            }

            // obtenemos las imágenes del producto incluídas las de las variantes
            $images = $product->getImages();
            if (empty($images)) {
                $pathImage = self::DEFAULT_NOT_IMAGE;
            } else {
                $pathImage = $images[0]->getThumbnail(500, 500);
                if (empty($pathImage)) {
                    $pathImage = self::DEFAULT_NOT_IMAGE;
                }
            }

            // obtenemos el precio mínimo y máximo
            $priceHtml = Tools::money($ratePriceMin, Tools::settings('default', 'coddivisa'));
            if (count($variants) > 1) {
                $priceHtml .= ' - ' . Tools::money($ratePriceMax, Tools::settings('default', 'coddivisa'));
            }

            // pintamos el html del card
            $nameModal = 'productModal' . $product->idproducto;
            $html .= '<div class="col-6 col-sm-4 col-md-3 col-xl-2 product mb-2 pointer" data-idproducto="' . $product->idproducto . '" onclick="showProductModal(\'' . $nameModal . '\')">'
                . '<div class="' . $cssCard . ' card shadow-sm text-center">'
                . '<div class="favorite w-100 p-1">'
                . '<i class="fa-regular fa-heart float-right pointer markAsFavorite ' . ($favorite ? 'd-none' : 'd-block') . '" title="' . Tools::lang()->trans('mark-as-favorite') . '" onclick="markAsFavorite(' . $product->idproducto . ')"></i>'
                . '<i class="fa-solid fa-heart text-danger float-right pointer unmarkAsFavorite ' . ($favorite ? 'd-block' : 'd-none') . '" title="' . Tools::lang()->trans('unmark-as-favorite') . '" onclick="unmarkAsFavorite(' . $product->idproducto . ')"></i>'
                . '</div>'
                . '<div class="photo">'
                . '<img loading="lazy" class="image card-img-top" src="' . Tools::config('route') . $pathImage . '?myft=' . MyFilesToken::get($pathImage, false) . '" alt="">'
                . '</div>'
                . '<div class="title px-2 pt-2 font-weight-bold">'
                . $product->referencia
                . '</div>'
                . '<div class="description px-2 mb-2 small">';

            if (Plugins::isEnabled('Traducciones')) {
                $html .= TranslateModel::get($contact->langcode, 'Producto', 'descripcion', $product->idproducto);
            } else {
                $html .= $product->descripcion;
            }

            $html .= '</div>'
                . '<div class="' . $cssPrice . ' price px-2 py-1 text-nowrap">'
                . $priceHtml
                . '</div>'
                . '</div>'
                . '</div>'
                . static::renderProductModal($contact, $nameModal, $product, $variants, $stocks, $images, $rate);
        }

        return [
            'productList' => $html,
            'productPaginate' => static::renderPaginate($currentPage, $currentProducts, $totalProducts),
        ];
    }

    public static function renderPaginate(int $currentPage, array $currentProducts, array $totalProducts): string
    {
        // si la cantidad total de productos es menor o igual que el máximo de productos
        // no mostramos la paginación
        if (count($totalProducts) <= self::MAX_PRODUCTS) {
            return '';
        }

        $html = '<nav><ul class="pagination justify-content-center mb-1">';
        $totalPages = ceil(count($totalProducts) / self::MAX_PRODUCTS);

        // si la página actual es mayor a 1, mostramos el botón de la página anterior
        $html .= $currentPage > 1 ? '<li class="page-item"><span class="page-link pointer" onclick="getCatalogue(' . ($currentPage - 1) . ')">
            <span aria-hidden="true">&laquo;</span>
          </span></li>' : '';

        // mostramos los botones de las páginas
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = $currentPage === $i ? 'active' : '';
            $html .= '<li class="page-item ' . $active . '"><span class="page-link pointer" onclick="getCatalogue(' . ($i) . ')">' . $i . '</span></li>';
        }

        // si la página actual es menor a la cantidad total de páginas, mostramos el botón de la página siguiente
        $html .= $currentPage < $totalPages ? '<li class="page-item pointer"><span class="page-link" onclick="getCatalogue(' . ($currentPage + 1) . ')">
            <span aria-hidden="true">&raquo;</span>
          </span></li>' : '';

        $html .= '</ul></nav><div class="text-center small">'
            . Tools::lang()->trans('paginate-product-list-desc', [
                '%totalProducts%' => count($totalProducts),
                '%initialProducts%' => (($currentPage - 1) * self::MAX_PRODUCTS + 1),
                '%finalProducts%' => ($currentPage - 1) * self::MAX_PRODUCTS + count($currentProducts),
            ])
            . '</div>';

        return $html;
    }

    protected static function getProducts(Contacto $contact, Tarifa $rate, int $offset, int $limit, string $orderProductFilter, string $searchProductFilter, string $familiesProductFilter, string $priceMinProductFilter, string $priceMaxProductFilter, bool $favoriteProductFilter): array
    {
        $productModel = new Producto();
        $where = [
            new DataBaseWhere('sevende', true),
            new DataBaseWhere('publico', true),
            new DataBaseWhere('bloqueado', false),
        ];

        if (false === empty($searchProductFilter) && false === Plugins::isEnabled('Traducciones')) {
            $where[] = new DataBaseWhere('referencia|descripcion|observaciones', $searchProductFilter, 'LIKE');
        }

        if (false === empty($familiesProductFilter)) {
            $where[] = new DataBaseWhere('codfamilia', $familiesProductFilter, 'IN');
        }

        $orderBy = match ($orderProductFilter) {
            'ref-desc' => ['referencia' => 'DESC'],
            'price-asc' => ['pc_price_min' => 'ASC'],
            'price-desc' => ['pc_price_max' => 'DESC'],
            default => ['referencia' => 'ASC'],
        };

        $warehousesCatalogue = [];
        if (false === empty(Tools::settings('portalcliente', 'catalogue_warehouse'))) {
            $warehousesCatalogue[] = Tools::settings('portalcliente', 'catalogue_warehouse');
        } else {
            $whereWarehouse = [new DataBaseWhere('idempresa', Tools::settings('portalcliente', 'catalogue_company'))];
            foreach (Almacen::all($whereWarehouse, ['codalmacen' => 'ASC'], 0, 0) as $warehouse) {
                $warehousesCatalogue[] = $warehouse->codalmacen;
            }
        }

        $result = [];
        foreach ($productModel->all($where, $orderBy, $offset, $limit) as $product) {
            // obtenemos todas las variantes del producto
            $variants = static::getVariants($product);

            // creamos el stock de las variantes
            $stocks = [];

            // comprobamos si la variante tiene stock en el almacén o almacenes del catálogo
            foreach ($variants as $variant) {
                $stockModel = new Stock();
                $where = [
                    new DataBaseWhere('referencia', $variant->referencia),
                    new DataBaseWhere('codalmacen', implode(',', $warehousesCatalogue), 'IN'),
                ];
                $stocks[$variant->referencia] = $stockModel->all($where, ['codalmacen' => 'ASC'], 0, 0);
            }

            // obtenemos el precio según base a la tarifa
            $ratePriceMin = $rate->applyTo($variants[0], $product);
            $ratePriceMax = count($variants) > 1
                ? $rate->applyTo($variants[count($variants) - 1], $product)
                : $ratePriceMin;

            // comprobamos el precio mínimo
            if (false === empty($priceMinProductFilter) && $ratePriceMin < (float)$priceMinProductFilter) {
                continue;
            }

            // comprobamos el precio máximo
            if (false === empty($priceMaxProductFilter) && $ratePriceMax > (float)$priceMaxProductFilter) {
                continue;
            }

            // comprobamos la búsqueda
            if (false === empty($searchProductFilter) && Plugins::isEnabled('Traducciones')) {
                $description = TranslateModel::get($contact->langcode, 'Producto', 'descripcion', $product->idproducto);
                $observations = TranslateModel::get($contact->langcode, 'Producto', 'observaciones', $product->idproducto);

                if (false === stripos($product->referencia, $searchProductFilter) && false === stripos($description, $searchProductFilter) && false === stripos($observations, $searchProductFilter)) {
                    continue;
                }
            }

            // comprobamos si es favorito o no
            $favorite = new PortalFavorite();
            $where = [
                new DataBaseWhere('idproducto', $product->idproducto),
                new DataBaseWhere('idcontacto', $contact->idcontacto),
            ];
            $favorite->loadFromCode('', $where);

            if ($favoriteProductFilter && false === $favorite->exists()) {
                continue;
            }

            $result[] = [
                'favorite' => $favorite->exists(),
                'product' => $product,
                'stocks' => $stocks,
                'variants' => $variants,
                'ratePriceMin' => $ratePriceMin,
                'ratePriceMax' => $ratePriceMax,
            ];
        }

        return $result;
    }

    protected static function getRate(Contacto $contact): Tarifa
    {
        $rate = new Tarifa();

        // si el contacto no es cliente, terminamos
        if (empty($contact->codcliente)) {
            return $rate;
        }

        // si el cliente tiene tarifa, la cargamos
        $customer = $contact->getCustomer(false);
        if ($customer->codtarifa && $rate->loadFromCode($customer->codtarifa)) {
            return $rate;
        }

        // si el cliente tiene grupo, cargamos la tarifa del grupo
        $group = new GrupoClientes();
        if ($customer->codgrupo && $group->loadFromCode($customer->codgrupo) && $group->codtarifa) {
            $rate->loadFromCode($group->codtarifa);
        }

        return $rate;
    }

    protected static function getVariants(Producto $product): array
    {
        $variantModel = new Variante();
        $where = [new DataBaseWhere('idproducto', $product->idproducto)];
        return $variantModel->all($where, ['precio' => 'ASC'], 0, 0);
    }

    protected static function renderProductModal(Contacto $contact, string $nameModal, Producto $product, array $variants, array $stocks, array $images, Tarifa $rate): string
    {
        $html = '<div class="modal fade modalProductInfo" id="' . $nameModal . '" tabindex="-1" aria-labelledby="' . $nameModal . 'Label" aria-hidden="true">'
            . '<div class="modal-dialog modal-xl">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title w-100 d-flex align-items-center" id="' . $nameModal . 'Label">'
            . static::getGalleryImage($images, null)
            . Tools::lang()->trans('product') . ' ' . $product->referencia
            . '</h5>'
            . '<button type="button" class="close" data-dismiss="modal" aria-label="' . Tools::lang()->trans('close') . '">'
            . '<span aria-hidden="true">&times;</span>'
            . '</button>'
            . '</div>'
            . '<div class="modal-description px-3 pt-2">'
            . '<strong>' . Tools::lang()->trans('description') . '</strong>'
            . '<p>';

        if (Plugins::isEnabled('Traducciones')) {
            $html .= TranslateModel::get($contact->langcode, 'Producto', 'descripcion', $product->idproducto);
        } else {
            $html .= $product->descripcion;
        }

        $html .= '</p>'
            . '</div>';

        if ($product->observaciones) {
            $nameCollapse = 'productCollapse' . $product->idproducto;
            $html .= '<div class="modal-observations px-3 mb-3">'
                . '<strong data-toggle="collapse" href="#' . $nameCollapse . '" role="button" aria-expanded="false" aria-controls="' . $nameCollapse . '">'
                . Tools::lang()->trans('observations')
                . '<i class="fas fa-eye fa-xs ml-1"></i>'
                . '</strong>'
                . '<div class="collapse" id="' . $nameCollapse . '">'
                . '<p>';

            if (Plugins::isEnabled('Traducciones')) {
                $html .= TranslateModel::get($contact->langcode, 'Producto', 'observaciones', $product->idproducto);
            } else {
                $html .= $product->observaciones;
            }

            $html .= '</p>'
                . '</div>'
                . '</div>';
        }

        // obtenemos los archivos públicos del producto
        $attachModel = new AttachedFileRelation();
        $where = [
            new DataBaseWhere('model', 'Producto'),
            new DataBaseWhere('modelid|modelcode', $product->idproducto),
            new DataBaseWhere('pc_show', true),
        ];
        $productFiles = $attachModel->all($where, ['creationdate' => 'DESC'], 0, 0);

        $html .= static::renderProductModalTab($contact, $nameModal, $product, $variants, $productFiles)
            . '<div class="tab-content" id="' . $nameModal . 'TabContent">'
            . static::renderProductModalTabContent($contact, $nameModal, $product, $variants, $stocks, $productFiles, $images, $rate)
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';

        return $html;
    }

    protected static function renderProductModalTab(Contacto $contact, string $nameModal, Producto $product, array $variants, array $productFiles): string
    {
        $html = '<ul class="nav nav-tabs" id="' . $nameModal . 'Tab" role="tablist">'
            . '<li class="nav-item" role="presentation">'
            . '<button class="nav-link active" id="' . $nameModal . 'VariantsTab" data-toggle="tab" data-target="#' . $nameModal . 'Variants" type="button" role="tab" aria-controls="' . $nameModal . 'Variants" aria-selected="true">'
            . Tools::lang()->trans('variants')
            . '<span class="badge badge-secondary ml-1">' . count($variants) . '</span>'
            . '</button>'
            . '</li>';

        if ($productFiles) {
            $html .= '<li class="nav-item" role="presentation">'
                . '<button class="nav-link" id="' . $nameModal . 'FilesTab" data-toggle="tab" data-target="#' . $nameModal . 'Files" type="button" role="tab" aria-controls="' . $nameModal . 'Files" aria-selected="false">'
                . Tools::lang()->trans('files')
                . '<span class="badge badge-secondary ml-1">' . count($productFiles) . '</span>'
                . '</button>'
                . '</li>';
        }

        $html .= '</ul>';
        return $html;
    }

    protected static function renderProductModalTabContent(Contacto $contact, string $nameModal, Producto $product, array $variants, array $stocks, array $productFiles, array $images, Tarifa $rate): string
    {
        $html = static::renderProductModalTabContentVariant($contact, $nameModal, $product, $variants, $stocks, $images, $rate);

        if ($productFiles) {
            $html .= static::renderProductModalTabContentFiles($contact, $nameModal, $productFiles);
        }

        return $html;
    }

    protected static function renderProductModalTabContentFiles(Contacto $contact, string $nameModal, array $productFiles): string
    {
        $html = '<div class="tab-pane fade p-3" id="' . $nameModal . 'Files" role="tabpanel" aria-labelledby="' . $nameModal . 'FilesTab">'
            . '<div class="form-row">'
            . '<div class="col-sm-12">'
            . '<div class="card-columns">';

        foreach ($productFiles as $productFile) {
            $file = $productFile->getFile();
            $html .= '<div class="card shadow mb-3">';

            if ($file->isImage()) {
                $html .= '<a href="' . $file->url('download') . '" target="_blank">'
                    . '<img class="card-img-top" src="' . $file->url('download') . '" alt="' . $file->filename . '">'
                    . '</a>';
            } else {
                $html .= '<div class="pl-3 pt-3 pr-3">'
                    . '<a href="' . $file->url('download') . '" target="_blank" class="btn btn-block btn-lg btn-secondary">';

                if ($file->isPdf()) {
                    $html .= '<i class="far fa-file-pdf fa-fw"></i>';
                } elseif ($file->isVideo()) {
                    $html .= '<i class="far fa-file-video fa-fw"></i>';
                } elseif ($file->isArchive()) {
                    $html .= '<i class="far fa-file-archive fa-fw"></i>';
                } else {
                    $html .= '<i class="far fa-file fa-fw"></i>';
                }

                $html .= $file->filename
                    . '</a>'
                    . '</div>';
            }

            $html .= '<div class="card-body p-3">'
                . '<div class="form-group">'
                . '<p>' . nl2br($productFile->observations) . '</p>'
                . '</div>'
                . '<div class="form-row card-text text-muted">'
                . '<div class="col">'
                . '<i class="fas fa-calendar-alt"></i> ' . Tools::dateTime($productFile->creationdate)
                . '</div>'
                . '<div class="col-auto">'
                . '<i class="fa-solid fa-weight-hanging"></i> ' . Tools::bytes($file->size)
                . '</div>'
                . '</div>'
                . '</div>'
                . '</div>';
        }

        $html .= '</div>'
            . '</div>'
            . '</div>'
            . '</div>';

        return $html;
    }

    protected static function renderProductModalTabContentVariant(Contacto $contact, string $nameModal, Producto $product, array $variants, array $stocks, array $images, Tarifa $rate): string
    {
        $html = '<div class="tab-pane fade show active" id="' . $nameModal . 'Variants" role="tabpanel" aria-labelledby="' . $nameModal . 'VariantsTab">'
            . '<div class="table-responsive border-top">'
            . '<table class="table mb-0">'
            . '<thead>'
            . '<tr>'
            . '<th>' . Tools::lang()->trans('image') . '</th>'
            . '<th>' . Tools::lang()->trans('variant') . '</th>'
            . '<th>' . Tools::lang()->trans('attributes') . '</th>'
            . '<th class="text-right">' . Tools::lang()->trans('available') . '</th>'
            . '<th class="text-right">' . Tools::lang()->trans('price') . '</th>';

        if ($contact->pc_allow_buy) {
            $html .= '<th style="width: 1%;"></th>';
        }

        $html .= '</tr>'
            . '</thead>';

        // mostramos las variantes
        foreach ($variants as $variant) {
            if ($product->nostock) {
                $qtyStock = '∞';
            } else {
                $qtyStock = 0;
                // obtenemos el stock disponible de la variante
                foreach ($stocks as $referencia => $variantStocks) {
                    foreach ($variantStocks as $stock) {
                        $qtyStock += $stock->disponible;
                    }
                }
                $qtyStock = Tools::number($qtyStock);
            }

            if ($product->nostock || $qtyStock > 0) {
                $cssTr = 'table-success';
            } else {
                $cssTr = 'table-warning';
            }

            // obtenemos los precios
            $ratePrice = $rate->applyTo($variant, $product);
            $variantPrice = $variant->precio;

            $html .= '<tr class="' . $cssTr . '">'
                . '<td class="align-middle">' . static::getGalleryImage($images, $variant->referencia) . '</td>'
                . '<td class="align-middle">' . $variant->referencia . '</td>'
                . '<td class="align-middle">';

            if (Plugins::isEnabled('Traducciones')) {
                $html .= $variant->descriptionTranslate($contact->langcode, true);
            } else {
                $html .= $variant->description(true);
            }

            $html .= '</td>'
                . '<td class="text-right align-middle">' . $qtyStock . '</td>'
                . '<td class="text-right align-middle text-nowrap">';

            if ($ratePrice == $variantPrice) {
                $html .= Tools::money($variantPrice, Tools::settings('default', 'coddivisa'));
            } else {
                $html .= '<div class="small"><s>' . Tools::money($variantPrice, Tools::settings('default', 'coddivisa')) . '</s></div> '
                    . Tools::money($ratePrice, Tools::settings('default', 'coddivisa'));
            }

            $html .= '</td>';

            if ($contact->pc_allow_buy && ($product->nostock || $qtyStock > 0)) {
                $html .= '<td class="align-middle">'
                    . '<button class="btn btn-success btn-spin-action text-nowrap" onclick="addProductToCart(' . $variant->idvariante . ',\'' . $nameModal . '\')">'
                    . '<i class="fa-solid fa-cart-plus mr-1"></i>' . Tools::lang()->trans('buy')
                    . '</button>'
                    . '</td>';
            } else {
                $html .= '<td></td>';
            }

            $html .= '</tr>';
        }

        $html .= '</table>'
            . '</div>'
            . '</div>';

        return $html;
    }
}