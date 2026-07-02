<?php
/**
 * Copyright (C) 2020-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\ManualTemplates;

use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Almacenes;
use FacturaScripts\Core\DataSrc\Divisas;
use FacturaScripts\Core\DataSrc\Impuestos;
use FacturaScripts\Core\DataSrc\Series;
use FacturaScripts\Core\Model\Base\BusinessDocumentLine;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\BusinessDocumentCode;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\CSVimport\Contract\ManualTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class SupplierInvoices extends ManualTemplateClass implements ManualTemplateInterface
{
    /** @var array */
    private $processingInvoices = [];

    public function getDataFields(): array
    {
        return [
            'facturasprov.codigo' => ['title' => 'invoice-code', 'required' => true],
            'facturasprov.numero' => ['title' => 'invoice-number', 'required' => true],
            'facturasprov.numproveedor' => ['title' => 'numsupplier'],
            'facturasprov.fecha' => ['title' => 'date'],
            'facturasprov.hora' => ['title' => 'hour'],
            'facturasprov.codalmacen' => ['title' => 'warehouse-code'],
            'facturasprov.codserie' => ['title' => 'serie'],
            'facturasprov.coddivisa' => ['title' => 'currency'],
            'facturasprov.dtopor1' => ['title' => 'invoice-dtopor1'],
            'facturasprov.dtopor2' => ['title' => 'invoice-dtopor2'],
            'facturasprov.neto' => ['title' => 'invoice-net'],
            'facturasprov.totaliva' => ['title' => 'invoice-iva'],
            'facturasprov.totalrecargo' => ['title' => 'invoice-surcharge'],
            'facturasprov.total' => ['title' => 'invoice-total'],
            'facturasprov.observaciones' => ['title' => 'observations'],
            'facturasprov.nombreproveedor' => ['title' => 'supplier-name'],
            'facturasprov.cifnif' => ['title' => 'cifnif'],
            'proveedores.codproveedor' => ['title' => 'supplier-code'],
            'proveedores.email' => ['title' => 'email'],
            'proveedores.telefono1' => ['title' => 'phone'],
            'lineasfacturasprov.referencia' => ['title' => 'line-reference'],
            'lineasfacturasprov.descripcion' => ['title' => 'line-description'],
            'lineasfacturasprov.cantidad' => ['title' => 'line-quantity'],
            'lineasfacturasprov.pvpunitario' => ['title' => 'line-price'],
            'lineasfacturasprov.dtopor' => ['title' => 'line-dto'],
            'lineasfacturasprov.dtopor2' => ['title' => 'line-dto-2'],
            'lineasfacturasprov.iva' => ['title' => 'line-iva'],
            'lineasfacturasprov.recargo' => ['title' => 'line-surcharge'],
            'lineasfacturasprov.irpf' => ['title' => 'line-irpf'],
            'lineasfacturasprov.suplido' => ['title' => 'line-supplied']
        ];
    }

    public function getFieldsToColumn(): array
    {
        return [];
    }

    public static function getProfile(): string
    {
        return 'supplier-invoices';
    }

    public function getRequiredFieldsAnd(): array
    {
        return [];
    }

    public function getRequiredFieldsOr(): array
    {
        return ['facturasprov.codigo', 'facturasprov.numero'];
    }

    public function importItem(array $item): bool
    {
        // buscamos la factura
        $invoice = $this->findInvoice($item);
        if (null === $invoice) {
            return false;
        }

        if (false === $invoice->exists()) {
            // buscamos el proveedor
            if (false === $this->findSupplier($invoice, $item)) {
                Tools::log()->warning('supplier-not-found');
                return false;
            }

            // añadimos los datos de la factura
            if (false === $this->setModelValues($invoice, $item, 'facturasprov.')) {
                return false;
            }
            // si no tiene código, generamos el código correspondiente
            if (empty($invoice->codigo)) {
                BusinessDocumentCode::setNewCode($invoice, empty($item['facturasprov.numero']));
            } elseif (empty($item['facturasprov.numero'])) {
                // si no tiene número, lo generamos
                BusinessDocumentCode::setNewNumber($invoice);
            }

            // si el código es más largo de 20 caracteres, avisamos y terminamos
            if (strlen($invoice->codigo) > 20) {
                Tools::log()->warning('code-too-long', ['%code%' => $invoice->codigo, '%max%' => 20]);
                return false;
            }

            // guardamos la factura
            $lines = [];
            if (false === Calculator::calculate($invoice, $lines, true)) {
                Tools::log()->error('invoice-error: ' . $invoice->codigo . ', ' . $invoice->fecha . ' (' . $item['facturasprov.fecha'] . ')');
                return false;
            }
        }

        // añadimos la línea a la factura
        if (false === $this->newLine($invoice, $item)) {
            Tools::log()->error('invoice-line-error: ' . $invoice->codigo);
            return false;
        }

        // actualizamos los totales de la factura
        $lines = $invoice->getLines();
        if (false === Calculator::calculate($invoice, $lines, true)) {
            Tools::log()->error('invoice-calculation-error: ' . $invoice->codigo);
            return false;
        }

        // añadimos a la lista de facturas procesadas
        $this->processingInvoices[] = $invoice->idfactura;
        return true;
    }

    protected function findInvoice(array $item): ?FacturaProveedor
    {
        $where = [];
        if (isset($item['facturasprov.codalmacen']) && !empty($item['facturasprov.codalmacen'])) {
            // si hay almacén, añadimos el almacén a la búsqueda
            $where[] = new DataBaseWhere('codalmacen', $item['facturasprov.codalmacen']);
        }

        if (isset($item['facturasprov.codigo']) && !empty($item['facturasprov.codigo'])) {
            // si hay código, buscamos por código
            $where[] = new DataBaseWhere('codigo', $item['facturasprov.codigo']);
        } elseif (isset($item['facturasprov.numero']) && !empty($item['facturasprov.numero'])) {
            // si hay número, buscamos por número y serie
            $where[] = new DataBaseWhere('numero', $item['facturasprov.numero']);
            if (isset($item['facturasprov.codserie']) && !empty($item['facturasprov.codserie'])) {
                $where[] = new DataBaseWhere('codserie', $this->formatSerie($item['facturasprov.codserie']));
            } else {
                // si no hay serie, usamos la predeterminada
                $where[] = new DataBaseWhere('codserie', Tools::settings('default', 'codserie'));
            }
            // si hay fecha, la usamos para filtrar mejor (dos facturas pueden tener mismo número y serie pero diferente fecha)
            if (isset($item['facturasprov.fecha']) && !empty($item['facturasprov.fecha'])) {
                $where[] = new DataBaseWhere('fecha', CsvFileTools::formatDate($item['facturasprov.fecha']));
            }
        }

        if (empty($where)) {
            Tools::log()->warning('invoice-code-or-number-missing');
            return null;
        }

        // buscamos la factura en la base de datos
        $invoice = new FacturaProveedor();
        if (false === $invoice->loadFromCode('', $where)) {
            // no la hemos encontrado, devolvemos la factura vacía
            return $invoice;
        }

        // hemos encontrado la factura, comprobamos si ya la estábamos procesando
        if (in_array($invoice->idfactura, $this->processingInvoices)) {
            // la estábamos procesando, así que podemos modificarla, la devolvemos
            return $invoice;
        }

        // la factura ya estaba en la base de datos, pero no la estábamos procesando, así que no podemos modificarla
        Tools::log()->warning('invoice-already-exists', ['%invoice%' => $invoice->codigo]);
        return null;
    }

    protected function findSupplier(FacturaProveedor &$invoice, array $item): bool
    {
        $where = [];
        if (isset($item['proveedores.codproveedor']) && !empty($item['proveedores.codproveedor'])) {
            $where[] = new DataBaseWhere('codproveedor', $item['proveedores.codproveedor']);
        } elseif (isset($item['facturasprov.cifnif']) && !empty($item['facturasprov.cifnif'])) {
            $where[] = new DataBaseWhere('cifnif', $item['facturasprov.cifnif']);
        } elseif (isset($item['proveedores.email']) && !empty($item['proveedores.email'])) {
            $where[] = new DataBaseWhere('email', $item['proveedores.email']);
        } elseif (isset($item['facturasprov.nombreproveedor']) && !empty($item['facturasprov.nombreproveedor'])) {
            $where[] = new DataBaseWhere('nombre', $item['facturasprov.nombreproveedor']);
        }
        if (empty($where)) {
            // falta el código de proveedor, cifnif, email o nombre
            Tools::log()->warning('missing-supplier-data');
            return false;
        }

        $supplier = new Proveedor();
        if (false === $supplier->loadFromCode('', $where)) {
            $supplier->nombre = $item['facturasprov.nombreproveedor'] ?? null;
            $supplier->codproveedor = $item['proveedores.codproveedor'] ?? null;
            $supplier->cifnif = $item['facturasprov.cifnif'] ?? '';
            $supplier->email = $item['proveedores.email'] ?? '';
            $supplier->telefono1 = $item['proveedores.telefono1'] ?? '';
            if (false === $supplier->save()) {
                Tools::log()->error('supplier-save-error: ' . $supplier->nombre);
                return false;
            }
        }

        return $invoice->setSubject($supplier);
    }

    protected function formatSerie($serie): string
    {
        return substr($serie, 0, 4);
    }

    protected function newLine(FacturaProveedor $invoice, array $item): bool
    {
        // si tenemos el neto de la factura, pero no el precio de la línea, entonces creamos una line con el neto
        if (isset($item['facturasprov.neto'], $item['facturasprov.totaliva'], $item['facturasprov.total']) &&
            false === isset($item['lineasfacturasprov.pvpunitario'])) {
            $line = $invoice->getNewLine();
            $line->cantidad = 1;
            $line->descripcion = 'Totales';

            // calculamos en base a los totales
            $neto = isset($item['facturasprov.neto']) ? (float)CsvFileTools::formatFloat($item['facturasprov.neto']) : 0.0;
            $totalIva = isset($item['facturasprov.totaliva']) ? (float)CsvFileTools::formatFloat($item['facturasprov.totaliva']) : 0.0;
            $iva = empty($neto) ? 0 : $totalIva * 100 / $neto;
            $totalRecargo = isset($item['facturasprov.totalrecargo']) ? (float)CsvFileTools::formatFloat($item['facturasprov.totalrecargo']) : 0.0;
            $recargo = empty($neto) ? 0 : $totalRecargo * 100 / $neto;
            $this->setLineTax($line, $iva, $recargo);

            $total = isset($item['facturasprov.total']) ? (float)CsvFileTools::formatFloat($item['facturasprov.total']) : 0.0;
            $line->pvpunitario = $total - $totalIva - $totalRecargo;
            $line->pvptotal = $total;
            return $line->save();
        }

        // en caso contrario, creamos una línea con los datos que nos han proporcionado
        $referencia = $item['lineasfacturasprov.referencia'] ?? '';
        $line = $invoice->getNewProductLine($referencia);
        $line->descripcion = $item['lineasfacturasprov.descripcion'] ?? $line->descripcion;
        $line->cantidad = isset($item['lineasfacturasprov.cantidad']) ? (float)CsvFileTools::formatFloat($item['lineasfacturasprov.cantidad']) : $line->cantidad;
        $line->dtopor = isset($item['lineasfacturasprov.dtopor']) ? (float)CsvFileTools::formatFloat($item['lineasfacturasprov.dtopor']) : $line->dtopor;
        $line->dtopor2 = isset($item['lineasfacturasprov.dtopor2']) ? (float)CsvFileTools::formatFloat($item['lineasfacturasprov.dtopor2']) : $line->dtopor2;
        $line->pvpunitario = isset($item['lineasfacturasprov.pvpunitario']) ? CsvFileTools::formatFloat($item['lineasfacturasprov.pvpunitario']) : $line->pvpunitario;
        $line->irpf = isset($item['lineasfacturasprov.irpf']) ? (float)CsvFileTools::formatFloat($item['lineasfacturasprov.irpf']) : $line->irpf;
        $line->suplido = (bool)($item['lineasfacturasprov.suplido'] ?? 0);

        $iva = isset($item['lineasfacturasprov.iva']) ? (float)CsvFileTools::formatFloat($item['lineasfacturasprov.iva']) : $line->iva;
        $recargo = isset($item['lineasfacturasprov.recargo']) ? (float)CsvFileTools::formatFloat($item['lineasfacturasprov.recargo']) : $line->recargo;
        $this->setLineTax($line, $iva, $recargo);
        return $line->save();
    }

    protected function setLineTax(BusinessDocumentLine &$line, float $iva, float $recargo): void
    {
        $line->codimpuesto = null;
        $line->iva = $iva;
        $line->recargo = $recargo;

        // probamos primero el impuesto predeterminado
        if (Impuestos::default()->iva == $iva) {
            $line->codimpuesto = Impuestos::default()->codimpuesto;
            return;
        }

        // buscamos el impuesto correspondiente
        foreach (Impuestos::all() as $impuesto) {
            if ($impuesto->iva == $iva) {
                $line->codimpuesto = $impuesto->codimpuesto;
                break;
            }
        }
    }

    protected function setModelValues(ModelClass &$model, array $values, string $prefix): bool
    {
        if (false === parent::setModelValues($model, $values, $prefix)) {
            return false;
        }

        foreach ($model->getModelFields() as $key => $field) {
            if (!isset($values[$prefix . $key])) {
                continue;
            }

            switch ($field['name']) {
                case 'codalmacen':
                    $warehouse = Almacenes::get($values[$prefix . $key]);
                    if (empty($warehouse->primaryColumnValue())) {
                        Tools::log()->warning('warehouse-not-found', ['%code%' => $values[$prefix . $key]]);
                        return false;
                    }

                    $model->{$key} = $warehouse->codalmacen;
                    break;

                case 'coddivisa':
                    $currency = Divisas::get($values[$prefix . $key]);
                    if (empty($currency->primaryColumnValue())) {
                        $model->{$key} = null;
                    }
                    break;

                case 'codserie':
                    $codserie = $this->formatSerie($values[$prefix . $key]);

                    // si la serie proporcionada está vacía, usamos la por defecto
                    if (empty($codserie)) {
                        $model->{$key} = Tools::settings('default', 'codserie');
                        break;
                    }

                    // si la serie existe, la asignamos
                    $serie = Series::get($codserie);
                    if (false === empty($serie->primaryColumnValue())) {
                        $model->{$key} = $serie->codserie;
                        break;
                    }

                    // si la serie no existe, la creamos
                    $serie->codserie = $codserie;
                    $serie->descripcion = $values[$prefix . $key];
                    if (false === $serie->save()) {
                        return false;
                    }

                    $model->{$key} = $serie->codserie;
                    break;

                case 'fecha':
                    if (false === $model->setDate($model->fecha, $model->hora)) {
                        return false;
                    }
                    break;

                case 'numero':
                    if (false === is_numeric($values[$prefix . $key])) {
                        Tools::log()->warning('invalid-invoice-number', ['%number%' => $values[$prefix . $key]]);
                        return false;
                    }
                    break;
            }
        }
        return true;
    }
}
