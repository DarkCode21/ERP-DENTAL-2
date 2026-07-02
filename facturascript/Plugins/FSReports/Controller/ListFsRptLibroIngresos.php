<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace FacturaScripts\Plugins\FSReports\Controller;

require_once 'Plugins/FSReports/Lib/BaseReport.php';
require_once 'Plugins/FSReports/Lib/BaseLibrosFacturas.php';

use FacturaScripts\Core\App\AppSettings;

use FacturaScripts\Plugins\FSReports\Lib\BaseLibrosFacturas;
use FacturaScripts\Plugins\FSReports\Lib\BaseReport;
use FacturaScripts\Core\Model;

use FacturaScripts\Core\Html;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Controller\ListFacturaCliente as LFC;

/**
 * Description of ListFsReportLibroIngresos
 *
 * @author Raul
 */
class ListFsRptLibroIngresos extends LFC {

    public $params;
    public $filters;

    /**
     * Número total de líneas que vienen en el desglose de tipos de IVA
     * @var integer
     */
    public $detailTotalLines;

    /**
     * Numero total de líneas para el informe procedentes de la cabecera
     * @var integer
     */
    public $headerTotalLines;

    /**
     * Total líneas del informe
     * @var integer
     */
    public $numPages;

    /**
     * Total de líneas que ocupa el resúmen final
     * @var integer
     */
    public $numLineasResumen;

    /**
     *
     * @var BaseLibrosFacturas
     */
    public $baseLibro;

    /**
     * Array con la lista de documentos
     * @var array
     */
    public $listDocument;

    /**
     * Array con el resumen del final del documento
     * @var array
     */
    public $resumenListado;
    public $detailLines;

    public function __construct(string $className, string $uri = '') {
        parent::__construct($className, $uri = '');
        $this->baseLibro = new BaseLibrosFacturas('facturascli', 'lineasfacturascli');
        $code = $idempresa ?? Tools::settings('default', 'idempresa', '');
        $company = new Model\Empresa();
        if ($company->loadFromCode($code)) {
            $companyName = $company->nombre;
        }
        $this->params = ['title' => 'Libro de facturas de ingresos',
            'orientation' => 'portrait',
            'cssFile' => 'ReportLibroFacturas',
            'templateFile' => 'ReportLibroFac',
            'headerFile' => '/Block/cabeceraPagina',
            'footerFile' => '/Block/piePagina',
            'controllerName' => static::class,
            'companyName' => $companyName,
            'detailLines' => 30
        ];
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'invoices';
        $pagedata['icon'] = 'fas fa-copy';
        $pagedata['menu'] = 'reports';
        $pagedata['submenu'] = 'sales';
        return $pagedata;
    }

     protected function createViews() {
        // listado de facturas de cliente
        $this->createViewSales('ListFsRptLibroIngresos', 'FacturaCliente', 'invoices');
    }

    

    public function execAfterAction($action) {
        switch ($action) {
            case 'print-pdf':
                $basePath = FS_FOLDER . '/Dinamic/View';
                foreach ($this->views as $viewName => $view) {
                    if ($this->active == $viewName) {
                        $this->baseLibro->view = $view;
                    }
                }
                $this->calculateFilters();
                $this->listHeaderDoc();
                $this->getDetailLines();
                $this->resumen();
                $this->countPrintLinesHeaderDoc();
                $this->countPrintLinesDetailDoc();
                $this->countNumLineasResumen();
                $this->numPages = ( $this->headerTotalLines + $this->detailTotalLines + $this->numLineasResumen) / (int) $this->params['detailLines'];
               // $html = $this->renderHtml($this->params['templateFile'] . '.html.twig');
                $tmpNumPages = floor((float) $this->numPages);

                if ($tmpNumPages < $this->numPages) {

                    $this->numPages = $tmpNumPages + 1;
                } else {
                    $this->numPages = round($this->numPages, 0);
                }

                // $htmlHeader = $this->renderHtml($this->params['headerFile'] . '.html.twig');
                $htmlHeader = '';
                $html = $this->renderHtml($this->params['templateFile'] . '.html.twig');
                // $htmlFooter = $this->renderHtml($this->params['footerFile'] . '.html.twig');
                $htmlFooter = '';
                // error_log($html);
                $document = new BaseReport($this->params);
                ini_set('max_execution_time', '300');
                ini_set("pcre.backtrack_limit", "5000000");
                $document->generatePDF($html, $htmlHeader, $htmlFooter);
                break;
            case 'export-report':
                $this->exportReport();
                break;
            case 'print-screen':
                $this->calculateFilters();
                $this->generateReport();
                break;
            default:
                parent::execAfterAction($action);
                break;
        }
    }
    public function exportReport() {
        $data = [];
        $this->calculateFilters();
        
        $this->listDocument = $this->baseLibro->getExportData();
        
        $titles = [
            'codigo', 'fecha', 'cifnif', 'razonsocial', 'base', 'poriva', 'totiva', 'porirpf', 'totirpf', 'total'
        ];
        foreach ($this->listDocument as $row) {

            $data[] = [
                $row['codigo']
            , \DateTime::createFromFormat('Y-m-d', $row['fecha'])->format('d/m/Y')
                , $row['cifnif']
                , $row['razonsocial']
                , $row['base']
                , $row['poriva']
                , $row['totiva']
                , $row['pirpf']
                , $row['totirpf']
                , $row['total']
            ];
        }
        $path = './MyFiles/';
        $this->template = false;
           
        $fp = fopen($path . 'documentos.csv', 'w');
            fputcsv($fp, $titles, ';');
            foreach ($data as $fields) {
            fputcsv($fp, $fields, ';');
            }
            fclose($fp);

            $this->setTemplate(false);
            $this->response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $this->response->headers->set('Content-Disposition', 'attachment;filename=documentos.csv');
            header("Content-Transfer-Encoding: binary");

            // read the file from disk
            $this->response->setContent(readfile($path . 'documentos.csv'));
        
       
    }

    public function getWhere() {
        return $this->baseLibro->getWhere();
    }

    public function calculateFilters() {
        $this->filters = $this->baseLibro->calculateFilters();

        return $this->filters;
    }

    public function listHeaderDoc() {

        $this->listDocument = $this->baseLibro->listHeaderdoc();

        return $this->listDocument;
    }

    public function getDetailLines() {
        $this->detailLines = $this->baseLibro->getDetailLines();
        return $this->detailLines;
    }

    private function countPrintLinesHeaderDoc() {
        $this->headerTotalLines = count($this->listDocument) * 2;

        return $this->headerTotalLines;
    }

    private function countPrintLinesDetailDoc() {
        $this->detailTotalLines = $this->baseLibro->countPrintLinesDetailDoc();
        return $this->detailTotalLines;
    }

    public function listLinesDoc($idfactura) {
        return $this->baseLibro->listLinesDoc($idfactura);
    }

    public function generateReport() {
        $this->setTemplate($this->params['templateFile']);
    }

    private function renderHtml(string $template, string $controllerName = '') {
        $templateVars = [
            'controllerName' => 'ListFsRptLibroIngresos',
            'debugBarRender' => true,
            'fsc' => $this,
            'template' => $template,
        ];
        $webRender = new Html();

        $html = $webRender->render($template, $templateVars);

        return $html;
    }

    public function resumen() {
        $this->resumenListado = $this->baseLibro->resumen();
        return $this->resumenListado;
    }

    public function countNumLineasResumen() {
        $this->numLineasResumen = $this->baseLibro->countNumLineasResumen();
        return $this->numLineasResumen;
    }

}
