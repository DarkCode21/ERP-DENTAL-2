<?php
/**
 * Copyright (C) 2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\AutoTemplates;

use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Impuestos;
use FacturaScripts\Core\DataSrc\Series;
use FacturaScripts\Core\Model\Base\ModelCore;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\LineaFacturaProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\CSVimport\Contract\AutoTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class FactusolSupplierInvoices implements AutoTemplateInterface
{
    const LIMIT_IMPORT = 10;

    /** @var bool */
    private $continue = false;

    /** @var array */
    protected static $invoice_dates = [];

    /** @var int */
    private $start = 0;

    /** @var int */
    private $total_lines = 0;

    public function continue(): bool
    {
        return $this->continue;
    }

    public function getTotalLines(): int
    {
        return $this->total_lines;
    }

    public function isValid(string $filePath, string $profile): bool
    {
        if ($profile !== 'supplier-invoices') {
            return false;
        }

        // probamos empezando desde las líneas 0 a la 6
        foreach (range(0, 6) as $start) {
            $this->start = $start;
            $csv = CsvFileTools::read($filePath, $start);

            if (count($csv['titles']) < 2) {
                continue;
            }

            if ($csv['titles'][0] === 'S.' &&
                $csv['titles'][2] === 'Factura recibida' &&
                $csv['titles'][3] === 'Fecha' &&
                $csv['titles'][4] === 'Proveedor') {
                $invoices = $this->readInvoices($csv['data']);
                $this->total_lines = count($invoices);
                return true;
            }
        }

        return false;
    }

    public function run(string $filePath, string $profile, string $mode, int &$offset, int &$saveLines): bool
    {
        $csv = CsvFileTools::read($filePath, $this->start);
        $invoices = $this->readInvoices($csv['data']);
        $this->total_lines = count($invoices);
        $this->continue = false;

        for ($i = $offset; $i < min($this->total_lines, $offset + static::LIMIT_IMPORT); $i++) {
            $row = $invoices[$i]['invoice'];

            // comprobamos la serie
            if (false === Series::get($row['S.'])->exists()) {
                Tools::log()->warning('serie-not-found', ['%serie%' => $row['S.']]);
                continue;
            }

            $this->continue = true;

            // buscamos la factura
            $invoice = new FacturaProveedor();
            $where = [
                new DataBaseWhere('codserie', $row['S.']),
                new DataBaseWhere('observaciones', $row['Num.'] ?? $row['Núm.']),
            ];
            if ($invoice->loadFromCode('', $where)) {
                // chequeamos la fecha
                static::checkInvoiceDate($invoice);
                continue;
            }

            // guardamos la factura
            $invoice->setSubject(static::getSupplier($row));
            $invoice->codserie = Series::get($row['S.'])->codserie;
            $invoice->setDate(CsvFileTools::formatDate($row['Fecha']), date('H:i:s'));
            $invoice->numero = intval($row['Num.'] ?? $row['Núm.']);
            $invoice->numproveedor = $row['Factura recibida'];
            $invoice->observaciones = $row['Num.'] ?? $row['Núm.'];
            static::checkInvoiceDate($invoice);
            if (false === $invoice->save()) {
                break;
            }

            $saveLines++;

            // añadimos las líneas
            foreach ($invoices[$i]['lines'] as $line) {
                // las cabeceras son las mismas que las de la factura
                $newLine = $invoice->getNewProductLine($line['Num.'] ?? $line['Núm.']);
                $newLine->descripcion = $line['Factura recibida'] . ' ' . $line['Fecha'] . ' ' . $line['Proveedor'];
                $newLine->cantidad = (float)$line['Estado'];
                $newLine->pvpunitario = CsvFileTools::formatFloat($line['Portes']);
                $newLine->dtopor = 100;
                $newLine->dtopor2 = 0;
                $newLine->save();
            }

            // añadimos la línea con los totales
            $newLine = $invoice->getNewLine();
            $newLine->descripcion = 'Total';
            static::setIVA($newLine, $row['Base'], $row['IVA'], $row['Rec']);
            $newLine->save();

            // actualizamos los totales
            $lines = $invoice->getLines();
            Calculator::calculate($invoice, $lines, true);
            if (abs($invoice->total - CsvFileTools::formatFloat($row['Total'])) > 0.01) {
                Tools::log()->warning('total-value-error', [
                    '%docType%' => $invoice->modelClassName(),
                    '%docCode%' => $invoice->codigo,
                    '%docTotal%' => $invoice->total,
                    '%calcTotal%' => CsvFileTools::formatFloat($row['Total'])
                ]);
            }

            // comprobamos si la factura está pagada
            if (isset($row['Estado']) && $row['Estado'] === 'Pagado') {
                foreach ($invoice->getReceipts() as $receipt) {
                    $receipt->fechapago = $invoice->fecha;
                    $receipt->pagado = true;
                    $receipt->save();
                }
            }
        }

        $offset += static::LIMIT_IMPORT;

        return true;
    }

    protected static function checkInvoiceDate(FacturaProveedor &$invoice): void
    {
        // undefined?
        if (false === isset(static::$invoice_dates[$invoice->codejercicio][$invoice->codserie])) {
            static::$invoice_dates[$invoice->codejercicio][$invoice->codserie] = strtotime($invoice->fecha);
            return;
        }

        // lower date?
        if (strtotime($invoice->fecha) < static::$invoice_dates[$invoice->codejercicio][$invoice->codserie]) {
            $newDate = date(ModelCore::DATE_STYLE, static::$invoice_dates[$invoice->codejercicio][$invoice->codserie]);
            Tools::log()->warning('invoice-date-changed', ['%old%' => $invoice->fecha, '%new%' => $newDate]);
            $invoice->fecha = $newDate;
            return;
        }

        // upper date
        static::$invoice_dates[$invoice->codejercicio][$invoice->codserie] = strtotime($invoice->fecha);
    }

    protected static function getIVA($totalIva, $net): float
    {
        $totalIva = (float)CsvFileTools::formatFloat($totalIva);
        $net = (float)CsvFileTools::formatFloat($net);

        if (empty($totalIva) || empty($net)) {
            return 0.0;
        }

        return $totalIva * 100 / $net;
    }

    protected static function getSupplier(array $line): Proveedor
    {
        // get code
        $parts = explode('-', $line['Proveedor']);
        $code = $parts[0];
        if (empty(intval($code))) {
            $code = 99999;
        }

        $supplier = new Proveedor();
        if (false === $supplier->loadFromCode($code)) {
            // save new customer
            $supplier->cifnif = '';
            $supplier->codproveedor = $code;
            $supplier->nombre = empty($parts[1]) ? '-' : $parts[1];
            $supplier->save();
        }

        return $supplier;
    }

    protected function readInvoices(array $data): array
    {
        // Inicializar el array para almacenar las facturas
        $invoices = [];
        $currentInvoice = [];

        foreach ($data as $row) {
            $num = $row['Num.'] ?? $row['Núm.'];

            // Comprobación para detectar el inicio de una nueva factura
            if (!empty($row['S.']) && !empty($num)) {
                // Si hay datos en ambas columnas, estamos en una nueva factura
                if (!empty($currentInvoice)) {
                    // Si ya tenemos una factura acumulada, la añadimos a la lista de invoices
                    $invoices[] = $currentInvoice;
                }
                $currentInvoice = ['date' => $row['Fecha'], 'invoice' => $row, 'lines' => []];
            } elseif ($num == 'Código') {
                // Se alcanzaron las líneas de productos, se saltan las cabeceras
                continue;
            } elseif (!empty($currentInvoice) && empty($row['S.']) && !empty($num)) {
                // Si la primera columna está vacía y la segunda tiene datos, es una línea de producto
                $currentInvoice['lines'][] = $row;
            }
        }

        // Añadir la última factura leída, si existe
        if (!empty($currentInvoice)) {
            $invoices[] = $currentInvoice;
        }

        // ordenamos por fecha, de menor a mayor
        usort($invoices, function ($a, $b) {
            $date_a = CsvFileTools::formatDate($a['date']);
            $date_b = CsvFileTools::formatDate($b['date']);
            return strtotime($date_a) <=> strtotime($date_b);
        });

        return $invoices;
    }

    protected static function setIVA(LineaFacturaProveedor &$line, string $net, string $totalIva, string $re): void
    {
        $line->codimpuesto = null;
        $line->iva = static::getIVA($totalIva, $net);
        $line->pvpunitario = CsvFileTools::formatFloat($net);
        $line->recargo = static::getIVA($re, $net);

        foreach (Impuestos::all() as $imp) {
            $subtotal = $line->pvpunitario * $imp->iva / 100;
            if (abs($subtotal - CsvFileTools::formatFloat($totalIva)) < 0.01) {
                $line->codimpuesto = $imp->codimpuesto;
                $line->iva = $imp->iva;
                $line->recargo = static::getIVA($re, $net);
                break;
            }
        }
    }
}
