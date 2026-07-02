<?php
/**
 * Copyright (C) 2024-2025 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente;

use FacturaScripts\Core\Base\AjaxForms\SalesHeaderHTML;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Cache;
use FacturaScripts\Core\Kernel;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\WorkQueue;
use FacturaScripts\Dinamic\Model\Base\BusinessDocument;
use FacturaScripts\Plugins\PortalCliente\Lib\Pay\PortalPaymentGateway;
use FacturaScripts\Plugins\PortalCliente\Lib\Pay\PortalPaymentGatewayBank;
use FacturaScripts\Plugins\PortalCliente\Lib\Pay\PortalPaymentGatewayPaypal;
use FacturaScripts\Plugins\PortalCliente\Lib\Pay\PortalPaymentGatewayRedsys;
use FacturaScripts\Plugins\PortalCliente\Lib\Pay\PortalPaymentGatewayStripe;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Init extends InitClass
{
    public function init(): void
    {
        // añadimos los campos que no se deben copiar al hacer una rectificativa
        BusinessDocument::dontCopyField('pc_created');
        BusinessDocument::dontCopyField('pc_payment_intent_stripe');
        BusinessDocument::dontCopyField('pc_payment_paypal');
        BusinessDocument::dontCopyField('pc_payment_redsys');
        BusinessDocument::dontCopyField('pc_paid');
        BusinessDocument::dontCopyField('pc_uuid');

        // cargamos las extensiones
        $this->loadExtension(new Extension\Controller\EditAlbaranCliente());
        $this->loadExtension(new Extension\Controller\EditCliente());
        $this->loadExtension(new Extension\Controller\EditContacto());
        $this->loadExtension(new Extension\Controller\EditEmpresa());
        $this->loadExtension(new Extension\Controller\EditFacturaCliente());
        $this->loadExtension(new Extension\Controller\EditPedidoCliente());
        $this->loadExtension(new Extension\Controller\EditPresupuestoCliente());
        $this->loadExtension(new Extension\Controller\EditProducto());
        $this->loadExtension(new Extension\Controller\EditSettings());
        $this->loadExtension(new Extension\Controller\ListAlbaranCliente());
        $this->loadExtension(new Extension\Controller\ListFacturaCliente());
        $this->loadExtension(new Extension\Controller\ListPedidoCliente());
        $this->loadExtension(new Extension\Controller\ListPresupuestoCliente());
        $this->loadExtension(new Extension\Model\AlbaranCliente());
        $this->loadExtension(new Extension\Model\Cliente());
        $this->loadExtension(new Extension\Model\Contacto());
        $this->loadExtension(new Extension\Model\FacturaCliente());
        $this->loadExtension(new Extension\Model\PedidoCliente());
        $this->loadExtension(new Extension\Model\PresupuestoCliente());
        $this->loadExtension(new Extension\Model\Producto());
        $this->loadExtension(new Extension\Model\Variante());

        // cargamos las rutas
        $this->loadRoutes();

        // cargamos los mods
        SalesHeaderHTML::addMod(new Mod\SalesHeaderHTMLMod());

        // cargamos las pasarelas de pago
        PortalPaymentGateway::register(new PortalPaymentGatewayRedsys());
        PortalPaymentGateway::register(new PortalPaymentGatewayPaypal());
        PortalPaymentGateway::register(new PortalPaymentGatewayStripe());
        PortalPaymentGateway::register(new PortalPaymentGatewayBank());

        // cargamos los workers
        WorkQueue::addWorker('PortalNewTicketWorker', 'Model.PortalTicket.Insert');
        WorkQueue::addWorker('PortalNewCommentTicketWorker', 'Model.PortalTicketComment.Insert');
        WorkQueue::addWorker('PortalDocNoticeWorker', 'PortalDocNotice');
        WorkQueue::addWorker('PortalDocPaymentCustomerWorker', 'PortalDocPaymentCustomer');
        WorkQueue::addWorker('PortalDocPaymentAdminWorker', 'PortalDocPaymentAdmin');
        WorkQueue::addWorker('PortalCreateLoginContactWorker', 'PortalCreateLoginContact');
        WorkQueue::addWorker('PortalPresupuestoClienteUuidWorker', 'PortalPresupuestoClienteUuid');
        WorkQueue::addWorker('PortalPedidoClienteUuidWorker', 'PortalPedidoClienteUuid');
        WorkQueue::addWorker('PortalAlbaranClienteUuidWorker', 'PortalAlbaranClienteUuid');
        WorkQueue::addWorker('PortalFacturaClienteUuidWorker', 'PortalFacturaClienteUuid');
        WorkQueue::addWorker('PortalFacturaClientePaidWorker', 'Model.FacturaCliente.Paid');
        WorkQueue::addWorker('PortalProductPriceWorker', 'PortalProductPrice');
        WorkQueue::addWorker('PortalNoteWorker', 'Model.PortalNote.Save');
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
        $this->loadRoutes();
        $this->updateSettings();
        $this->updateWorkers();
        $this->updateDontCopyInvoice();

        Plugins::deploy(true, true);
        Cache::clear();
    }

    private function loadRoutes(): void
    {
        Kernel::addRoutes(function () {
            Kernel::addRoute('/PortalPresupuesto/*', 'PortalPresupuesto');
            Kernel::addRoute('/PortalPedido/*', 'PortalPedido');
            Kernel::addRoute('/PortalAlbaran/*', 'PortalAlbaran');
            Kernel::addRoute('/PortalFactura/*', 'PortalFactura');
        });
    }

    private function updateSettings(): void
    {
        Tools::settings('portalcliente', 'catalogue_company', Tools::settings('default', 'idempresa'));
        Tools::settings('portalcliente', 'shop_warehouse', Tools::settings('default', 'codalmacen'));
        Tools::settings('portalcliente', 'shop_document', 'PedidoCliente');
        Tools::settingsSave();
    }

    private function updateDontCopyInvoice(): void
    {
        $db = new DataBase();

        // buscamos las facturas rectificativas donde su factura padre tenga el mismo pc_uuid
        $sql = 'SELECT f2.idfactura, f2.pc_uuid as pc_uuid_rect, f1.pc_uuid'
            . ' FROM facturascli f1'
            . ' JOIN facturascli f2 ON f2.idfacturarect = f1.idfactura'
            . ' WHERE f1.pc_uuid = f2.pc_uuid';

        // recorremos los resultados
        foreach ($db->select($sql) as $row) {
            // actualizamos los campos de la factura rectificativa
            $sql = 'UPDATE facturascli'
                . ' SET pc_created = 0,'
                . ' pc_payment_intent_stripe = null,'
                . ' pc_payment_paypal = null,'
                . ' pc_payment_redsys = null,'
                . ' pc_paid = 0,'
                . ' pc_uuid = ' . $db->var2str(uniqid())
                . ' WHERE idfactura = ' . $row['idfactura'];

            $db->exec($sql);
        }
    }

    private function updateWorkers(): void
    {
        // añadimos a los contactos existentes el login
        WorkQueue::send('PortalCreateLoginContact', '');

        // añadimos a los presupuestos existentes el uuid
        WorkQueue::send('PortalPresupuestoClienteUuid', '');

        // añadimos a los pedidos existentes el uuid
        WorkQueue::send('PortalPedidoClienteUuid', '');

        // añadimos a los albaranes existentes el uuid
        WorkQueue::send('PortalAlbaranClienteUuid', '');

        // añadimos a las facturas existentes el uuid
        WorkQueue::send('PortalFacturaClienteUuid', '');

        // guardamos el precio máximo y mínimo de cada producto
        WorkQueue::send('PortalProductPrice', '');
    }
}