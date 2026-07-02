<?php

/**
 * This file is part of AltasRep plugin for FacturaScripts.
 * Copyright (C) 2025 FacturaScripts
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 */

namespace FacturaScripts\Plugins\AltasRep\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Model\Producto;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\AttachedFile;
use FacturaScripts\Dinamic\Model\AttachedFileRelation;
use FacturaScripts\Dinamic\Model\ProductoImagen;
use FacturaScripts\Dinamic\Model\ProductoProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller para dar de alta productos rápidamente desde dispositivos móviles
 *
 * @author FacturaScripts
 */
class AltasRep extends Controller
{
    /**
     * Runs the controller's private logic.
     *
     * @param Response              $response
     * @param \FacturaScripts\Dinamic\Model\User $user
     * @param \FacturaScripts\Core\Base\ControllerPermissions $permissions
     */
    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $action = $this->request->get('action', '');
        switch ($action) {
            case 'save-product':
                $this->saveProductAction();
                break;

            case 'check-reference':
                $this->checkReferenceAction();
                return;

            case 'search-proveedores':
                $this->searchProveedoresAction();
                return;
        }

        $this->setTemplate('AltasRep');
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'altas-rapidas';
        $pagedata['menu'] = 'warehouse';
        $pagedata['icon'] = 'fas fa-plus-circle';
        $pagedata['showonmenu'] = true;

