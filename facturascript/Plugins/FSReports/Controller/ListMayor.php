<?php

namespace FacturaScripts\Plugins\FSReports\Controller;
use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use Mpdf\Mpdf;

use FacturaScripts\Dinamic\Lib\AssetManager;
use FacturaScripts\Core\Html;




/**
 * Description of ListMayor
 *
 * @author Raúl <raljopa@gmail.com>
 */
class ListMayor extends controller {

    public $dateFrom;
    public $dateTo;
    public $pdfParams;
    public $data;

    public function __construct(string $className, string $uri = '') {
        parent::__construct($className, $uri);

        $this->dateFromAndTo(date('Y'));
        $code = $idempresa ?? Tools::settings('default', 'idempresa', '');
        $company = new \FacturaScripts\Dinamic\Model\Empresa();
        if ($company->loadFromCode($code)) {
            $companyName = $company->nombre;
        }
        $this->filters = [
            'desdeFecha' => $this->dateFrom,
            'hastaFecha' => $this->dateTo,
            'saldoinicial' => 0,
            'desdeFechaMostrar' => date('d-m-Y', strtotime($this->dateFrom)),
            'hastaFechaMostrar' => date('d-m-Y', strtotime($this->dateTo))
        ];
        $this->pdfParams = ['title' => 'Listado de ventas por grupo',
            'orientation' => 'portrait',
            'cssFile' => 'ReportVentasGrupo',
            'templateFile' => 'ReportVentasGrupo',
            'headerFile' => '/Block/cabecera',
            'footerFile' => '/Block/piePagina',
            'controllerName' => static::class,
            'companyName' => $companyName,
            'detailLines' => 30
        ];
    }

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'reports';
        $pageData['submenu'] = 'contabilidad';
        $pageData['title'] = 'Mayor';
        $pageData['icon'] = 'fas fa-paperclip';

