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
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\Pais;
use FacturaScripts\Plugins\CSVimport\Contract\ManualTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class CustomerInvoices extends ManualTemplateClass implements ManualTemplateInterface
{
    /** @var array */
    private $processingInvoices = [];

    public function getDataFields(): array
    {
        return [
            'facturascli.codigo' => ['title' => 'invoice-code'],
            'facturascli.numero' => ['title' => 'invoice-number'],
            'facturascli.numero2' => ['title' => 'number2'],
            'facturascli.fecha' => ['title' => 'date'],
            'facturascli.hora' => ['title' => 'hour'],
            'facturascli.codalmacen' => ['title' => 'warehouse-code'],
            'facturascli.codserie' => ['title' => 'serie'],
            'facturascli.coddivisa' => ['title' => 'currency'],
            'facturascli.dtopor1' => ['title' => 'invoice-dtopor1'],
            'facturascli.dtopor2' => ['title' => 'invoice-dtopor2'],
            'facturascli.neto' => ['title' => 'invoice-net'],
            'facturascli.totaliva' => ['title' => 'invoice-iva'],
            'facturascli.totalrecargo' => ['title' => 'invoice-surcharge'],
            'facturascli.total' => ['title' => 'invoice-total'],
            'facturascli.observaciones' => ['title' => 'observations'],
            'facturascli.nombrecliente' => ['title' => 'customer-name'],
            'facturascli.apellidos' => ['title' => 'customer-surname'],
            'facturascli.cifnif' => ['title' => 'cifnif'],
            'clientes.codcliente' => ['title' => 'customer-code'],
            'clientes.email' => ['title' => 'email'],
            'clientes.telefono1' => ['title' => 'phone'],
            'facturascli.direccion' => ['title' => 'address'],
            'facturascli.codpostal' => ['title' => 'zip-code'],
            'facturascli.apartado' => ['title' => 'post-office-box'],
            'facturascli.ciudad' => ['title' => 'city'],
            'facturascli.provincia' => ['title' => 'province'],
            'facturascli.codpais' => ['title' => 'country'],
            'lineasfacturascli.referencia' => ['title' => 'line-reference'],
            'lineasfacturascli.descripcion' => ['title' => 'line-description'],
            'lineasfacturascli.cantidad' => ['title' => 'line-quantity'],
            'lineasfacturascli.pvpunitario' => ['title' => 'line-price'],
            'lineasfacturascli.dtopor' => ['title' => 'line-dto'],
            'lineasfacturascli.dtopor2' => ['title' => 'line-dto-2'],
            'lineasfacturascli.iva' => ['title' => 'line-iva'],
            'lineasfacturascli.recargo' => ['title' => 'line-surcharge'],
            'lineasfacturascli.irpf' => ['title' => 'line-irpf'],
            'lineasfacturascli.suplido' => ['title' => 'line-supplied']
        ];
    }

    public function getFieldsToColumn(): array
    {
        return [];
    }

    public static function getProfile(): string
    {
        return 'customer-invoices';
    }

    public function getRequiredFieldsAnd(): array
    {
        return [];
    }

    public function getRequiredFieldsOr(): array
    {
        return ['facturascli.codigo', 'facturascli.numero'];
    }

    public function importItem(array $item): bool
    {
        // buscamos la factura
        $invoice = $this->findInvoice($item);
        if (null === $invoice) {
            return false;
        }

        if (false === $invoice->exists()) {
            // buscamos el cliente
            if (false === $this->findCustomer($invoice, $item)) {
                Tools::log()->warning('customer-not-found');
                return false;
            }

            // añadimos los datos de la factura
            if (false === $this->setModelValues($invoice, $item, 'facturascli.')) {
                return false;
            }
            // si no tiene código, generamos el código correspondiente
            if (empty($invoice->codigo)) {
                BusinessDocumentCode::setNewCode($invoice, empty($item['facturascli.numero']));
            } elseif (empty($item['facturascli.numero'])) {
                // si no tiene número, generamos el número correspondiente
                BusinessDocumentCode::setNewNumber($invoice);
            }

            // si el código es más largo de 20 caracteres, avisamos y terminamos
            if (strlen($invoice->codigo) > 20) {
                Tools::log()->warning('code-too-long', ['%code%' => $invoice->codigo, '%max%' => 20]);
                return false;
            }

            // guardamos
            $lines = [];
            if (false === Calculator::calculate($invoice, $lines, true)) {
                Tools::log()->error('invoice-error: ' . $invoice->codigo . ', ' . $invoice->fecha . ' (' . $item['facturascli.fecha'] . ')');
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

    protected function findCustomer(FacturaCliente &$invoice, array $item): bool
    {
        $where = [];
        if (isset($item['clientes.codcliente']) && !empty($item['clientes.codcliente'])) {
            $where[] = new DataBaseWhere('codcliente', $item['clientes.codcliente']);
        } elseif (isset($item['facturascli.cifnif']) && !empty($item['facturascli.cifnif'])) {
            $where[] = new DataBaseWhere('cifnif', $item['facturascli.cifnif']);
        } elseif (isset($item['clientes.email']) && !empty($item['clientes.email'])) {
            $where[] = new DataBaseWhere('email', $item['clientes.email']);
        } elseif (isset($item['facturascli.nombrecliente']) && !empty($item['facturascli.nombrecliente'])) {
            $where[] = new DataBaseWhere('nombre', $item['facturascli.nombrecliente']);
        }
        if (empty($where)) {
            // falta el código de cliente, cifnif, email o nombre
            Tools::log()->warning('missing-customer-data');
            return false;
        }

        $customer = new Cliente();
        if (false === $customer->loadFromCode('', $where)) {
            // creamos el cliente
            $name = $item['facturascli.nombrecliente'] ?? '';
            $surname = $item['facturascli.apellidos'] ?? '';
            if (empty($name)) {
                return false;
            }

            $customer->nombre = empty($surname) ? $name : implode(' ', [$name, $surname]);
            $customer->codcliente = $item['clientes.codcliente'] ?? null;
            $customer->cifnif = $item['facturascli.cifnif'] ?? '';
            $customer->email = $item['clientes.email'] ?? '';
            $customer->telefono1 = $item['clientes.telefono1'] ?? '';
            if (false === $customer->save()) {
                Tools::log()->error('customer-save-error: ' . $customer->nombre);
                return false;
            }
        }

        if (false === $invoice->setSubject($customer)) {
            return false;
        }

        // si hay una columna de dirección, desvinculamos la dirección del cliente en la factura
        if (isset($item['facturascli.direccion']) && !empty($item['facturascli.direccion'])) {
            $invoice->idcontactofact = null;
        }

        return true;
    }

    protected function findInvoice(array $item): ?FacturaCliente
    {
        $where = [];
        if (isset($item['facturascli.codalmacen']) && !empty($item['facturascli.codalmacen'])) {
            // si hay almacén, añadimos el almacén a la búsqueda
            $where[] = new DataBaseWhere('codalmacen', $item['facturascli.codalmacen']);
        }

        if (isset($item['facturascli.codigo']) && !empty($item['facturascli.codigo'])) {
            // si hay código, buscamos por código
            $where[] = new DataBaseWhere('codigo', $item['facturascli.codigo']);
        } elseif (isset($item['facturascli.numero']) && !empty($item['facturascli.numero'])) {
            // si hay número, buscamos por número y serie
            $where[] = new DataBaseWhere('numero', $item['facturascli.numero']);
            if (isset($item['facturascli.codserie']) && !empty($item['facturascli.codserie'])) {
                $where[] = new DataBaseWhere('codserie', $this->formatSerie($item['facturascli.codserie']));
            } else {
                // si no hay serie, usamos la predeterminada
                $where[] = new DataBaseWhere('codserie', Tools::settings('default', 'codserie'));
            }
            // si hay fecha, la usamos para filtrar mejor (dos facturas pueden tener mismo número y serie pero diferente fecha)
            if (isset($item['facturascli.fecha']) && !empty($item['facturascli.fecha'])) {
                $where[] = new DataBaseWhere('fecha', CsvFileTools::formatDate($item['facturascli.fecha']));
            }
        }

        if (empty($where)) {
            Tools::log()->warning('invoice-code-or-number-missing');
            return null;
        }

        // buscamos la factura en la base de datos
        $invoice = new FacturaCliente();
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

    protected function formatSerie($serie): string
    {
        return substr($serie, 0, 4);
    }

    protected function newLine(FacturaCliente $invoice, array $item): bool
    {
        // si tenemos el neto de la factura, pero no el precio de la línea, entonces creamos una línea con el neto
        if (isset($item['facturascli.neto'], $item['facturascli.totaliva'], $item['facturascli.total']) &&
            false === isset($item['lineasfacturascli.pvpunitario'])) {
            $line = $invoice->getNewLine();
            $line->cantidad = 1;
            $line->descripcion = 'Totales';

            // calculamos en base a los totales
            $neto = isset($item['facturascli.neto']) ? (float)CsvFileTools::formatFloat($item['facturascli.neto']) : 0.0;
            $totalIva = isset($item['facturascli.totaliva']) ? (float)CsvFileTools::formatFloat($item['facturascli.totaliva']) : 0.0;
            $iva = empty($neto) ? 0 : $totalIva * 100 / $neto;
            $totalRecargo = isset($item['facturascli.totalrecargo']) ? (float)CsvFileTools::formatFloat($item['facturascli.totalrecargo']) : 0.0;
            $recargo = empty($neto) ? 0 : $totalRecargo * 100 / $neto;
            $this->setLineTax($line, $iva, $recargo);

            $total = isset($item['facturascli.total']) ? (float)CsvFileTools::formatFloat($item['facturascli.total']) : 0.0;
            $line->pvpunitario = $total - $totalIva - $totalRecargo;
            $line->pvptotal = $total;
            return $line->save();
        }

        // en caso contrario, creamos una línea con los datos que nos han proporcionado
        $referencia = $item['lineasfacturascli.referencia'] ?? '';
        $line = $invoice->getNewProductLine($referencia);
        $line->descripcion = $item['lineasfacturascli.descripcion'] ?? $line->descripcion;
        $line->cantidad = isset($item['lineasfacturascli.cantidad']) ? (float)CsvFileTools::formatFloat($item['lineasfacturascli.cantidad']) : $line->cantidad;
        $line->dtopor = isset($item['lineasfacturascli.dtopor']) ? (float)CsvFileTools::formatFloat($item['lineasfacturascli.dtopor']) : $line->dtopor;
        $line->dtopor2 = isset($item['lineasfacturascli.dtopor2']) ? (float)CsvFileTools::formatFloat($item['lineasfacturascli.dtopor2']) : $line->dtopor2;
        $line->pvpunitario = isset($item['lineasfacturascli.pvpunitario']) ? (float)CsvFileTools::formatFloat($item['lineasfacturascli.pvpunitario']) : $line->pvpunitario;
        $line->irpf = isset($item['lineasfacturascli.irpf']) ? (float)CsvFileTools::formatFloat($item['lineasfacturascli.irpf']) : $line->irpf;
        $line->suplido = (bool)($item['lineasfacturascli.suplido'] ?? 0);

        $iva = isset($item['lineasfacturascli.iva']) ? (float)CsvFileTools::formatFloat($item['lineasfacturascli.iva']) : $line->iva;
        $recargo = isset($item['lineasfacturascli.recargo']) ? (float)CsvFileTools::formatFloat($item['lineasfacturascli.recargo']) : $line->recargo;
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

                case 'codpais':
                    if (empty($values[$prefix . $key])) {
                        $model->{$key} = $model->codpais;
                        break;
                    }

                    $country = new Pais();
                    $where = [
                        new DataBaseWhere('codiso', $values[$prefix . $key]),
                        new DataBaseWhere('codpais', $values[$prefix . $key], '=', 'OR')
                    ];
                    if (false === $country->loadFromCode('', $where)) {
                        // creamos el país
                        $country->codpais = $values[$prefix . $key];
                        $country->nombre = $values[$prefix . $key];
                        if (false === $country->save()) {
                            return false;
                        }
                    }
                    $model->{$key} = $country->codpais;
                    break;

                case 'codpostal':
                    $model->{$key} = CsvFileTools::formatString($values[$prefix . $key], 10);
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
