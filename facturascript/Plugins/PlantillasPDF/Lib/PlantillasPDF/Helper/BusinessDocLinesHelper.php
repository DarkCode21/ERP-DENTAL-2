<?php
/**
 * Copyright (C) 2022-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PlantillasPDF\Lib\PlantillasPDF\Helper;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\ExtensionsTrait;
use FacturaScripts\Core\DataSrc\Divisas;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Model\Base\BusinessDocumentLine;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ProductType;
use FacturaScripts\Dinamic\Model\ProductoProveedor;

class BusinessDocLinesHelper
{
    use ExtensionsTrait;

    public static function get(BusinessDocument $model, BusinessDocumentLine $line, array $field): string
    {
        if (self::hide($line, $field)) {
            return '';
        }

        $pipe = new self();
        $return = $pipe->pipe('get', $model, $line, $field);
        if (null !== $return) {
            return $return;
        }

        switch ($field['key']) {
            case 'codbarras':
                $value = self::getBarcode($line);
                break;

            case 'descripcion':
                $value = $line->descripcion . self::getTrazabilidad($line);
                break;

            case 'image':
                $value = self::getImage($line);
                break;

            case 'numlinea':
                $value = $line->numlinea ?? 0;
                break;

            case 'precioiva':
                $value = self::getPriceTax($line);
                break;

            case 'pvpdto':
                // calculamos el precio con los dos descuentos
                $dto1 = 100 - $line->dtopor > 0 ? (100 - $line->dtopor) / 100 : 0;
                $dto2 = 100 - $line->dtopor2 > 0 ? (100 - $line->dtopor2) / 100 : 0;
                $value = $line->pvpunitario * $dto1 * $dto2;
                break;

            case 'refproveedor':
                $value = self::getRefProveedor($line);
                break;

            case 'totaliva':
                $value = self::getTotalTax($line);
                break;

            default:
                $value = $line->{$field['key']};
                break;
        }

        if (empty($value) && (!isset($line->cantidad) || empty($line->cantidad))) {
            return '&nbsp;';
        }

        return self::type($model, $value, $field['type']);
    }

    public static function hide(BusinessDocumentLine $line, array $field): bool
    {
        $pipe = new self();
        $return = $pipe->pipe('hide', $line, $field);
        if (null !== $return) {
            return $return;
        }

        switch ($field['key']) {
            case 'cantidad':
                return property_exists($line, 'mostrar_cantidad') && $line->mostrar_cantidad === false;

            case 'image':
            case 'codbarras':
            case 'refproveedor':
                return false;

            case 'dtopor':
            case 'dtopor2':
            case 'irpf':
            case 'iva':
            case 'precioiva':
            case 'pvpdto':
            case 'pvpunitario':
            case 'pvptotal':
            case 'recargo':
            case 'totaliva':
                return property_exists($line, 'mostrar_precio') && $line->mostrar_precio === false;
        }

        return !isset($line->{$field['key']});
    }

    public static function money(BusinessDocument $model, float $value, int $decimals = FS_NF0): string
    {
        $coddivisa = $model->coddivisa;
        if (empty($coddivisa)) {
            $coddivisa = Tools::settings('default', 'coddivisa', '');
        }

        $symbol = Divisas::get($coddivisa)->simbolo;
        $currencyPosition = Tools::settings('default', 'currency_position', 'right');
        $money = $currencyPosition === 'right' ?
            self::number($value, $decimals) . ' ' . $symbol :
            $symbol . ' ' . self::number($value, $decimals);

        return str_replace(' ', '&nbsp;', $money);
    }

    public static function number(float $value, int $decimals = FS_NF0): string
    {
        $number = Tools::number($value, $decimals);
        return str_replace(' ', '&nbsp;', $number);
    }

    public static function type(BusinessDocument $model, $value, string $type): string
    {
        switch ($type) {
            case 'money':
                return self::money($model, (float)$value);

            case 'money0':
                return self::money($model, (float)$value, 0);

            case 'money1':
                return self::money($model, (float)$value, 1);

            case 'money2':
                return self::money($model, (float)$value, 2);

            case 'money3':
                return self::money($model, (float)$value, 3);

            case 'money4':
                return self::money($model, (float)$value, 4);

            case 'money5':
                return self::money($model, (float)$value, 5);

            case 'number':
                return self::number((float)$value);

            case 'number0':
                return self::number((float)$value, 0);

            case 'number1':
                return self::number((float)$value, 1);

            case 'number2':
                return self::number((float)$value, 2);

            case 'number3':
                return self::number((float)$value, 3);

            case 'number4':
                return self::number((float)$value, 4);

            case 'number5':
                return self::number((float)$value, 5);

            case 'percentage':
                return Tools::number((float)$value) . '%';

            case 'percentage0':
                return Tools::number((float)$value, 0) . '%';

            case 'percentage1':
                return Tools::number((float)$value, 1) . '%';

            case 'percentage2':
                return Tools::number((float)$value, 2) . '%';

            case 'percentage3':
                return Tools::number((float)$value, 3) . '%';

            case 'percentage4':
                return Tools::number((float)$value, 4) . '%';

            case 'percentage5':
                return Tools::number((float)$value, 5) . '%';

            case 'text':
                return nl2br($value);

            default:
                return $value;
        }
    }

    protected static function getBarcode($line): string
    {
        return $line->referencia ? ($line->getVariante()->codbarras ?? '') : '';
    }

    protected static function getImage($line): string
    {
        // comprobamos que la línea tenga referencia de variante
        if (empty($line->referencia)) {
            return '';
        }

        // obtenemos las imágenes de la variante
        $images = $line->getVariante()->getImages();
        if (empty($images)) {
            return '';
        }

        // obtenemos las medidas de la imagen de la configuración del plugin
        $width = Tools::settings('plantillaspdf', 'productimagewidth', 50);
        $height = Tools::settings('plantillaspdf', 'productimageheight', 50);

        // devolvemos la primera imagen en html
        return '<img src="' . FS_FOLDER . $images[0]->getThumbnail($width, $height) . '" />';
    }

    protected static function getRefProveedor($line): ?string
    {
        $producto = new ProductoProveedor();
        $where = [
            new DataBaseWhere('referencia', $line->referencia),
            new DataBaseWhere('codproveedor', $line->getDocument()->codproveedor)
        ];
        return $producto->loadFromCode('', $where) && $producto->refproveedor ?
            $producto->refproveedor :
            $line->referencia;
    }

    protected static function getPriceTax(BusinessDocumentLine $line): float
    {
        $product = $line->getProducto();

        // si el producto no existe
        // o el producto no es de segunda mano
        // o la línea no tiene coste
        // calculamos el precio con el iva
        if (false === $product->exists()
            || $product->tipo !== ProductType::SECOND_HAND
            || false === property_exists($line, 'coste')) {
            return $line->pvpunitario + ($line->pvpunitario * $line->iva / 100);
        }

        // si el producto es de segunda mano, calculamos el precio con iva
        // en base, a la diferencia entre coste y precio
        $diff = $line->pvpunitario - $line->coste;
        return $line->pvpunitario + ($diff * $line->iva / 100);
    }

    protected static function getTotalTax(BusinessDocumentLine $line): float
    {
        $product = $line->getProducto();

        // si el producto no existe
        // o el producto no es de segunda mano
        // o la línea no tiene coste
        // calculamos el total con el iva
        if (false === $product->exists()
            || $product->tipo !== ProductType::SECOND_HAND
            || false === property_exists($line, 'coste')) {
            return $line->pvptotal + ($line->pvptotal * $line->iva / 100);
        }

        // si el producto es de segunda mano, calculamos el total con iva
        // en base, a la diferencia entre coste y precio
        $diff = $line->pvptotal - ($line->coste * $line->cantidad);
        return $line->pvptotal + ($diff * $line->iva / 100);
    }

    protected static function getTrazabilidad(BusinessDocumentLine $line): string
    {
        $classTrazabilidad = '\\FacturaScripts\\Dinamic\\Model\\ProductoLoteMovimiento';
        if (false === class_exists($classTrazabilidad)) {
            return '';
        }

        $movimientos = new $classTrazabilidad();
        $doc = $line->getDocument();
        $where = [
            new DataBaseWhere('docid', $doc->primaryColumnValue()),
            new DataBaseWhere('docmodel', $doc->modelClassName()),
            new DataBaseWhere('documento', $doc->codigo),
            new DataBaseWhere('idlinea', $line->idlinea),
            new DataBaseWhere('referencia', $line->referencia)
        ];

        $lotes = [];
        foreach ($movimientos->all($where) as $mov) {
            $lotes[] = $mov->numserie . ' (' . $mov->cantidad . ') ' . $mov->fecha;
        }
        if (empty($lotes)) {
            return '';
        }

        return '<div><br/>' . Tools::lang()->trans('batch-serial-numbers') . ': '
            . implode(",\n", $lotes) . '</div>';
    }
}