        return $pagedata;
    }


    /**
     * Acción para guardar un nuevo producto
     */
    private function saveProductAction()
    {
        if (!$this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return;
        }

        if (false === $this->validateFormToken()) {
            return;
        }

        $descripcion = $this->request->get('descripcion', '');
        $referencia = $this->request->get('referencia', '');
        $codbarras = $this->request->get('codbarras', '');
        $precio = floatval($this->request->get('precio', 0));
        $ivaValor = intval($this->request->get('codimpuesto', 21));
        $codimpuesto = 'IVA' . $ivaValor;

        // Si el precio neto es 0 pero se introdujo precio con IVA, calculamos el neto
        if ($precio <= 0) {
            $precioConIva = floatval($this->request->get('precio_con_iva', 0));
            if ($precioConIva > 0) {
                $precio = $precioConIva / (1 + $ivaValor / 100);
            }
        }
        $precioCompra = floatval($this->request->get('precio_compra', 0));
        $cantidad = floatval($this->request->get('cantidad', 1));
        $ubicacion = $this->request->get('ubicacion', '');
        $codproveedor = $this->request->get('codproveedor', '');
        $refproveedor = $this->request->get('refproveedor', '');

        if (empty($descripcion)) {
            Tools::log()->warning('El nombre del producto es obligatorio');
            return;
        }

        // Crear nuevo producto
        $producto = new Producto();
        $producto->descripcion = $descripcion;
        $producto->referencia = $referencia ?: ($codbarras ?: $this->generateReference());
        $producto->precio = $precio;
        $producto->codimpuesto = $codimpuesto;
        $producto->stockfis = $cantidad;
        $producto->bloqueado = false;
        $producto->nostock = false;
        $producto->secompra = true;
        $producto->sevende = true;

        if ($producto->save()) {
            // Guardar precio de compra en la variante principal
            if ($precioCompra > 0) {
                $variantePrincipal = $producto->getVariants()[0] ?? null;
                if ($variantePrincipal) {
                    $variantePrincipal->coste = $precioCompra;
                    $variantePrincipal->save();
                }
            }

            // Crear variante principal con código de barras si se proporcionó
            if (!empty($codbarras)) {
                $variante = $producto->getVariants()[0] ?? null;
                if ($variante) {
                    $variante->codbarras = $codbarras;
                    $variante->precio = $precio;
                    if ($precioCompra > 0) {
                        $variante->coste = $precioCompra;
                    }
                    $variante->stockfis = $cantidad;
                    $variante->save();
                }
            }

            // Actualizar la ubicación en el stock si se proporcionó
            if (!empty($ubicacion)) {
                $this->updateStockLocation($producto->idproducto, $ubicacion);
            }

            // Guardar la imagen si se proporcionó
            $this->saveProductImage($producto);

            // Guardar relación con proveedor si se proporcionó
            if (!empty($codproveedor)) {
                $productoProveedor = new ProductoProveedor();
                $productoProveedor->referencia = $producto->referencia;
                $productoProveedor->codproveedor = $codproveedor;
                $productoProveedor->refproveedor = $refproveedor;
                $productoProveedor->save();
            }

            Tools::log()->notice('record-saved-correctly');
        } else {
            Tools::log()->error('record-save-error');
        }
    }

    /**
     * Actualiza la ubicación del producto en el stock del almacén principal
     */
    private function updateStockLocation(int $idproducto, string $ubicacion)
    {
        $stock = new \FacturaScripts\Dinamic\Model\Stock();
        $where = [
            new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('idproducto', $idproducto)
        ];

        foreach ($stock->all($where, [], 0, 0) as $stockItem) {
            $stockItem->ubicacion = $ubicacion;
            $stockItem->save();
        }
    }

    /**
     * Acción para buscar proveedores por nombre
     */
    private function searchProveedoresAction()
    {
        $this->setTemplate(false);
        header('Content-Type: application/json');

        $term = $this->request->get('term', '');
        if (strlen($term) < 2) {
            echo json_encode([]);
            return;
        }

        $proveedor = new Proveedor();
        $where = [
            new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('nombre', '%' . $term . '%', 'LIKE')
        ];
        $results = [];
        foreach ($proveedor->all($where, ['nombre' => 'ASC'], 0, 20) as $prov) {
            $results[] = [
                'value' => $prov->codproveedor,
                'label' => $prov->nombre . ' (' . $prov->codproveedor . ')',
                'nombre' => $prov->nombre,
            ];
        }

        echo json_encode($results);
    }

    /**
     * Acción para verificar si una referencia ya existe
     */
    private function checkReferenceAction()
    {
        $this->setTemplate(false);
        header('Content-Type: application/json');

        $referencia = $this->request->get('referencia', '');

        if (empty($referencia)) {
            echo json_encode(['exists' => false, 'product' => null]);
            return;
        }

        $producto = new Producto();
        $exists = $producto->loadFromCode('', [['referencia', '=', $referencia]]);

        $response = [
            'exists' => $exists,
            'product' => $exists ? [
                'referencia' => $producto->referencia,
                'descripcion' => $producto->descripcion,
                'pvp' => $producto->pvp
            ] : null
        ];

        echo json_encode($response);
    }

    /**
     * Genera una referencia automática para el producto
     */
    private function generateReference(): string
    {
        $producto = new Producto();
        $lastId = $producto->getLastId();
        return 'PROD-' . str_pad($lastId + 1, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Guarda la imagen del producto
     */
    private function saveProductImage(Producto $producto)
    {
        $uploadFile = $this->request->files->get('fotografia');
        if (empty($uploadFile) || !$uploadFile->isValid()) {
            return;
        }

        if (strpos($uploadFile->getMimeType(), 'image/') === false) {
            Tools::log()->warning('file-not-supported');
            return;
        }

        try {
            // Mover archivo a MyFiles (igual que en el Core)
            $uploadFile->move(FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles', $uploadFile->getClientOriginalName());
            
            // Crear registro AttachedFile
            $newFile = new AttachedFile();
            $newFile->path = $uploadFile->getClientOriginalName();
            if (!$newFile->save()) {
                Tools::log()->error('record-save-error');
                return;
            }

            // Crear registro ProductoImagen
            $productImage = new ProductoImagen();
            $productImage->idproducto = $producto->idproducto;
            $productImage->idfile = $newFile->idfile;
            $productImage->referencia = $producto->referencia;
            if (!$productImage->save()) {
                Tools::log()->error('record-save-error');
                return;
            }

            // Crear relación de archivo (igual que en el Core)
            $fileRelation = new AttachedFileRelation();
            $fileRelation->idfile = $newFile->idfile;
            $fileRelation->model = 'Producto';
            $fileRelation->modelid = $producto->idproducto;
            $fileRelation->nick = $this->user->nick;
            $fileRelation->save();

        } catch (\Exception $exc) {
            Tools::log()->error($exc->getMessage());
        }
    }
}
