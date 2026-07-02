<?php
/**
 * Copyright (C) 2020-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Etiquetas\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\AlbaranProveedor;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class PrintEtiquetas extends Controller
{
    /**
     * @var AlbaranProveedor|FacturaProveedor|AlbaranCliente|Producto
     */
    protected $model;

    public function generateTags(): array
    {
        $tags = [];
        $this->getModel();

        // add empty tags
        $initRow = (int)$this->request->request->get('initrow');
        $initCol = (int)$this->request->request->get('initcol');
        if ($initRow > 1 || $initCol > 1) {
            $total = 4 * ($initRow - 1) + $initCol;
            for ($num = 1; $num < $total; $num++) {
                $tags[] = [
                    'barcode' => '',
                    'barcodeimg' => '',
                    'description' => '',
                    'price' => '',
                    'reference' => ''
                ];
            }
        }

        $dataPost = [
            'withbarcode' => (bool)$this->request->request->get('withbarcode', '0'),
            'printbarcodes' => (bool)$this->request->request->get('printbarcodes', '0'),
            'printprices' => (bool)$this->request->request->get('printprices', '0'),
            'printdesc' => (bool)$this->request->request->get('printdesc', '0')
        ];

        if (empty($this->model)) {
            $this->loadCustom($tags, $dataPost);
        } elseif ($this->model->modelClassName() === 'Producto') {
            $this->loadProduct($tags, $dataPost);
        } elseif (in_array($this->model->modelClassName(), ['AlbaranProveedor', 'FacturaProveedor', 'AlbaranCliente'])) {
            $this->loadDoc($tags, $dataPost);
        }

        return $tags;
    }

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'warehouse';
        $pageData['title'] = 'tags';
        $pageData['icon'] = 'fas fa-barcode';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    public function getReturn(): string
    {
        return $this->request->request->get('return', '');
    }

    protected function addTag(array &$tags, Variante $variant, array $dataPost, int $max): void
    {
        if ($dataPost['withbarcode'] && empty($variant->codbarras)) {
            return;
        }

        for ($num = 1; $num <= $max; $num++) {
            $tags[] = [
                'barcode' => $dataPost['printbarcodes'] ? $variant->codbarras : '',
                'barcodeimg' => $variant->getBarcodeImg(),
                'description' => $dataPost['printdesc'] ? $this->getDescriptionString($variant->getProducto()->descripcion, 30) : '',
                'price' => $dataPost['printprices'] ? $this->getPriceString($variant) : 0,
                'reference' => $variant->referencia
            ];
        }
    }

    protected function getDescriptionString(string $text, int $len): string
    {
        if (mb_strlen($text) <= $len) {
            return $text;
        }

        return mb_substr($text, 0, $len);
    }

    /**
     * @return null|AlbaranProveedor|FacturaProveedor|AlbaranCliente|Producto
     */
    protected function getModel()
    {
        if (!isset($this->model)) {
            $model = $this->request->request->get('model', '');
            if (empty($model)) {
                return null;
            }

            $modelName = '\\FacturaScripts\\Dinamic\\Model\\' . $model;
            $this->model = new $modelName();
            $this->model->loadFromCode($this->request->request->get('code'));
        }

        return $this->model;
    }

    protected function getPriceString(Variante $variant): string
    {
        return $this->toolBox()->coins()->format($variant->priceWithTax());
    }

    protected function loadCustom(array &$tags, array $dataPost): void
    {
        $references = $this->request->request->get('reference', []);
        $quantities = $this->request->request->get('quantity', []);
        foreach ($references as $index => $reference) {
            $variant = new Variante();
            $where = [new DataBaseWhere('referencia', $reference)];
            if (empty($reference) || !$variant->loadFromCode('', $where)) {
                continue;
            }

            $max = intval($quantities[$index]) ?? 1;
            $this->addTag($tags, $variant, $dataPost, $max);
        }
    }

    protected function loadDoc(array &$tags, array $dataPost): void
    {
        foreach ($this->getModel()->getLines() as $key => $line) {
            $variant = new Variante();
            $where = [new DataBaseWhere('referencia', $line->referencia)];
            if (empty($line->referencia) || !$variant->loadFromCode('', $where)) {
                continue;
            }

            $max = (int)$this->request->request->get('quantity' . $key, $line->cantidad);
            $this->addTag($tags, $variant, $dataPost, $max);
        }
    }

    protected function loadProduct(array &$tags, array $dataPost): void
    {
        foreach ($this->getModel()->getVariants() as $key => $variant) {
            if ($dataPost['withbarcode'] && empty($variant->codbarras)) {
                continue;
            }

            $max = (int)$this->request->request->get('quantity' . $key, 1);
            $this->addTag($tags, $variant, $dataPost, $max);
        }
    }
}
