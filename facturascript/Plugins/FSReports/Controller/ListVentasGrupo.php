<?php

namespace FacturaScripts\Plugins\FSReports\Controller;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;
use Mpdf\Mpdf;
use FacturaScripts\Dinamic\Lib\AssetManager;


//require_once './vendor/autoload.php';

//require_once FS_FOLDER . '/Plugins/extlibraries/vendor/autoload.php';

/**
 * Description of ListVentasGrupo
 *
 * @author Ra�l
 */
class ListVentasGrupo extends \FacturaScripts\Core\Base\Controller {

    public $dateFrom;
    public $dateTo;
    public $filters;
    public $filtersReport;
    public $pdfParams;
    public $params;
    public $gruposCliente;
    public $agentes;
    public $data;
    public $tipoDesglose;

    public function __construct(string $className, string $uri = '') {
        parent::__construct($className, $uri);
        
        $this->dateFromAndTo(date('Y'));
        $this->getGruposCliente();
        $this->getAgentes();
         
         $this->getData();
        $code = $idempresa ?? Tools::settings('default', 'idempresa', '');
        $company = new \FacturaScripts\Dinamic\Model\Empresa();
        if ($company->loadFromCode($code)) {
            $companyName = $company->nombre;
        }
        $this->filters = [
            'desdeFecha' => $this->dateFrom,
            'hastaFecha' => $this->dateTo,
            'cantidad' => 3005,
            'desdeFechaMostrar' => date('d-m-Y', strtotime($this->dateFrom)),
            'hastaFechaMostrar' => date('d-m-Y', strtotime($this->dateTo))
        ];

        $this->pdfParams = ['title' => 'Listado de ventas por grupo',
            'company' => 'Bathstage',
            'date' => date('Y-m-d'),
            'orientation' => 'portrait',
            'cssFile' => 'ReportVentasGrupo',
            'templateFile' => 'ReportVentasGrupo',
            'headerFile' => '/Block/cabecera',
            'footerFile' => '/Block/piePagina',
            'controllerName' => static::class,
            'companyName' => $companyName,
            'detailLines' => 30
        ];
        $this->params = $this->pdfParams;
    }

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'reports';
        $pageData['submenu'] = 'sales';
        $pageData['title'] = 'ventas-grupo';
        $pageData['icon'] = 'fas fa-paperclip';

