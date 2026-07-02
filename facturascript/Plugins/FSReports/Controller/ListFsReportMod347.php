<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 *
 * @author Raúl Jiménez <raljopa@gmail.com>
 */
namespace FacturaScripts\Plugins\FSReports\Controller;

use FacturaScripts\Dinamic\Model;

use FacturaScripts\Core\Tools;
use Mpdf\Mpdf;

use FacturaScripts\Core\Html;


/**
 * Description of ListFsReportMod347
 *
 * @author Raul
 */
class ListFsReportMod347 extends \FacturaScripts\Core\Base\Controller {

    public $dateFrom;
    public $dateTo;
    public $filters;
    public $pdfParams;

    public function __construct(string $className, string $uri = '')
    {
        parent::__construct($className, $uri);

        $this->dateFromAndTo(date('Y'));
        $code = $idempresa ?? Tools::settings('default', 'idempresa', '');
        $company = new Model\Empresa();
        if ($company->loadFromCode($code)) {
            $companyName = $company->nombre;
        }
        $this->filters = [
            'desdeFecha' => $this->dateFrom,
            'hastaFecha' => $this->dateTo,
            'cantidad' => 3005,
            'desdeFechaMostrar' => date('d-m-Y', strtotime($this->dateFrom)),
            'hastaFechaMostrar' => date('d/m-Y', strtotime($this->dateTo))
        ];

        $this->pdfParams = ['title' => 'Listado de operaciones con terceros. Modelo 347',
            'orientation' => 'portrait',
            'cssFile' => 'ReportModelo347',
            'templateFile' => 'ReportMod347',
            'headerFile' => '/Block/cabeceraModelo347',
            'footerFile' => '/Block/piePaginaModelo347',
            'controllerName' => static::class,
            'companyName' => $companyName,
            'detailLines' => 30
        ];
    }

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'reports';
        $pageData['submenu'] = 'fiscal';
        $pageData['title'] = 'Modelo 347';
        $pageData['icon'] = 'fas fa-paperclip';

