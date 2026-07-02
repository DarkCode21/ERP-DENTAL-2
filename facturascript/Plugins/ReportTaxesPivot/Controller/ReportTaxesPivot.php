<?php

/**
 * Plugin ReportTaxesPivot para FacturaScripts
 * Informe de impuestos pivotado: una fila por factura, columnas dinámicas por tipo de IVA.
 * Columnas: SERIE, CÓDIGO, NUM2, NOMBRE, CIF/NIF, PAÍS, B0, B4, B10, B21..., IVA 4, IVA 10, IVA 21..., TOTAL
 */

namespace FacturaScripts\Plugins\ReportTaxesPivot\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\ControllerPermissions;
use FacturaScripts\Core\DataSrc\Divisas;
use FacturaScripts\Core\DataSrc\FormasPago;
use FacturaScripts\Core\DataSrc\Paises;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Lib\InvoiceOperation;
use FacturaScripts\Dinamic\Model\Divisa;
use FacturaScripts\Dinamic\Model\Pais;
use FacturaScripts\Dinamic\Model\Serie;
use FacturaScripts\Dinamic\Model\User;
use Symfony\Component\HttpFoundation\Response;

class ReportTaxesPivot extends Controller
{
    /** @var string */
    public $coddivisa;

    /** @var string */
    public $codpais;

    /** @var string */
    public $codserie;

    /** @var string */
    public $datefrom;

    /** @var string */
    public $dateto;

    /** @var Divisa */
    public $divisa;

    /** @var string */
    public $format;

    /** @var int */
    public $idempresa;

    /** @var Pais */
    public $pais;

    /** @var Serie */
    public $serie;

    /** @var string */
    public $source;

