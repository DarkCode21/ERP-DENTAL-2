<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Facturae\Lib;

use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Almacenes;
use FacturaScripts\Core\DataSrc\FormasPago;
use FacturaScripts\Core\DataSrc\Impuestos;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;

class FacturaeImporter
{
    /** @var FacturaProveedor[] */
    protected static $last_invoices = [];

    public static function getLastInvoice(): ?FacturaProveedor
    {
        foreach (self::$last_invoices as $invoice) {
            return $invoice;
        }

        return null;
    }

    public static function import(string $file_path, string $codalmacen, string $codserie): bool
    {
        // leemos el xml de facturae
        $xml = simplexml_load_file($file_path);
        if ($xml === false) {
            Tools::log()->warning('empty-xml');
            return false;
        }

        // si no es una facturae, salimos
        if ($xml->getName() !== 'Facturae') {
            Tools::log()->warning('xml-not-facturae');
            return false;
        }

        // si el esquema (Facturae\FileHeader\SchemaVersion) es anterior al 3.0, salimos
        $schema_version = (string)$xml->FileHeader->SchemaVersion;
        if (version_compare($schema_version, '3.0', '<')) {
            Tools::log()->warning('xml-schema-version-old');
            return false;
        }

        // comprobamos si corresponde a este empresa
        $warehouse = Almacenes::get($codalmacen);
        if (false === $warehouse->exists()) {
            Tools::log()->error('warehouse-not-found');
            return false;
        }
        $company = $warehouse->getCompany();
        $buyer_cif = (string)$xml->Parties->BuyerParty->TaxIdentification->TaxIdentificationNumber;
        if ($buyer_cif !== $company->cifnif) {
            Tools::log()->error('company-not-match', ['%cif%' => $buyer_cif]);
            return false;
        }

        // comprobamos el proveedor (SellerParty)
        $supplier = self::getSupplier($xml->Parties->SellerParty);
        if ($supplier === null) {
            Tools::log()->warning('supplier-not-found');
            return false;
        }

        foreach ($xml->Invoices->Invoice as $xml_invoice) {
            // comprobamos si la factura ya existe
            $new_invoice = new FacturaProveedor();
            $xml_num = (string)$xml_invoice->InvoiceHeader->InvoiceNumber;
            $xml_serie = (string)$xml_invoice->InvoiceHeader->InvoiceSeriesCode;
            $where = [
                new DataBaseWhere('codproveedor', $supplier->codproveedor),
                new DataBaseWhere('idempresa', $company->idempresa),
                new DataBaseWhere('numproveedor', $xml_serie . '/' . $xml_num),
            ];
            if ($new_invoice->loadFromCode('', $where)) {
                Tools::log()->warning('invoice-already-exists', ['%invoice_code%' => $new_invoice->codigo]);
                return false;
            }

            // creamos la factura
            if (false === $new_invoice->setWarehouse($codalmacen)) {
                Tools::log()->error('invoice-warehouse-error');
                return false;
            }
            foreach (FormasPago::all() as $payment) {
                if ($payment->idempresa === $company->idempresa) {
                    $new_invoice->codpago = $payment->codpago;
                    break;
                }
            }
            if (!$new_invoice->setSubject($supplier)) {
                Tools::log()->error('invoice-subject-error');
                return false;
            }
            $new_invoice->codserie = $codserie;
            $date = (string)$xml_invoice->InvoiceIssueData->IssueDate;
            if (empty($date)) {
                Tools::log()->error('invoice-date-empty ' . print_r($xml_invoice, true));
                return false;
            } elseif (!$new_invoice->setDate($date, $new_invoice->hora)) {
                Tools::log()->error('invoice-date-error');
                return false;
            }
            $new_invoice->numproveedor = $xml_serie . '/' . $xml_num;
            if (!$new_invoice->save()) {
                Tools::log()->error('invoice-creation-error');
                return false;
            }

            // guardamos las líneas
            foreach ($xml_invoice->Items->InvoiceLine as $line) {
                $new_line = $new_invoice->getNewLine();
                $new_line->descripcion = (string)$line->ItemDescription;
                $new_line->cantidad = (float)$line->Quantity;
                $new_line->pvpunitario = (float)$line->UnitPriceWithoutTax;

                foreach ($line->TaxesOutputs->Tax as $tax) {
                    foreach (Impuestos::all() as $impuesto) {
                        if ($impuesto->iva === (float)$tax->TaxRate) {
                            $new_line->codimpuesto = $impuesto->codimpuesto;
                            $new_line->iva = $impuesto->iva;
                            $new_line->recargo = $impuesto->recargo;
                            break 2;
                        }
                    }
                }

                if (!$new_line->save()) {
                    Tools::log()->error('invoice-line-creation-error');
                    return false;
                }
            }

            // guardamos el total
            $new_lines = $new_invoice->getLines();
            if (!Calculator::calculate($new_invoice, $new_lines, true)) {
                Tools::log()->error('invoice-total-error');
                return false;
            }

            // añadimos la factura a la lista de últimas facturas
            array_unshift(self::$last_invoices, $new_invoice);
        }

        return true;
    }

    protected static function getSupplier($seller): ?Proveedor
    {
        // buscamos por cif o nombre
        $cif = (string)$seller->TaxIdentification->TaxIdentificationNumber;
        if (empty($cif)) {
            Tools::log()->error('supplier-cif-empty');
            return null;
        }
        $name = (string)$seller->LegalEntity->CorporateName;
        if (empty($name)) {
            Tools::log()->error('supplier-name-empty');
            return null;
        }

        $supplier = new Proveedor();
        $where = [
            new DataBaseWhere('cifnif', $cif),
            new DataBaseWhere('razonsocial', $name, '=', 'OR'),
        ];
        if ($supplier->loadFromCode('', $where)) {
            // lo hemos encontrado
            return $supplier;
        }

        // no encontrado, lo creamos
        $supplier->cifnif = $cif;
        $supplier->nombre = $name;
        if (false === $supplier->save()) {
            Tools::log()->error('supplier-save-error');
            return null;
        }

        // guardamos la dirección
        $address = $supplier->getDefaultAddress();
        $address->direccion = (string)$seller->LegalEntity->AddressInSpain->Address;
        $address->codpostal = (string)$seller->LegalEntity->AddressInSpain->PostCode;
        $address->ciudad = (string)$seller->LegalEntity->AddressInSpain->Town;
        $address->provincia = (string)$seller->LegalEntity->AddressInSpain->Province;
        $address->codpais = 'ESP';
        if (false === $address->save()) {
            Tools::log()->error('supplier-address-save-error');
            return null;
        }

        return $supplier;
    }
}
