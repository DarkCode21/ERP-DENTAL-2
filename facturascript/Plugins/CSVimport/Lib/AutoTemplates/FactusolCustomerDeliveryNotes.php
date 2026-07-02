<?php
/**
 * Copyright (C) 2020-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\AutoTemplates;

use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Impuestos;
use FacturaScripts\Core\DataSrc\Series;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\LineaAlbaranCliente;
use FacturaScripts\Plugins\CSVimport\Contract\AutoTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class FactusolCustomerDeliveryNotes implements AutoTemplateInterface
{
    const LIMIT_IMPORT = 10;

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
        if ($profile !== 'customer-delivery-notes') {
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
                $csv['titles'][2] === 'Fecha' &&
                $csv['titles'][3] === 'Cliente') {
                $deliveryNotes = $this->readDeliveryNotes($csv['data']);
                $this->total_lines = count($deliveryNotes);
                return true;
            }
        }

        return false;
    }

    public function run(string $filePath, string $profile, string $mode, int &$offset, int &$saveLines): bool
    {
        $csv = CsvFileTools::read($filePath, $this->start);
        $deliveryNotes = $this->readDeliveryNotes($csv['data']);
        $this->total_lines = count($deliveryNotes);
        $this->continue = false;

        // recorremos los albaranes empezando por el offset y terminando por el limit
        for ($i = $offset; $i < min($this->total_lines, $offset + static::LIMIT_IMPORT); $i++) {
            $row = $deliveryNotes[$i]['note'];

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

            // buscamos si ya existe el albarán
            $old = new AlbaranCliente();
            $where = [
                new DataBaseWhere('codserie', $row['S.']),
                new DataBaseWhere('numero2', $row['Num.'] ?? $row['Núm.']),
                new DataBaseWhere('codejercicio', $exercise->codejercicio)
            ];
            if ($old->loadFromCode('', $where)) {
                continue;
            }

            // comprobamos si ya está facturado
            if ($row['Estado'] === 'Facturado') {
                continue;
            }

            // creamos el albarán
            $newDeliveryNote = new AlbaranCliente();
            $newDeliveryNote->setSubject(static::getCustomer($row));
            $newDeliveryNote->idempresa = $exercise->idempresa;
            $newDeliveryNote->codejercicio = $exercise->codejercicio;
            $newDeliveryNote->fecha = CsvFileTools::formatDate($row['Fecha']);
            $newDeliveryNote->hora = date('H:i:s');
            $newDeliveryNote->codserie = Series::get($row['S.'])->codserie;
            $newDeliveryNote->numero = (int)($row['Num.'] ?? $row['Núm.']);
            $newDeliveryNote->numero2 = $row['Num.'] ?? $row['Núm.'];
            if (false === $newDeliveryNote->save()) {
                break;
            }

            $saveLines++;

            // añadimos las líneas
            foreach ($deliveryNotes[$i]['lines'] as $line) {
                $newLine = $newDeliveryNote->getNewLine();
                $newLine->descripcion = $line['Cliente'] . ' ' . $line['Estado'] . ' ' . $line['F.Pago'];
                $newLine->cantidad = (float)$line['Base'];
                $newLine->pvpunitario = CsvFileTools::formatFloat($line['IVA']);
                $newLine->dtopor = 100;
                $newLine->dtopor2 = 0;
                $newLine->save();
            }

            // añadimos la línea con los totales
            $newTotalLine = $newDeliveryNote->getNewLine();
            $newTotalLine->descripcion = 'Total';
            static::setIVA($newTotalLine, $row['Base'], $row['IVA'], $row['Rec']);
            $newTotalLine->save();

            // actualizamos los totales
            $lines = $newDeliveryNote->getLines();
            Calculator::calculate($newDeliveryNote, $lines, true);
            if (abs($newDeliveryNote->total - CsvFileTools::formatFloat($row['Total'])) > 0.01) {
                Tools::log()->warning('total-value-error', [
                    '%docType%' => $newDeliveryNote->modelClassName(),
                    '%docCode%' => $newDeliveryNote->codigo,
                    '%docTotal%' => $newDeliveryNote->total,
                    '%calcTotal%' => CsvFileTools::formatFloat($row['Total'])
                ]);
            }
        }

        $offset += static::LIMIT_IMPORT;

        return true;
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

    protected function readDeliveryNotes(array $data): array
    {
        // Inicializar el array para almacenar los albaranes
        $deliveryNotes = [];
        $currentDeliveryNote = [];

        foreach ($data as $row) {
            $num = $row['Num.'] ?? $row['Núm.'];

            // Comprobación para detectar el inicio de un nuevo albarán
            if (!empty($row['S.']) && !empty($num)) {
                // Si hay datos en ambas columnas, estamos en un nuevo albarán
                if (!empty($currentDeliveryNote)) {
                    // Si ya tenemos un albarán acumulado, lo añadimos a la lista
                    $deliveryNotes[] = $currentDeliveryNote;
                }
                $currentDeliveryNote = ['date' => $row['Fecha'], 'note' => $row, 'lines' => []];
            } elseif ($row['Fecha'] == 'Código') {
                // Se alcanzaron las líneas de productos, se saltan las cabeceras
                continue;
            } elseif (!empty($currentDeliveryNote) && empty($row['S.']) && empty($num) && !empty($row['Fecha'])) {
                // es una línea de producto
                $currentDeliveryNote['lines'][] = $row;
            }
        }

        // añadimos el último albarán leído, si existe
        if (!empty($currentDeliveryNote)) {
            $deliveryNotes[] = $currentDeliveryNote;
        }

        // ordenamos por fecha, de menor a mayor
        usort($deliveryNotes, function ($a, $b) {
            $date_a = CsvFileTools::formatDate($a['date']);
            $date_b = CsvFileTools::formatDate($b['date']);
            return strtotime($date_a) <=> strtotime($date_b);
        });

        return $deliveryNotes;
    }

    protected static function setIVA(LineaAlbaranCliente &$line, string $net, string $totalIva, string $re): void
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
