<?php
/**
 * Copyright (C) 2022-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\LoteRebuild;
use FacturaScripts\Dinamic\Model\LineaTransferenciaStock;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\ProductoLoteMovimiento;
use FacturaScripts\Dinamic\Model\ProductoLote;
use FacturaScripts\Plugins\Trazabilidad\Model\LineaConteoStockTraza;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Init extends InitClass
{
    public function init(): void
    {
        $this->loadExtension(new Extension\Controller\EditAlbaranCliente());
        $this->loadExtension(new Extension\Controller\EditAlbaranProveedor());
        $this->loadExtension(new Extension\Controller\EditConteoStock());
        $this->loadExtension(new Extension\Controller\EditFacturaCliente());
        $this->loadExtension(new Extension\Controller\EditFacturaProveedor());
        $this->loadExtension(new Extension\Controller\EditProducto());
        $this->loadExtension(new Extension\Controller\EditTransferenciaStock());
        $this->loadExtension(new Extension\Controller\ListProducto());
        $this->loadExtension(new Extension\Lib\BusinessDocumentGenerator());
        $this->loadExtension(new Extension\Model\ConteoStock());

        // integración con el plugin Produccion (si está instalado)
        if (Plugins::isEnabled('Produccion')) {
            $this->loadExtension(new Extension\Controller\EditOrdenProduccion());
            $this->loadExtension(new Extension\Lib\Produccion\OrderManager());
        }
        $this->loadExtension(new Extension\Model\FacturaCliente());
        $this->loadExtension(new Extension\Model\FacturaProveedor());
        $this->loadExtension(new Extension\Model\LineaAlbaranCliente());
        $this->loadExtension(new Extension\Model\LineaAlbaranProveedor());
        $this->loadExtension(new Extension\Model\LineaConteoStock());
        $this->loadExtension(new Extension\Model\LineaFacturaCliente());
        $this->loadExtension(new Extension\Model\LineaFacturaProveedor());
        $this->loadExtension(new Extension\Model\LineaPresupuestoCliente());
        $this->loadExtension(new Extension\Model\LineaPedidoCliente());
        $this->loadExtension(new Extension\Model\LineaTransferenciaStock());
        $this->loadExtension(new Extension\Model\TransferenciaStock());
        $this->loadExtension(new Extension\Model\Producto());
        $this->loadExtension(new Extension\Model\Variante());

        new ProductoLote();
        new LineaConteoStockTraza();
        new ProductoLoteMovimiento();
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
        new ProductoLoteMovimiento();
        $this->updateTransferStock();
        $this->updateWarehouseLote();
    }

    /**
     * Todas las transferencias de stock que tengan enlace con un lote deben tener un movimiento para el lote
     * Se puede eliminar una vez todos los usuarios que usen el plugin estén por encima o igual a la versión 1.7
     */
    private function updateTransferStock(): void
    {
        $db = new DataBase();
        // si la tabla stocks_lineastransferencias no existe, paramos la ejecución
        if (false === $db->tableExists('stocks_lineastransferencias')) {
            return;
        }

        $sqlLines = 'SELECT * FROM stocks_lineastransferencias WHERE numserie IS NOT NULL ORDER BY idlinea ASC';
        foreach ($db->select($sqlLines) as $lineaTransferenciaStock) {
            // obtenemos la transferencia
            $sqlTransfer = 'SELECT * FROM stocks_transferencias WHERE idtrans = ' . $lineaTransferenciaStock['idtrans'];
            $transfer = $db->selectLimit($sqlTransfer, 1);
            if (empty($transfer)) {
                continue;
            }

            // obtenemos el lote de origen
            $sqlLoteOrig = 'SELECT * FROM productos_lotes'
                . ' WHERE numserie = ' . $db->var2str($lineaTransferenciaStock['numserie'])
                . ' AND referencia = ' . $db->var2str($lineaTransferenciaStock['referencia'])
                . ' AND codalmacen = ' . $db->var2str($transfer[0]['codalmacenorigen']);
            $loteOrig = $db->selectLimit($sqlLoteOrig, 1);
            if (empty($loteOrig)) {
                continue;
            }

            // obtenemos el lote de destino
            $sqlLoteDest = 'SELECT * FROM productos_lotes'
                . ' WHERE numserie = ' . $db->var2str($lineaTransferenciaStock['numserie'])
                . ' AND referencia = ' . $db->var2str($lineaTransferenciaStock['referencia'])
                . ' AND codalmacen = ' . $db->var2str($transfer[0]['codalmacendestino']);
            $loteDest = $db->selectLimit($sqlLoteDest, 1);
            if (empty($loteDest)) {
                continue;
            }

            // si la línea no tiene un movimiento creado para ese lote, lo creamos
            $sqlLoteMovimiento = 'SELECT * FROM productos_lotes_movs'
                . ' WHERE docid = ' . $transfer[0]['idtrans']
                . ' AND docmodel = ' . $db->var2str('TransferenciaStock')
                . ' AND idlinea = ' . $lineaTransferenciaStock['idlinea']
                . ' AND numserie = ' . $db->var2str($lineaTransferenciaStock['numserie']);
            $loteMovimiento = $db->selectLimit($sqlLoteMovimiento, 1);
            if (false === empty($loteMovimiento)) {
                continue;
            }

            // creamos el movimiento para el lote origen
            $sqlLoteMovOrig = 'INSERT INTO productos_lotes_movs'
                . ' (cantidad, docfecha, docid, docmodel, documento, fecha, idlinea, idlote, lastnick, lastupdate,'
                . ' creationdate, nick, numserie, referencia, total, devuelto, facturado) VALUES ('
                . $lineaTransferenciaStock['cantidad'] * -1 . ','
                . $db->var2str(Tools::date($transfer[0]['fecha'])) . ','
                . $transfer[0]['idtrans'] . ','
                . $db->var2str('TransferenciaStock') . ','
                . $db->var2str(Tools::textBreak($transfer[0]['observaciones'], 20)) . ','
                . $db->var2str(Tools::date($transfer[0]['fecha'])) . ','
                . $lineaTransferenciaStock['idlinea'] . ','
                . $loteOrig[0]['idlote'] . ','
                . $db->var2str($lineaTransferenciaStock['nick']) . ','
                . $db->var2str($lineaTransferenciaStock['fecha']) . ','
                . $db->var2str($lineaTransferenciaStock['fecha']) . ','
                . $db->var2str($lineaTransferenciaStock['nick']) . ','
                . $db->var2str($lineaTransferenciaStock['numserie']) . ','
                . $db->var2str($lineaTransferenciaStock['referencia']) . ','
                . $lineaTransferenciaStock['cantidad'] * -1 . ','
                . '0,'
                . '0)';
            $db->exec($sqlLoteMovOrig);


            // creamos el movimiento para el lote destino
            $sqlLoteMovDest = 'INSERT INTO productos_lotes_movs'
                . ' (cantidad, docfecha, docid, docmodel, documento, fecha, idlinea, idlote, lastnick, lastupdate,'
                . ' creationdate, nick, numserie, referencia, total, devuelto, facturado) VALUES ('
                . $lineaTransferenciaStock['cantidad'] . ','
                . $db->var2str(Tools::date($transfer[0]['fecha'])) . ','
                . $transfer[0]['idtrans'] . ','
                . $db->var2str('TransferenciaStock') . ','
                . $db->var2str(Tools::textBreak($transfer[0]['observaciones'], 20)) . ','
                . $db->var2str(Tools::date($transfer[0]['fecha'])) . ','
                . $lineaTransferenciaStock['idlinea'] . ','
                . $loteDest[0]['idlote'] . ','
                . $db->var2str($lineaTransferenciaStock['nick']) . ','
                . $db->var2str($lineaTransferenciaStock['fecha']) . ','
                . $db->var2str($lineaTransferenciaStock['fecha']) . ','
                . $db->var2str($lineaTransferenciaStock['nick']) . ','
                . $db->var2str($lineaTransferenciaStock['numserie']) . ','
                . $db->var2str($lineaTransferenciaStock['referencia']) . ','
                . $lineaTransferenciaStock['cantidad'] . ','
                . '0,'
                . '0)';
            $db->exec($sqlLoteMovDest);
        }
    }

    /**
     * Se puede eliminar una vez todos los usuarios que usen el plugin estén por encima o igual a la versión 1.61
     */
    private function updateWarehouseLote(): void
    {
        // obtenemos los lotes sin almacén
        $where = [new DataBaseWhere('codalmacen', null)];
        $lotes = ProductoLote::all($where, [], 0, 0);

        // si no hay lotes sin almacén, terminamos
        if (empty($lotes)) {
            return;
        }

        $idsProduct = [];

        // recorremos los lotes
        foreach ($lotes as $lote) {
            if (false === in_array($lote->idproducto, $idsProduct)) {
                $idsProduct[] = $lote->idproducto;
            }
        }

        // recorremos los productos
        foreach ($idsProduct as $idproduct) {
            $product = new Producto();
            if ($product->loadFromCode($idproduct)) {
                // reconstruimos los lotes y sus cantidades del producto
                LoteRebuild::run($product);
            }
        }
    }
}