        return $pageData;
    }
    public function privateCore(&$response, $user, $permisions) {
        parent::privateCore($response, $user, $permisions);
       
        AssetManager::add('js', FS_ROUTE . '/Plugins/FSReports/node_modules/tabulator-tables/dist/js/tabulator.js');
        //
        AssetManager::add('css', FS_ROUTE . '/Plugins/FSReports/node_modules/tabulator-tables/dist/css/tabulator_bootstrap5.css');
        $this->getFilters();
        
        $action = $this->request->get('action', '');
        if ($action !== '') {
            $this->execAction($action);
        }
    }

    protected function execAction($action) {
        $this->tipoDesglose = $this->request->get('tipodesglose', '');
        switch ($action) {
            case 'get-datos-venta':
                $this->tipoDesglose = 'd';
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
                $this->getFilters();
                $this->getData();
                $this->exportToPDF();
                break;

            default:
                // parent::execAfterAction($action);
                break;
        }
    }
    public function dateFromAndTo($codejercicio) {


        $ejercicio = new \FacturaScripts\Core\Model\Ejercicio();
        $ejercicio->loadFromCode($codejercicio);

        $this->dateFrom = date('Y-m-d', strtotime($ejercicio->fechainicio));

        $this->dateTo = date('Y-m-d', strtotime($ejercicio->fechafin));

        return $this->dateFrom;
    }

    public function getFilters() {
        $dataBase = new \FacturaScripts\Core\Base\DataBase();
        $grupo = new \FacturaScripts\Dinamic\Model\grupoClientes();
        $agente = new \FacturaScripts\Dinamic\Model\Agente();

        $fecha1 = \DateTime::createFromFormat('Y-m-d', $this->request->get('desFec', $this->dateFrom));
        $fecha2 = \DateTime::createFromFormat('Y-m-d', $this->request->get('hasFec', $this->dateTo));
        
        if (!$fecha1 || !$fecha2) {
            $this->dateFromAndTo(date('Y'));
            $filters = [
                'desdeFecha' => $this->dateFrom,
                'hastaFecha' => $this->dateTo,
                'codgrupo' => 0,
                'codagente' => $this->request->get('codagente', '')
            ];
            $this->filters = $filters;
            return;
        }
        $codgrupo = $this->request->get('codgrupo', '');

        $code = $idempresa ?? Tools::settings('default', 'idempresa', '');

        $filters = [
            'desdeFecha' => $fecha1->format('Y-m-d'),
            'hastaFecha' => $fecha2->format('Y-m-d'),
            'codgrupo' => $codgrupo,
            'codagente' => $this->request->get('codagente', '')
        ];
        $grupo->loadFromCode($filters['codgrupo']);
        $agente->loadFromCode($filters['codagente']);

        $this->filtersReport = [
            'desdeFecha' => ['label' => 'Desde Fecha:', 'value' => $fecha1->format('d-m-Y')]
            , 'hastaFecha' => ['label' => 'Hasta Fecha', 'value' => $fecha2->format('d-m-Y')]
            , 'codgrupo' => ['label' => 'Grupo:', 'value' => $grupo->nombre]
            , 'codagente' => ['label' => 'Vendedor:', 'value' => $agente->nombre]
        ];

        $this->filters = $filters;
        return $filters;
    }

    public function exportToPDF() {
        $this->tipoDesglose = $this->request->get('tipodesglose', '');
        $this->getData();
        $basePath = './Plugins/FSReports/View';
        $html = $this->renderHtml($this->pdfParams['templateFile'] . '.html.twig');
        
        ini_set("memory_limit", "2560M");
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
        $webRender = new \FacturaScripts\Core\Html();
      
        $html = $webRender->render($template, $templateVars);
        return $html;
    }
    private function getGruposCliente() {
        $grupos = new \FacturaScripts\Dinamic\Model\GrupoClientes();
        $grupos = $grupos->all([], ['nombre' => 'asc'], 0, 0);
        $this->gruposCliente[0] = '---------------';
        foreach ($grupos as $grupo) {
            $this->gruposCliente[$grupo->codgrupo] = $grupo->nombre;
        }
    }

    private function getAgentes() {
        $agentes = new \FacturaScripts\Dinamic\Model\Agente();
        $agentes = $agentes->all([], ['nombre' => 'asc'], 0, 0);
        $this->agentes['0'] = '----------------';
        foreach ($agentes as $agente) {
            $this->agentes[$agente->codagente] = $agente->nombre;
        }
    }
    

    private function getData() {
        $this->getFilters();
        $this->tipoDesglose = $this->request->get('tipodesglose', '');
        if ($this->tipoDesglose === 'sp') {
            return $this->getVentasSerieProducto();
        }
        if ($this->tipoDesglose == 'r') {
            $sql = 'SELECT concat(gruposclientes.codgrupo,"-",gruposclientes.nombre) grupo, sum(lineasfacturascli.pvptotal*(1-facturascli.dtopor1/100)) importe'
                . ',sum(cantidad) unid,avg(pvpunitario) pvmedio, MAX(fecha) as ultimafecha '
                . ' FROM `lineasfacturascli` inner join facturascli inner join clientes inner join gruposclientes'
                . ' ON lineasfacturascli.idfactura=facturascli.idfactura AND facturascli.codcliente=clientes.codcliente'
                . ' AND clientes.codgrupo=gruposclientes.codgrupo';
                 
        $sql .= ' where facturascli.fecha>="' . $this->filters['desdeFecha'] . '"'
                . ' and facturascli.fecha<="' . $this->filters['hastaFecha'] . '"';
        if ($this->filters['codagente'] !== '0' & $this->filters['codagente'] !== '') {
                $sql .= ' and facturascli.codagente="' . $this->filters['codagente'] . '"';
        }
        if ($this->filters['codgrupo'] !== '0') {
            $sql .= ' and clientes.codgrupo="' . $this->filters['codgrupo'] . '"';
        }
        $sql .= ' group by gruposclientes.codgrupo,gruposclientes.nombre';
        $sql .= '  order by importe desc;';
        } else {
            $sql = 'SELECT concat(gruposclientes.codgrupo,"-",gruposclientes.nombre) grupo,concat(clientes.codcliente,"-",clientes.nombre) as nombrecliente, ROUND(sum(pvptotal-(pvptotal-(pvptotal *(1-(facturascli.dtopor1/100))))),2) importe'
                    . ',ROUND(sum(cantidad),2) unid,ROUND(avg(pvpunitario),2) pvmedio, MAX(fecha) as ultimafecha '
                    . ' FROM `lineasfacturascli` inner join facturascli inner join clientes inner join gruposclientes'
                    . ' ON lineasfacturascli.idfactura=facturascli.idfactura AND facturascli.codcliente=clientes.codcliente'
                    . ' AND clientes.codgrupo=gruposclientes.codgrupo';

            $sql .= ' where facturascli.fecha>="' . $this->filters['desdeFecha'] . '"'
                    . ' and facturascli.fecha<="' . $this->filters['hastaFecha'] . '"';
            if ($this->filters['codagente'] !== '0' && $this->filters['codagente'] != '') {
                $sql .= ' and facturascli.codagente="' . $this->filters['codagente'] . '"';
            }
            if ($this->filters['codgrupo'] !== '0' && $this->filters['codgrupo'] != '') {
                $sql .= ' and clientes.codgrupo="' . $this->filters['codgrupo'] . '"';
            }
            $sql .= ' group by clientes.nombre';
            $sql .= '  order by grupo ASC, importe desc;';
        }
        $db = new DataBase();
       
        $data = $db->select($sql);

        $this->data = $data ? $data : [];
    }
    private function getVentasSerieProducto() {
        $sql = 'select atributos_valores.valor serie,variantes.referencia,productos.descripcion,sum(lineasfacturascli.cantidad) '
                . 'cuantos,sum(lineasfacturascli.pvptotal*(1-facturascli.dtopor1/100)) simporte,gruposclientes.nombre grupocliente '
                . 'FROM lineasfacturascli inner join facturascli inner JOIN clientes '
                . 'inner join gruposclientes inner JOIN atributos_valores inner join variantes inner join productos '
                . 'ON lineasfacturascli.idfactura=facturascli.idfactura AND facturascli.codcliente=clientes.codcliente '
                . 'and clientes.codgrupo = gruposclientes.codgrupo AND lineasfacturascli.referencia=variantes.referencia'
                . ' AND variantes.idatributovalor1 = atributos_valores.id and lineasfacturascli.referencia=variantes.referencia '
                . 'and variantes.idproducto=productos.idproducto ';
        $sql .= ' where facturascli.fecha>="' . $this->filters['desdeFecha'] . '"'
                . ' and facturascli.fecha<="' . $this->filters['hastaFecha'] . '"';
        if ($this->filters['codgrupo'] !== '0' && $this->filters['codgrupo'] != '') {
            $sql .= ' and clientes.codgrupo="' . $this->filters['codgrupo'] . '"';
        }

        $sql .= 'group by atributos_valores.valor,variantes.referencia,productos.descripcion;';
        $db = new DataBase();

        $data = $db->select($sql);

        $this->data = $data ? $data : [];
    }

}