        return $pageData;
    }

    public function privateCore(&$response, $user, $permisions)
    {
        parent::privateCore($response, $user, $permisions);
        $this->salesResume();
        $action = $this->request->get('action', '');
        if ($action !== '') {
            $this->execAction($action);
        }
    }

    protected function execAction($action)
    {

        switch ($action) {
            case 'reload':
                $this->getFilters();
                break;
            case 'print-pdf':
                $this->exportToPDF();
                break;
            default:
                // parent::execAfterAction($action);
                break;
        }
    }

    public function getExercises()
    {
        $datos = [];
        $ejercicio = new \FacturaScripts\Core\Model\Ejercicio();
        $ejercicios = $ejercicio->all();

        foreach ($ejercicios as $dato) {
            $datos[$dato->codejercicio] = $dato->codejercicio;
        }

        return $datos;
    }

    public function dateFromAndTo($codejercicio)
    {
        $ejercicio = new \FacturaScripts\Core\Model\Ejercicio();
        $ejercicio->loadFromCode($codejercicio);

        $this->dateFrom = date('d-m-Y', strtotime($ejercicio->fechainicio));

        $this->dateTo = date('d-m-Y', strtotime($ejercicio->fechafin));

        return $this->dateFrom;
    }

    public function salesResume()
    {
        $dataBase = new \FacturaScripts\Core\Base\DataBase();

        $sql = 'select '
            . ' nombrecliente,cifnif,codpostal,'
            . ' sum(case when quarter(fecha) = 1 then (totaleuros  + totalirpf) end) AS T1'
            . ', sum(case when quarter(fecha) = 2 then (totaleuros  + totalirpf) end) AS T2'
            . ', sum(case when quarter(fecha) = 3 then (totaleuros  + totalirpf) end) AS T3'
            . ', sum(case when quarter(fecha) = 4 then (totaleuros  + totalirpf) end) AS T4'
            . ', sum(totaleuros + totalirpf) as total  from facturascli ';
        $filters = $this->getFilters();
        $sqlWhere = ' where fecha BETWEEN "' . date('Y-m-d', strtotime($filters['desdeFecha']))
            . '" and "' . date('Y-m-d', strtotime($filters['hastaFecha'])) . '"'
            . ' and idempresa= ' . $filters['idempresa'];
        $sqlGroup = ' group by nombrecliente,cifnif,codpostal';
        $sqlHaving = ' having total>="' . $filters['cantidad'] . '"';

        $data = $dataBase->selectLimit($sql . $sqlWhere . $sqlGroup . $sqlHaving);

        return $data;
    }

    public function purchasesResume()
    {
        $dataBase = new \FacturaScripts\Core\Base\DataBase();
        $where = [];
        $sql = 'select '
            . ' nombre,cifnif,'
            . ' sum(case when quarter(fecha) = 1 then (totaleuros  + totalirpf) end) AS T1'
            . ', sum(case when quarter(fecha) = 2 then (totaleuros  + totalirpf) end) AS T2'
            . ', sum(case when quarter(fecha) = 3 then (totaleuros  + totalirpf) end) AS T3'
            . ', sum(case when quarter(fecha) = 4 then (totaleuros  + totalirpf) end) AS T4'
            . ', sum(totaleuros + totalirpf) as total  from facturasprov ';
        $filters = $this->getFilters();
        $sqlWhere = ' where fecha BETWEEN "' . date('Y-m-d', strtotime($filters['desdeFecha']))
            . '" and "' . date('Y-m-d', strtotime($filters['hastaFecha'])) . '"'
            . ' and idempresa= ' . $filters['idempresa'];
        $sqlGroup = ' group by nombre,cifnif';
        $sqlHaving = ' having total>="' . $filters['cantidad'] . '"';
        $data = $dataBase->selectLimit($sql . $sqlWhere . $sqlGroup . $sqlHaving);
        return $data;
    }

    public function getFilters()
    {
        $dataBase = new \FacturaScripts\Core\Base\DataBase();

        $fecha1 = \DateTime::createFromFormat('d-m-Y', $this->request->get('date-from', $this->dateFrom));
        $fecha2 = \DateTime::createFromFormat('d-m-Y', $this->request->get('date-to', $this->dateTo));


        $code = $idempresa ?? Tools::settings('default', 'idempresa', '');

        $filters = ['desdeFecha' => $fecha1->format('Y-m-d'),
            'hastaFecha' => $fecha2->format('Y-m-d'),
            'cantidad' => $this->request->get('cantidad', '3005'),
            'idempresa' => $code,
            'desdeFechaMostrar' => $this->request->get('date-from', $this->dateFrom),
            'hastaFechaMostrar' => $this->request->get('date-to', $this->dateTo)
        ];

        $this->filters = $filters;
        return $filters;
    }

    public function exportToPDF()
    {

        $basePath = './Plugins/FSReports/View';
        $html = $this->renderHtml($this->pdfParams['templateFile'] . '.html.twig');
        //echo $html;
        $mpdf = new Mpdf(['tempDir' => './Plugins/FSReports/tempFiles', 'mode' => 'utf-8',
            'format' => 'A4-P', 'setAutoTopMargin' => 'stretch', 'setAutoBottomMargin' => 'stretch']);

        if (file_exists($basePath . '/CSS/' . $this->pdfParams['cssFile'] . '.css')) {

            $style = file_get_contents($basePath . '/CSS/' . $this->pdfParams['cssFile'] . '.css');
            $mpdf->WriteHTML($style, 1);
        } else {
            error_log('No encuentro el css ' . $basePath . '/CSS/' . $this->pdfParams['cssFile'] . '.css');
            error_log(realpath('.'));
        }
        $htmlHeader = $this->renderHtml($this->pdfParams['headerFile'] . '.html.twig');

        $mpdf->SetHTMLHeader($htmlHeader);
        $htmlFooter = $this->renderHtml($this->pdfParams['footerFile'] . '.html.twig');
        $mpdf->SetHTMLFooter($htmlFooter);
        $mpdf->WriteHTML($html, 2);

        $mpdf->Output();
    }

    private function renderHtml(string $template, string $controllerName = '')
    {
        $templateVars = [
            'appSettings' => [],
            'controllerName' => 'ListFsReportMod347',
            'debugBarRender' => true,
            'fsc' => $this,
            'menuManager' => '',
            'template' => $template,
        ];
        $webRender = new Html();

        $html = $webRender->render($template, $templateVars);
        return $html;
    }
}
