<?php
/**
 * Copyright (C) 2019-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport;

use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Dinamic\Model\CSVfile;

/**
 * Composer autoload.
 */
require_once __DIR__ . '/vendor/autoload.php';

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
final class Init extends InitClass
{
    public function init(): void
    {
        $this->loadExtension(new Extension\Controller\EditProveedor());
        $this->loadExtension(new Extension\Controller\ListAlbaranCliente());
        $this->loadExtension(new Extension\Controller\ListAtributo());
        $this->loadExtension(new Extension\Controller\ListAttachedFile());
        $this->loadExtension(new Extension\Controller\ListCliente());
        $this->loadExtension(new Extension\Controller\ListContacto());
        $this->loadExtension(new Extension\Controller\ListFabricante());
        $this->loadExtension(new Extension\Controller\ListFacturaCliente());
        $this->loadExtension(new Extension\Controller\ListFacturaProveedor());
        $this->loadExtension(new Extension\Controller\ListFamilia());
        $this->loadExtension(new Extension\Controller\ListProducto());
        $this->loadExtension(new Extension\Controller\ListProveedor());
        $this->loadExtension(new Extension\Controller\ListAsiento());

        // plantillas automáticas Factusol
        $this->addAutoTemplate('customers', 'FactusolCustomers');
        $this->addAutoTemplate('customer-delivery-notes', 'FactusolCustomerDeliveryNotes');
        $this->addAutoTemplate('customer-invoices', 'FactusolCustomerInvoices');
        $this->addAutoTemplate('suppliers', 'FactusolSuppliers');
        $this->addAutoTemplate('supplier-invoices', 'FactusolSupplierInvoices');
        $this->addAutoTemplate('families', 'FactusolFamilies');
        $this->addAutoTemplate('products', 'FactusolProducts');

        // plantillas automáticas de Holded
        $this->addAutoTemplate('customer-invoices', 'HoldedCustomerInvoices');
        $this->addAutoTemplate('products', 'HoldedProducts');

        // plantillas automáticas Sage
        $this->addAutoTemplate('accounting-entries', 'SageAccountingEntries');
        $this->addAutoTemplate('accounting-entries', 'SageAccountingDiary');

        // plantillas automáticas Outlook
        $this->addAutoTemplate('contacts', 'OutlookContacts');

        // plantillas automáticas Google
        $this->addAutoTemplate('contacts', 'GoogleContacts');

        // plantillas automáticas FacturaScripts
        $this->addAutoTemplate('contacts', 'FSContacts');
        $this->addAutoTemplate('customers', 'FSCustomers');
        $this->addAutoTemplate('products', 'FSProducts');
        $this->addAutoTemplate('suppliers', 'FSSuppliers');
        $this->addAutoTemplate('variants', 'FSVariants');

        // plantillas automáticas FacturaScripts 2017
        $this->addAutoTemplate('families', 'FS17Families');
        $this->addAutoTemplate('manufacturers', 'FS17Manufacturers');
        $this->addAutoTemplate('products', 'FS17Products');
        $this->addAutoTemplate('customers', 'FS17Customers');
        $this->addAutoTemplate('suppliers', 'FS17Suppliers');

        // plantillas manuales
        $this->addManualTemplate('accounting-entries', 'AccountingEntries');
        $this->addManualTemplate('contacts', 'Contacts');
        $this->addManualTemplate('customer-delivery-notes', 'CustomerDeliveryNotes');
        $this->addManualTemplate('customer-invoices', 'CustomerInvoices');
        $this->addManualTemplate('families', 'Families');
        $this->addManualTemplate('manufacturers', 'Manufacturers');
        $this->addManualTemplate('products', 'Products');
        $this->addManualTemplate('supplier-invoices', 'SupplierInvoices');
        $this->addManualTemplate('supplier-products', 'SupplierProducts');
        $this->addManualTemplate('suppliers', 'Suppliers');
        $this->addManualTemplate('variants', 'Variants');
        $this->addManualTemplate('customers', 'Customers');
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
    }

    private function addAutoTemplate(string $profile, string $class): void
    {
        $fullClass = "\\FacturaScripts\\Dinamic\\Lib\\AutoTemplates\\" . $class;
        if (class_exists($fullClass)) {
            CSVfile::addAutoTemplate($profile, new $fullClass());
        }

        $localClass = "\\FacturaScripts\\Plugins\\CSVimport\\Lib\\AutoTemplates\\" . $class;
        if (class_exists($localClass)) {
            CSVfile::addAutoTemplate($profile, new $localClass());
        }
    }

    private function addManualTemplate(string $profile, string $class): void
    {
        $fullClass = "\\FacturaScripts\\Dinamic\\Lib\\ManualTemplates\\" . $class;
        if (class_exists($fullClass)) {
            CSVfile::addManualTemplate($profile, new $fullClass());
        }

        $localClass = "\\FacturaScripts\\Plugins\\CSVimport\\Lib\\ManualTemplates\\" . $class;
        if (class_exists($localClass)) {
            CSVfile::addManualTemplate($profile, new $localClass());
        }
    }
}
