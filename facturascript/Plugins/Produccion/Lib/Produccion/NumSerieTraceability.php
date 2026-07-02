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

use FacturaScripts\Core\Html;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\Produccion\Model\OrdenIngrediente;
use FacturaScripts\Plugins\Produccion\Model\OrdenNumSerie;
use FacturaScripts\Plugins\Produccion\Model\OrdenProduccion;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Builds the traceability tree for a given serial number (OrdenNumSerie).
 *
 * Tree structure:
 *   numserie    — the OrdenNumSerie being traced
 *   product     — scalar product/variant data
 *   origin      — where it was produced
 *     order       — OrdenProduccion that produced it
 *     ingredients — ingredient lines with their consumed serial number nodes (each a subtree)
 *   destination — where it went (mutually exclusive or null)
 *     type        — 'sale' | 'order' | null
 *     data        — AlbaranCliente | OrdenProduccion | null
 *     children    — future: subtree nodes produced/sold from destination
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class NumSerieTraceability
{
    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public static function exec($action, $data): array
    {
        return match ($action) {
            'get-traceability'   => self::getTraceHtml($data),
            'print-traceability' => self::getTracePdf($data),
            default              => ['ok' => false, 'error' => 'not implemented'],
        };
    }

    /**
     * Return the traceability data in HTML format for Ajax Response.
     *   - 'ok'   : (bool) Indicate if exists any error.
     *   - 'error': (string) The error message if exists any error.
     *   - 'html' : (string) The rendered HTML if not exists any error.
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    protected static function getTraceHtml(array $data): array
    {
        $traceData = self::getTraceData($data);
        if (empty($traceData)) {
            return [
                'ok'    => false,
                'error' => Tools::lang()->trans('record-not-found'),
            ];
        }

        return [
            'ok'   => true,
            'html' => Html::render('Block/NumSerieTraceabilityModal.html.twig', [
                'data' => $traceData,
            ]),
        ];
    }

    /**
     * Return the traceability data in PDF format for Ajax Response.
     *   - 'ok' : (bool) Indicate if exists any error.
     *   - 'pdf': (string) The pdf file in string format.
     *
     * @param array $data
     * @return array
     */
    protected static function getTracePdf(array $data): array
    {
        $traceData = self::getTraceData($data);
        if (empty($traceData)) {
            return ['ok' => false, 'pdf' => ''];
        }

        $pdf = new NumSerieTraceabilityPDF();
        $pdf->build($traceData);
        return ['ok' => true, 'pdf' => $pdf->getDoc()];
    }

    /**
     * Builds the ingredient lines for the given order.
     * Each line includes its consumed serial number nodes, each with their own origin and destination.
     */
    private static function buildIngredientNodes(OrdenProduccion $order): array
    {
        if (empty($order->id)) {
            return [];
        }

        // Index consumed NS by reference for fast lookup
        $consumedNs = [];
        foreach (OrdenNumSerie::all([Where::eq('idusedinorder', $order->id)]) as $ns) {
            $consumedNs[$ns->reference][] = $ns;
        }

        $nodes = [];
        $where = [Where::eq('idorden', $order->id)];
        foreach (OrdenIngrediente::all($where, ['referencia' => 'ASC']) as $ingredient) {
            $nsNodes = [];
            foreach ($consumedNs[$ingredient->referencia] ?? [] as $ns) {
                $variant = new Variante();
                $variant->loadWhereEq('referencia', $ns->reference);
                $nsNodes[] = [
                    'numserie'    => $ns,
                    'product'     => self::buildProductData($variant),
                    'origin'      => [
                        'order'       => $ns->getOrder(OrdenNumSerie::ORIGIN),
                        'ingredients' => [],
                    ],
                    'destination' => [
                        'type'     => 'order',
                        'data'     => $order,
                        'children' => [],
                    ],
                ];
            }

            $nodes[] = [
                'reference' => $ingredient->referencia,
                'name'      => $ingredient->getVariant()->description() ?? '',
                'quantity'  => $ingredient->cantidad,
                'numseries' => $nsNodes,
            ];
        }

        return $nodes;
    }

    /**
     * Extracts scalar product/variant data for display.
     */
    private static function buildProductData(Variante $variant): array
    {
        $product = $variant->getProducto();
        $images = $product->getImages();
        return [
            'barcode'      => $variant->codbarras,
            'cost'         => Tools::money($variant->coste),
            'family'       => $product->getFamilia()->descripcion ?? '',
            'imageUrl'     => empty($images) ? '' : $images[0]->url('download'),
            'manufacturer' => $product->getFabricante()->nombre ?? '',
            'name'         => $variant->description(),
            'price'        => Tools::money($variant->precio),
            'reference'    => $variant->referencia,
        ];
    }

    /**
     * Validates and builds the root trace tree node for the given serial number.
     *
     * @param array $data
     * @return array
     */
    private static function getTraceData(array $data): array
    {
        $numSerie = new OrdenNumSerie();
        $variant = new Variante();
        if (false === $numSerie->load($data['id'])
            || $numSerie->numserie !== $data['numserie']
            || false === $variant->loadWhereEq('referencia', $numSerie->reference)
        ) {
            return [];
        }

        $order = $numSerie->getOrder(OrdenNumSerie::ORIGIN);
        $destination = ['type' => null, 'data' => null, 'children' => []];
        if (false === empty($numSerie->iddelivery)) {
            $destination['type'] = 'sale';
            $destination['data'] = $numSerie->getDeliveryNote();
        } elseif (false === empty($numSerie->idusedinorder)) {
            $destination['type'] = 'order';
            $destination['data'] = $numSerie->getOrder(OrdenNumSerie::DESTINATION);
        }

        return [
            'numserie'    => $numSerie,
            'product'     => self::buildProductData($variant),
            'origin'      => [
                'order'       => $order,
                'ingredients' => self::buildIngredientNodes($order),
            ],
            'destination' => $destination,
        ];
    }
}
