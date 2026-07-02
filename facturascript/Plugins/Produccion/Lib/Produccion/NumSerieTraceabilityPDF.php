<?php
/**
 * This file is part of the Produccion plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 * All Rights Reserved.
 */
namespace FacturaScripts\Plugins\Produccion\Lib\Produccion;

use FacturaScripts\Core\Lib\Export\PDFExport;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\FormatoDocumento;

/**
 * Generates a PDF report for serial number traceability data.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class NumSerieTraceabilityPDF extends PDFExport
{
    /**
     * Builds the PDF document from a traceability data array.
     *
     * @param array $traceData
     */
    public function build(array $traceData): void
    {
        $this->newDoc($traceData['numserie']->numserie, 0, '');
        if ($this->format === null) {
            $this->format = new FormatoDocumento();
        }
        $this->newPage();
        $this->insertHeader();

        $this->pdf->ezText(
            "\n" . Tools::lang()->trans('numserie') . ': ' . $traceData['numserie']->numserie . "\n",
            self::FONT_SIZE + 4
        );
        $this->newLine();
        $this->insertProductSection($traceData['product'], $traceData['numserie']);
        $this->insertOriginSection($traceData['origin']['order']);
        $this->insertIngredientsSection($traceData['origin']['ingredients']);
        $this->insertDestinationSection($traceData['destination']);
        $this->insertFooter();
    }

    /**
     * @param int $status
     * @return string
     */
    private function getOrderStatus(int $status): string
    {
        return match ($status) {
            0       => Tools::lang()->trans('pending'),
            10      => Tools::lang()->trans('started'),
            15      => Tools::lang()->trans('verifying'),
            20      => Tools::lang()->trans('finished'),
            default => Tools::lang()->trans('cancelled'),
        };
    }

    /**
     * @param array $destination
     */
    private function insertDestinationSection(array $destination): void
    {
        $this->pdf->ezText("\n");
        $this->pdf->ezText(Tools::lang()->trans('destination') . "\n", self::FONT_SIZE + 1);
        $tableOptions = ['cols' => [], 'lineCol' => [1, 1, 1], 'shaded' => 0, 'showHeadings' => 0, 'width' => $this->tableWidth];

        if ($destination['type'] === 'sale') {
            $delivery = $destination['data'];
            $data = [
                ['key' => Tools::lang()->trans('type'),     'value' => Tools::lang()->trans('customer-delivery-note')],
                ['key' => Tools::lang()->trans('number'),   'value' => $delivery->codigo ?? ''],
                ['key' => Tools::lang()->trans('customer'), 'value' => $delivery->nombrecliente ?? ''],
                ['key' => Tools::lang()->trans('date'),     'value' => $delivery->fecha ?? ''],
            ];
        } elseif ($destination['type'] === 'order') {
            $order = $destination['data'];
            $data = [
                ['key' => Tools::lang()->trans('type'),   'value' => Tools::lang()->trans('production-order')],
                ['key' => Tools::lang()->trans('number'), 'value' => (string)$order->id],
                ['key' => Tools::lang()->trans('date'),   'value' => $order->fecha ?? ''],
            ];
        } else {
            $data = [
                ['key' => Tools::lang()->trans('status'), 'value' => Tools::lang()->trans('in-stock')],
            ];
        }

        $this->insertParallelTable($data, '', $tableOptions);
        $this->pdf->ezText("\n");
    }

    /**
     * @param array $ingredients
     */
    private function insertIngredientsSection(array $ingredients): void
    {
        if (empty($ingredients)) {
            return;
        }

        $this->pdf->ezText(Tools::lang()->trans('ingredients') . "\n", self::FONT_SIZE + 3);

        $headers = [
            'reference' => Tools::lang()->trans('reference'),
            'name'      => Tools::lang()->trans('description'),
            'quantity'  => Tools::lang()->trans('quantity'),
            'numseries' => Tools::lang()->trans('lote-serial-number'),
        ];
        $rows = [];
        foreach ($ingredients as $ingredient) {
            $nsValues = array_map(fn($n) => $n['numserie']->numserie, $ingredient['numseries']);
            $rows[] = [
                'reference' => $ingredient['reference'],
                'name'      => $ingredient['name'],
                'quantity'  => (string)$ingredient['quantity'],
                'numseries' => implode(', ', $nsValues),
            ];
        }

        $this->pdf->ezTable($rows, $headers, '', [
            'cols'            => ['quantity' => ['justification' => 'right']],
            'shadeCol'        => [0.95, 0.95, 0.95],
            'shadeHeadingCol' => [0.95, 0.95, 0.95],
            'width'           => $this->tableWidth,
        ]);
    }

    /**
     * @param $order
     */
    private function insertOriginSection($order): void
    {
        $this->pdf->ezText(Tools::lang()->trans('origin') . "\n", self::FONT_SIZE + 3);

        $tableOptions = ['cols' => [], 'lineCol' => [1, 1, 1], 'shaded' => 0, 'showHeadings' => 0, 'width' => $this->tableWidth];
        $orderData = [
            ['key' => Tools::lang()->trans('number'), 'value' => (string)$order->id],
            ['key' => Tools::lang()->trans('date'),   'value' => $order->fecha . ' ' . $order->hora],
            ['key' => Tools::lang()->trans('user'),   'value' => $order->nick ?? ''],
            ['key' => Tools::lang()->trans('status'), 'value' => $this->getOrderStatus((int)$order->estado)],
        ];
        if (!empty($order->fechafabricacion)) {
            $orderData[] = ['key' => Tools::lang()->trans('manufactured-date'), 'value' => $order->fechafabricacion];
        }
        $this->insertParallelTable($orderData, '', $tableOptions);
        $this->pdf->ezText("\n");
    }

    /**
     * @param array $product
     * @param $numserie
     */
    private function insertProductSection(array $product, $numserie): void
    {
        $this->pdf->ezText(Tools::lang()->trans('product') . "\n", self::FONT_SIZE + 3);

        $tableOptions = ['cols' => [], 'lineCol' => [1, 1, 1], 'shaded' => 0, 'showHeadings' => 0, 'width' => $this->tableWidth];
        $verified = $numserie->verified
            ? Tools::lang()->trans('verified')
            : Tools::lang()->trans('pending');

        $productData = [
            ['key' => Tools::lang()->trans('reference'),    'value' => $product['reference']],
            ['key' => Tools::lang()->trans('description'),  'value' => $product['name']],
            ['key' => Tools::lang()->trans('manufacturer'), 'value' => $product['manufacturer']],
            ['key' => Tools::lang()->trans('family'),       'value' => $product['family']],
            ['key' => Tools::lang()->trans('barcode'),      'value' => $product['barcode'] ?? ''],
            ['key' => Tools::lang()->trans('cost-price'),   'value' => $product['cost']],
            ['key' => Tools::lang()->trans('price'),        'value' => $product['price']],
            ['key' => Tools::lang()->trans('status'),       'value' => $verified],
        ];
        $this->insertParallelTable($productData, '', $tableOptions);
        $this->pdf->ezText("\n");
    }
}