        return $pageData;
    }

    public function privateCore(&$response, $user, $permisions) {
        parent::privateCore($response, $user, $permisions);

        AssetManager::add('js', FS_ROUTE . '/Plugins/FSReports/node_modules/tabulator-tables/dist/js/tabulator.js');
        // 
        //
        AssetManager::add('css', FS_ROUTE . '/Plugins/FSReports/node_modules/tabulator-tables/dist/css/tabulator_bootstrap5.css');
        AssetManager::add('js', FS_ROUTE . '/node_modules/jquery-ui-dist/jquery-ui.js');
        AssetManager::add('css', FS_ROUTE . '/node_modules/jquery-ui-dist/jquery-ui.css');
        $this->getFilters();

        $action = $this->request->get('action', '');
        if ($action !== '') {
            $this->execAction($action);
        }
    }

    protected function execAction($action) {
        $this->tipoDesglose = $this->request->get('tipodesglose', '');
        switch ($action) {
            case 'get-data':

                $this->getData();
                $this->setTemplate(false);
                $this->response->setContent(json_encode($this->data, 1));
                break;
            case 'recalc':

                $this->getData();
                $this->setTemplate(false);
                $this->response->setContent(json_encode($this->data, 1));
                break;
            case 'print-pdf':
                $this->exportToPDF();
                break;
            case 'searchcuenta':
                $this->setTemplate(false);
                $result = $this->searchCuenta();
                $this->response->setContent(json_encode($result, 1));
                break;
            default:
                // parent::execAfterAction($action);
                break;
        }
    }

    public function dateFromAndTo($codejercicio) {


        $ejercicio = new \FacturaScripts\Core\Model\Ejercicio();
        $ejercicio->loadFromCode($codejercicio);

        $this->dateFrom = date('d-m-Y', strtotime($ejercicio->fechainicio));

        $this->dateTo = date('d-m-Y', strtotime($ejercicio->fechafin));

        return $this->dateFrom;
    }

    public function getFilters() {
        $dataBase = new \FacturaScripts\Core\Base\DataBase();
        

        $fecha1 = \DateTime::createFromFormat('d-m-Y', $this->request->get('desFec', $this->dateFrom));
        $fecha2 = \DateTime::createFromFormat('d-m-Y', $this->request->get('hasFec', $this->dateTo));
        $codgrupo = $this->request->get('codgrupo', '');

        $code = $idempresa ?? Tools::settings('default', 'idempresa', '');

        $filters = [
            'desdeFecha' => $fecha1->format('Y-m-d'),
            'hastaFecha' => $fecha2->format('Y-m-d'),
            'codsubcuenta' => $this->request->get('codsubcuenta', ''),
        ];
       

        $this->filtersReport = [
            'desdeFecha' => ['label' => 'Desde Fecha:', 'value' => $fecha1->format('d-m-Y')]
            , 'hastaFecha' => ['label' => 'Hasta Fecha', 'value' => $fecha2->format('d-m-Y')]
            , 'codsubcuenta' => ['label' => 'Cuenta:', 'value' => $this->request->get('codsubcuenta', '')]
        ];

        $this->filters = $filters;
        return $filters;
    }

    public function exportToPDF() {
        $this->tipoDesglose = $this->request->get('tipodesglose', '');
        $this->getData();
        $basePath = './Plugins/FSReports/View';
        $html = $this->renderHtml($this->pdfParams['templateFile'] . '.html.twig');
        //echo $html;
        $mpdf = new Mpdf(['tempDir' => './Plugins/FSReports/tempFiles', 'mode' => 'utf-8',
            'format' => 'A4-P', 'setAutoTopMargin' => 'stretch', 'setAutoBottomMargin' => 'stretch']);

        if (file_exists($basePath . '/CSS/' . $this->pdfParams['cssFile'] . '.css')) {

            $style = file_get_contents($basePath . '/CSS/' . $this->pdfParams['cssFile'] . '.css');
            $mpdf->WriteHTML($style, 1);
        } else {

            error_log(realpath('.'));
        }
        $htmlHeader = $this->renderHtml($this->pdfParams['headerFile'] . '.html.twig');

        $mpdf->SetHTMLHeader($htmlHeader);
        $htmlFooter = $this->renderHtml($this->pdfParams['footerFile'] . '.html.twig');
        $mpdf->SetHTMLFooter($htmlFooter);
        $mpdf->WriteHTML($html, 2);

        $mpdf->Output();
    }

    private function renderHtml(string $template, string $controllerName = '') {
        $templateVars = [
            'appSettings' => [],
            'controllerName' => 'ListFsReportMod347',
            'debugBarRender' => true,
            'fsc' => $this,
            'menuManager' => [],
            'template' => $template,
        ];
        $webRender = new Html();

        $html = $webRender->render($template, $templateVars);
        return $html;
    }

    private function getData() {
        $this->getFilters();
        $saldoInicial = $this->getSaldoInicial();
        $registro[] = ['fecha' => '', 'concepto' => 'SALDO ANTERIOR', 'debe' => '', 'haber' => '', 'saldo' => $saldoInicial];

        $sql = ' SELECT asientos.fecha,partidas.concepto, partidas.debe, partidas.haber, partidas.punteada '
                . ' FROM partidas inner join asientos on asientos.idasiento=partidas.idasiento '
                . ' where codsubcuenta="' . $this->filters['codsubcuenta'] . '"'
                . ' and asientos.fecha>="' . $this->filters['desdeFecha'] . '"'
                . ' and asientos.fecha<="' . $this->filters['hastaFecha'] . '"'
                . ' order by asientos.fecha,asientos.idasiento';
        
        $db = new DataBase();
        
        $data = $db->select($sql);
        $saldoActual = $saldoInicial;
        foreach ($data as $dato) {
            $saldoActual = $saldoActual + $dato['debe'] - $dato['haber'];
            $registro[] = [
                'fecha' => \DateTime::createFromFormat('Y-m-d', $dato['fecha'])->format('d/m/Y')
                , 'concepto' => $dato['concepto']
                , 'debe' => $dato['debe']
                , 'haber' => $dato['haber']
                , 'saldo' => $saldoActual
                , 'punteada' => $dato['punteada'] ? true : false
            ];
        }

        $this->data = $registro;
    }
    private function getSaldoInicial() {
        $ejercicio = new \FacturaScripts\Dinamic\Model\Ejercicio();
        $result = $ejercicio->loadFromDate($this->filters['desdeFecha'], false, false);
       
       
            $desFec = \DateTime::createFromFormat('d-m-Y', $ejercicio->fechainicio);

        $sql = 'select sum(debe-haber) saldoinicial from partidas '
                . ' inner join asientos '
                . ' on asientos.idasiento=partidas.idasiento'
                . ' where codsubcuenta="' . $this->filters['codsubcuenta'] . '"'
        . ' and asientos.fecha>="' . $desFec->format('Y-m-d') . '"'
                . ' and asientos.fecha<"' . $this->filters['desdeFecha'] . '"';
        $db = new DataBase();
        error_log($sql . "\r\n", 3, "./r");
        $data = $db->select($sql);

        return $data ? $data[0]['saldoinicial'] : 0;
    }

    private function searchCuenta() {
        $registro = [];
        $term = $this->request->get('search', 1);
        $whereCuenta = [
            new DataBaseWhere('codsubcuenta', $term . '%', 'LIKE'),
            new DataBaseWhere('descripcion', '%' . $term . '%', 'XLIKE', 'OR')
        ];
        $cuentas = new \FacturaScripts\Dinamic\Model\Subcuenta();
        $cuentas = $cuentas->all($whereCuenta);
        foreach ($cuentas as $cuenta) {
            $registro[] = ['value' => $cuenta->codsubcuenta, 'label' => $cuenta->descripcion . '-' . $cuenta->codsubcuenta . ' (' . $cuenta->codejercicio . ')'];
        }
        return $registro;
    }

}
