<?php
/**
 * Copyright (C) 2020-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\AutoTemplates;

use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Impuestos;
use FacturaScripts\Core\DataSrc\Series;
use FacturaScripts\Core\Model\Base\ModelCore;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\LineaFacturaCliente;
use FacturaScripts\Plugins\CSVimport\Contract\AutoTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class FactusolCustomerInvoices implements AutoTemplateInterface
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
        if ($profile !== 'customer-invoices') {
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
                $csv['titles'][1] === 'Num.' &&
                $csv['titles'][2] === 'Fecha' &&
                $csv['titles'][3] === 'Cliente') {
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

        // recorremos las facturas empezando por el offset y terminando por el limit
        for ($i = $offset; $i < min($this->total_lines, $offset + static::LIMIT_IMPORT); $i++) {
            $row = $invoices[$i]['invoice'];

            // comprobamos la serie
            if (false === Series::get($row['S.'])->exists()) {
                Tools::log()->warning('serie-not-found', ['%serie%' => $row['S.']]);
                continue;
            }

            $this->continue = true;

            // obtenemos el ejercicio para la fecha de la factura
            $exercise = new Ejercicio();
            $exercise->idempresa = Tools::settings('default', 'idempresa');
            $exercise->loadFromDate(CsvFileTools::formatDate($row['Fecha']));
            if (empty($exercise->primaryColumnValue())) {
                Tools::log()->warning('exercise-not-found', ['%date%' => $row['Fecha']]);
                continue;
            }

            // buscamos si ya existe la factura
            $oldInvoice = new FacturaCliente();
            $where = [
                new DataBaseWhere('codserie', $row['S.']),
                new DataBaseWhere('numero2', $row['Num.']),
                new DataBaseWhere('codejercicio', $exercise->codejercicio)
            ];
            if ($oldInvoice->loadFromCode('', $where)) {
                // forzamos la comprobación de la fecha
                static::checkInvoiceDate($oldInvoice);
                continue;
            }

            // creamos la factura
            $newInvoice = new FacturaCliente();
            $newInvoice->setSubject(static::getCustomer($row));
            $newInvoice->idempresa = $exercise->idempresa;
            $newInvoice->codejercicio = $exercise->codejercicio;
            $newInvoice->fecha = CsvFileTools::formatDate($row['Fecha']);
            $newInvoice->hora = date('H:i:s');
            $newInvoice->codserie = Series::get($row['S.'])->codserie;
            $newInvoice->numero = (int)$row['Num.'];
            $newInvoice->numero2 = $row['Num.'];
            static::checkInvoiceDate($newInvoice);
            if (false === $newInvoice->save()) {
                break;
            }

            $saveLines++;

            // añadimos las líneas
            foreach ($invoices[$i]['lines'] as $line) {
                // las cabeceras son las mismas que las de la factura
                $newLine = $newInvoice->getNewProductLine($line['Num.']);
                $newLine->descripcion = $line['Fecha'] . ' ' . $line['Cliente'] . ' ' . $line['Alm.'];
                $newLine->cantidad = (float)$line['For. pag.'];
                $newLine->pvpunitario = CsvFileTools::formatFloat($line['Column8']);
                $newLine->dtopor = 100;
                $newLine->dtopor2 = 0;
                $newLine->save();
            }

            // añadimos la línea con los totales
            $newTotalLine = $newInvoice->getNewLine();
            $newTotalLine->descripcion = 'Total';
            static::setIVA($newTotalLine, $row['Base'], $row['IVA'], $row['Rec.']);
            $newTotalLine->save();

            // actualizamos los totales
            $lines = $newInvoice->getLines();
            Calculator::calculate($newInvoice, $lines, true);
            if (abs($newInvoice->total - CsvFileTools::formatFloat($row['Total'])) > 0.01) {
                Tools::log()->warning('total-value-error', [
                    '%docType%' => $newInvoice->modelClassName(),
                    '%docCode%' => $newInvoice->codigo,
                    '%docTotal%' => $newInvoice->total,
                    '%calcTotal%' => CsvFileTools::formatFloat($row['Total'])
                ]);
            }

            // ¿La factura está pagada?
            if (isset($row['Est.']) && $row['Est.'] === 'Cobra') {
                foreach ($newInvoice->getReceipts() as $receipt) {
                    $receipt->fechapago = $newInvoice->fecha;
                    $receipt->pagado = true;
                    $receipt->save();
                }
            }
        }

        $offset += static::LIMIT_IMPORT;

        return true;
    }

    protected static function checkInvoiceDate(FacturaCliente &$invoice): void
    {
        // ¿Indefinido?
        if (false === isset(static::$invoice_dates[$invoice->codejercicio][$invoice->codserie])) {
            static::$invoice_dates[$invoice->codejercicio][$invoice->codserie] = strtotime($invoice->fecha);
            return;
        }

        // ¿Fecha anterior?
        if (strtotime($invoice->fecha) < static::$invoice_dates[$invoice->codejercicio][$invoice->codserie]) {
            $newDate = date(ModelCore::DATE_STYLE, static::$invoice_dates[$invoice->codejercicio][$invoice->codserie]);
            Tools::log()->warning('invoice-date-changed', ['%old%' => $invoice->fecha, '%new%' => $newDate]);
            $invoice->fecha = $newDate;
            return;
        }

        // fecha posterior
        static::$invoice_dates[$invoice->codejercicio][$invoice->codserie] = strtotime($invoice->fecha);
    }

    protected static function getCustomer(array $line): Cliente
    {
        // separar el código del nombre
        $parts = explode('-', $line['Cliente']);
        $code = $parts[0];
        if (empty(intval($code))) {
            $code = 99999;
        }

        $customer = new Cliente();
        if (false === $customer->loadFromCode($code)) {
            // guardamos el cliente
            $customer->cifnif = '';
            $customer->codcliente = $code;
            $customer->nombre = empty($parts[1]) ? '-' : $parts[1];
            $customer->save();
        }

        return $customer;
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

    protected function readInvoices(array $data): array
    {
        // Inicializar el array para almacenar las facturas
        $invoices = [];
        $currentInvoice = [];

        foreach ($data as $row) {
            // Comprobación para detectar el inicio de una nueva factura
            if (!empty($row['S.']) && !empty($row['Num.'])) {
                // Si hay datos en ambas columnas, estamos en una nueva factura
                if (!empty($currentInvoice)) {
                    // Si ya tenemos una factura acumulada, la añadimos a la lista de invoices
                    $invoices[] = $currentInvoice;
                }
                $currentInvoice = ['date' => $row['Fecha'], 'invoice' => $row, 'lines' => []];
            } elseif (isset($row['Num.']) && $row['Num.'] == 'Código') {
                // Se alcanzaron las líneas de productos, se saltan las cabeceras
                continue;
            } elseif (!empty($currentInvoice) && empty($row['S.']) && !empty($row['Num.'])) {
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

    protected static function setIVA(LineaFacturaCliente &$line, string $net, string $totalIva, string $re): void
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
