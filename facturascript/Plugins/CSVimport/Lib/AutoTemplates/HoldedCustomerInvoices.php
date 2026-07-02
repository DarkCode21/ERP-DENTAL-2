<?php
/**
 * Copyright (C) 2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\AutoTemplates;

use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\BusinessDocumentCode;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\CSVimport\Contract\AutoTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

class HoldedCustomerInvoices implements AutoTemplateInterface
{
    const LIMIT_IMPORT = 300;

    /** @var bool */
    private $continue = false;

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
            $csv = CsvFileTools::read($filePath, $start, 0, 1);
            $this->total_lines = CsvFileTools::getTotalLines();

            if (count($csv['titles']) < 3) {
                continue;
            }

            if ($csv['titles'][0] === 'Fecha' && $csv['titles'][2] === 'Vencimiento') {
                return true;
            }
        }

        return false;
    }

    public function run(string $filePath, string $profile, string $mode, int &$offset, int &$saveLines): bool
    {
        $csv = CsvFileTools::read($filePath, $this->start, $offset, static::LIMIT_IMPORT);
        $this->total_lines = CsvFileTools::getTotalLines();
        $this->continue = false;

        $pending = [];
        foreach ($csv['data'] as $row) {
            if (empty($row['Num'])) {
                continue;
            }

            // buscamos la factura
            $invoice = new FacturaCliente();
            $where = [
                new DataBaseWhere('codserie', $invoice->codserie),
                new DataBaseWhere('codigo', $row['Num']),
            ];
            if ($invoice->loadFromCode('', $where)) {
                continue;
            }

            // no está, la añadimos a la lista de pendientes
            $pending[] = $row;
        }

        // ordenamos la lista por fecha y número, de menos a más
        usort($pending, function ($a, $b) {
            if ($a['Fecha'] === $b['Fecha']) {
                return $a['Num'] <=> $b['Num'];
            }
            return strtotime($a['Fecha']) <=> strtotime($b['Fecha']);
        });

        // guardamos las facturas pendientes
        foreach ($pending as $row) {
            $invoice = new FacturaCliente();
            $invoice->setSubject(static::getCustomer($row));
            $invoice->codigo = $row['Num'];
            $invoice->fecha = CsvFileTools::formatDate($row['Fecha']);
            $invoice->observaciones = $row['Tags'];
            BusinessDocumentCode::setNewNumber($invoice);
            if (false === $invoice->save()) {
                break;
            }

            $this->continue = true;
            $offset++;
            $saveLines++;

            // añadimos la línea de la factura
            $newLine = $invoice->getNewLine();
            $newLine->descripcion = $row['Descripción'];
            $newLine->cantidad = 1;
            $newLine->pvpunitario = CsvFileTools::formatFloat($row['Subtotal']);
            if (false === $newLine->save()) {
                break;
            }

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

            foreach ($invoice->getReceipts() as $receipt) {
                // ¿Hay algo ya cobrado?
                if (CsvFileTools::formatFloat($row['Cobrado']) > 0.01) {
                    $receipt->importe = CsvFileTools::formatFloat($row['Cobrado']);
                    $receipt->pagado = true;
                    $receipt->fechapago = CsvFileTools::formatDate($row['Fecha de cobro']);
                    $receipt->vencimiento = CsvFileTools::formatDate($row['Fecha de cobro']);
                    $receipt->save();
                    break;
                }

                // anotamos la fecha de vencimiento
                $receipt->vencimiento = CsvFileTools::formatDate($row['Vencimiento']);
                $receipt->save();
                break;
            }
        }

        return true;
    }

    protected static function getCustomer(array $line): Cliente
    {
        // buscamos el cliente por nombre
        $customer = new Cliente();
        $where = [new DataBaseWhere('nombre', Tools::noHtml($line['Cliente']))];
        if ($customer->loadFromCode('', $where)) {
            return $customer;
        }

        // no lo hemos encontrado, lo creamos
        $customer->nombre = Tools::noHtml($line['Cliente']);
        $customer->cifnif = '';
        $customer->save();

        return $customer;
    }
}