    /** @var string */
    public $typeDate;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'taxes-pivot';
        $data['menu'] = 'reports';
        $data['icon'] = 'fas fa-table';
        return $data;
    }

    /**
     * @param Response $response
     * @param User $user
     * @param ControllerPermissions $permissions
     */
    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $this->divisa = new Divisa();
        $this->pais = new Pais();
        $this->serie = new Serie();
        $this->initFilters();

        if ('export' === $this->request->request->get('action')) {
            $this->exportAction();
        }
    }

    protected function exportAction(): void
    {
        $i18n = Tools::lang();
        $data = $this->getReportData();
        if (empty($data)) {
            Tools::log()->warning('no-data');
            return;
        }

        // Recopilar todos los tipos de IVA únicos presentes en los datos, ordenados
        $rates = [];
        foreach ($data as $row) {
            foreach (array_keys($row['bases']) as $rate) {
                $rates[$rate] = true;
            }
        }
        ksort($rates);
        $rates = array_keys($rates);

        // Tasas con impuesto (>0) para columnas de cuota IVA
        $ratesWithTax = array_values(array_filter($rates, function ($r) {
            return $r > 0;
        }));

        // Construir líneas: una fila por factura
        $num2title = $this->source === 'sales' ? $i18n->trans('number2') : $i18n->trans('numproveedor');
        $lines = [];
        foreach ($data as $row) {
            $formaPago = $row['codpago'] ? FormasPago::get($row['codpago'])->descripcion : '';

            $line = [
                $i18n->trans('code')           => $row['codigo'],
                $num2title                     => $row['numero2'],
                $i18n->trans('date')           => Tools::date($row['fecha']),
                $i18n->trans('name')           => Tools::fixHtml($row['nombre']),
                $i18n->trans('cifnif')         => $row['cifnif'],
                $i18n->trans('city')           => Tools::fixHtml($row['ciudad'] ?? ''),
                $i18n->trans('address')        => Tools::fixHtml($row['direccion'] ?? ''),
                $i18n->trans('zip-code')       => $row['codpostal'] ?? '',
                $i18n->trans('country')        => $row['codpais'] ? Paises::get($row['codpais'])->nombre : '',
            ];

            // Columnas base imponible: B0, B4, B10, B21...
            foreach ($rates as $rate) {
                $label = 'B' . $this->formatRate($rate);
                $line[$label] = $this->fmtNum($row['bases'][$rate] ?? 0);
            }

            // Columnas cuota IVA: IVA 4, IVA 10, IVA 21...
            foreach ($ratesWithTax as $rate) {
                $label = 'IVA ' . $this->formatRate($rate);
                $line[$label] = $this->fmtNum($row['cuotas'][$rate] ?? 0);
            }

            $line[$i18n->trans('total')]          = $this->fmtNum($row['total']);
            $line[$i18n->trans('payment-method')] = $formaPago;
            $lines[] = $line;
        }

        // Construir fila de totales
        $totals = $this->buildTotals($data, $rates, $ratesWithTax, $i18n);

        $this->setTemplate(false);
        $this->processLayout($lines, $totals, $rates, $ratesWithTax);
    }

    /**
     * Formatea la tasa de IVA: entero si no tiene decimales.
     * Ej: 10.0 -> "10", 2.5 -> "2,5"
     */
    protected function formatRate(float $rate): string
    {
        return $rate == (int)$rate ? (string)(int)$rate : str_replace('.', ',', (string)$rate);
    }

    protected function fmtNum($value): string
    {
        if ($this->format === 'PDF') {
            return Tools::number($value);
        }
        return (string)$value;
    }

    protected function buildTotals(array $data, array $rates, array $ratesWithTax, $i18n): array
    {
        $totBases  = [];
        $totCuotas = [];
        $totTotal  = 0.0;

        foreach ($data as $row) {
            foreach ($rates as $rate) {
                $totBases[$rate] = ($totBases[$rate] ?? 0) + ($row['bases'][$rate] ?? 0);
            }
            foreach ($ratesWithTax as $rate) {
                $totCuotas[$rate] = ($totCuotas[$rate] ?? 0) + ($row['cuotas'][$rate] ?? 0);
            }
            $totTotal += $row['total'];
        }

        $num2title = $this->source === 'sales' ? $i18n->trans('number2') : $i18n->trans('numproveedor');
        $line = [
            $i18n->trans('code')     => '',
            $num2title               => '',
            $i18n->trans('date')     => '',
            $i18n->trans('name')     => $i18n->trans('total'),
            $i18n->trans('cifnif')   => '',
            $i18n->trans('city')     => '',
            $i18n->trans('address')  => '',
            $i18n->trans('zip-code') => '',
            $i18n->trans('country')  => '',
        ];

        foreach ($rates as $rate) {
            $label = 'B' . $this->formatRate($rate);
            $line[$label] = $this->fmtNum(round($totBases[$rate] ?? 0, FS_NF0));
        }
        foreach ($ratesWithTax as $rate) {
            $label = 'IVA ' . $this->formatRate($rate);
            $line[$label] = $this->fmtNum(round($totCuotas[$rate] ?? 0, FS_NF0));
        }
        $line[$i18n->trans('total')]          = $this->fmtNum(round($totTotal, FS_NF0));
        $line[$i18n->trans('payment-method')] = '';

        return [$line];
    }

    protected function getReportData(): array
    {
        $sql = '';
        $numCol     = strtolower(FS_DB_TYPE) == 'postgresql' ? 'CAST(f.numero as integer)' : 'CAST(f.numero as unsigned)';
        $columnDate = $this->typeDate === 'create' ? 'f.fecha' : 'COALESCE(f.fechadevengo, f.fecha)';

        switch ($this->source) {
            case 'purchases':
                $sql .= 'SELECT f.codserie, f.codigo, f.numproveedor AS numero2, f.fecha, f.fechadevengo,'
                    . ' f.nombre, f.cifnif, f.codpago, l.pvptotal, l.iva, l.recargo, l.irpf, l.suplido,'
                    . ' f.dtopor1, f.dtopor2, f.total, f.operacion'
                    . ' FROM lineasfacturasprov AS l'
                    . ' LEFT JOIN facturasprov AS f ON l.idfactura = f.idfactura'
                    . ' WHERE f.idempresa = ' . $this->dataBase->var2str($this->idempresa)
                    . ' AND ' . $columnDate . ' >= ' . $this->dataBase->var2str($this->datefrom)
                    . ' AND ' . $columnDate . ' <= ' . $this->dataBase->var2str($this->dateto)
                    . ' AND (l.pvptotal <> 0.00 OR l.iva <> 0.00)'
                    . ' AND f.coddivisa = ' . $this->dataBase->var2str($this->coddivisa);
                break;

            case 'sales':
                $sql .= 'SELECT f.codserie, f.codigo, f.numero2, f.fecha, f.fechadevengo,'
                    . ' f.nombrecliente AS nombre, f.cifnif, f.codpago, f.ciudad, f.codpostal, f.direccion,'
                    . ' l.pvptotal, l.iva, l.recargo, l.irpf, l.suplido,'
                    . ' f.dtopor1, f.dtopor2, f.total, f.operacion, f.codpais'
                    . ' FROM lineasfacturascli AS l'
                    . ' LEFT JOIN facturascli AS f ON l.idfactura = f.idfactura'
                    . ' WHERE f.idempresa = ' . $this->dataBase->var2str($this->idempresa)
                    . ' AND ' . $columnDate . ' >= ' . $this->dataBase->var2str($this->datefrom)
                    . ' AND ' . $columnDate . ' <= ' . $this->dataBase->var2str($this->dateto)
                    . ' AND (l.pvptotal <> 0.00 OR l.iva <> 0.00)'
                    . ' AND f.coddivisa = ' . $this->dataBase->var2str($this->coddivisa);
                if ($this->codpais) {
                    $sql .= ' AND codpais = ' . $this->dataBase->var2str($this->codpais);
                }
                break;

            default:
                Tools::log()->warning('wrong-source');
                return [];
        }

        if ($this->codserie) {
            $sql .= ' AND codserie = ' . $this->dataBase->var2str($this->codserie);
        }
        $sql .= ' ORDER BY ' . $columnDate . ', ' . $numCol . ' ASC;';

        // Agrupar por factura (codigo), pivotando los tipos de IVA en columnas
        $data = [];
        foreach ($this->dataBase->select($sql) as $row) {
            $pvpTotal = floatval($row['pvptotal'])
                * (100 - floatval($row['dtopor1']))
                * (100 - floatval($row['dtopor2']))
                / 10000;

            $invoiceKey = $row['codigo'];
            $iva        = (float)$row['iva'];
            $isSuplido  = (bool)$row['suplido'];
            $isIntra    = isset($row['operacion']) && $row['operacion'] === InvoiceOperation::INTRA_COMMUNITY;

            $lineNeto  = $isSuplido ? 0.0 : $pvpTotal;
            $lineCuota = ($isSuplido || $isIntra) ? 0.0 : ($iva * $pvpTotal / 100);

            if (isset($data[$invoiceKey])) {
                $data[$invoiceKey]['bases'][$iva]  = ($data[$invoiceKey]['bases'][$iva] ?? 0) + $lineNeto;
                $data[$invoiceKey]['cuotas'][$iva] = ($data[$invoiceKey]['cuotas'][$iva] ?? 0) + $lineCuota;
            } else {
                $data[$invoiceKey] = [
                    'codserie'  => $row['codserie'],
                    'codigo'    => $row['codigo'],
                    'numero2'   => $row['numero2'],
                    'fecha'     => $this->typeDate == 'create'
                        ? $row['fecha']
                        : ($row['fechadevengo'] ?? $row['fecha']),
                    'nombre'    => $row['nombre'],
                    'cifnif'    => $row['cifnif'],
                    'ciudad'    => $row['ciudad'] ?? '',
                    'codpostal' => $row['codpostal'] ?? '',
                    'direccion' => $row['direccion'] ?? '',
                    'codpais'   => $row['codpais'] ?? null,
                    'codpago'   => $row['codpago'] ?? null,
                    'total'     => (float)$row['total'],
                    'bases'     => [$iva => $lineNeto],
                    'cuotas'    => [$iva => $lineCuota],
                ];
            }
        }

        // Redondear bases y cuotas
        foreach ($data as $key => $value) {
            foreach ($value['bases'] as $rate => $amount) {
                $data[$key]['bases'][$rate] = round($amount, FS_NF0);
            }
            foreach ($value['cuotas'] as $rate => $amount) {
                $data[$key]['cuotas'][$rate] = round($amount, FS_NF0);
            }
        }

        return $data;
    }

    protected function initFilters(): void
    {
        $this->coddivisa = $this->request->request->get(
            'coddivisa',
            Tools::settings('default', 'coddivisa')
        );

        $this->codpais  = $this->request->request->get('codpais', '');
        $this->codserie = $this->request->request->get('codserie', '');
        $this->datefrom = $this->request->request->get('datefrom', $this->getQuarterDate(true));
        $this->dateto   = $this->request->request->get('dateto', $this->getQuarterDate(false));

        $this->idempresa = (int)$this->request->request->get(
            'idempresa',
            Tools::settings('default', 'idempresa')
        );

        $this->format   = $this->request->request->get('format');
        $this->source   = $this->request->request->get('source');
        $this->typeDate = $this->request->request->get('type-date');
    }

    protected function getQuarterDate(bool $start): string
    {
        $month = (int)date('m');

        if ($month === 1) {
            return $start
                ? date('Y-10-01', strtotime('-1 year'))
                : date('Y-12-31', strtotime('-1 year'));
        }
        if ($month >= 1 && $month <= 4) {
            return $start ? date('Y-01-01') : date('Y-03-31');
        }
        if ($month >= 4 && $month <= 7) {
            return $start ? date('Y-04-01') : date('Y-06-30');
        }
        if ($month >= 7 && $month <= 10) {
            return $start ? date('Y-07-01') : date('Y-09-30');
        }
        return $start ? date('Y-10-01') : date('Y-12-31');
    }

    protected function processLayout(array &$lines, array &$totals, array $rates, array $ratesWithTax): void
    {
        $i18n          = Tools::lang();
        $exportManager = new ExportManager();
        $exportManager->setOrientation('landscape');
        $exportManager->newDoc($this->format, $i18n->trans('taxes-pivot'));
        $exportManager->setCompany($this->idempresa);

        // Tabla informativa de filtros
        $exportManager->addTablePage(
            [
                $i18n->trans('report'),
                $i18n->trans('currency'),
                $i18n->trans('date'),
                $i18n->trans('from-date'),
                $i18n->trans('until-date'),
            ],
            [[
                $i18n->trans('report')     => $i18n->trans('taxes-pivot') . ' — ' . $i18n->trans($this->source),
                $i18n->trans('currency')   => Divisas::get($this->coddivisa)->descripcion,
                $i18n->trans('date')       => $i18n->trans($this->typeDate === 'create' ? 'creation-date' : 'accrual-date'),
                $i18n->trans('from-date')  => Tools::date($this->datefrom),
                $i18n->trans('until-date') => Tools::date($this->dateto),
            ]]
        );

        // Opciones de alineación para columnas numéricas
        $options = [];
        foreach ($rates as $rate) {
            $options['B' . $this->formatRate($rate)] = ['display' => 'right'];
        }
        foreach ($ratesWithTax as $rate) {
            $options['IVA ' . $this->formatRate($rate)] = ['display' => 'right'];
        }
        $options[$i18n->trans('total')] = ['display' => 'right'];

        // Tabla principal
        $headers = empty($lines) ? [] : array_keys(reset($lines));
        $exportManager->addTablePage($headers, $lines, $options);

        // Tabla de totales
        $headTotals = empty($totals) ? [] : array_keys(reset($totals));
        $exportManager->addTablePage($headTotals, $totals, $options);

        if (ob_get_length()) {
            ob_end_clean();
        }

        $exportManager->show($this->response);
    }
}
