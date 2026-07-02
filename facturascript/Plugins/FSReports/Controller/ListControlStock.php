<?php

namespace FacturaScripts\Plugins\FSReports\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;

use FacturaScripts\Dinamic\Lib\AssetManager;
use FacturaScripts\Core\Html;

/**
 * Description of ListControlStock
 *
 * @author Raúl   <raljopa@gmail.com>
 */
class ListControlStock extends Controller {

    public function __construct(string $className, string $uri = '') {
        parent::__construct($className, $uri);
        $code = $idempresa ?? Tools::settings('default', 'idempresa', '');
        $company = new \FacturaScripts\Dinamic\Model\Empresa();
        if ($company->loadFromCode($code)) {
            $companyName = $company->nombre;
        }
        $this->pdfParams = ['title' => 'list-control-stock',
            'orientation' => 'landscape',
            'cssFile' => 'ReportControlStock',
            'templateFile' => 'ReportControlStock',
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
        $pageData['submenu'] = '';
        $pageData['title'] = 'control-stock';
        $pageData['icon'] = 'fas fa-paperclip';

        return $pageData;
    }

    public function privateCore(&$response, $user, $permisions) {
        parent::privateCore($response, $user, $permisions);
        AssetManager::add('js', FS_ROUTE . '/Plugins/FSReports/node_modules/tabulator-tables/dist/js/tabulator.js');
        AssetManager::add('css', FS_ROUTE . '/Plugins/FSReports/node_modules/tabulator-tables/dist/css/tabulator_bootstrap5.css');

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
           
            case 'print-pdf':
                $this->exportToPDF();
                break;

            default:
                // parent::execAfterAction($action);
                break;
        }
    }

    private function renderHtml(string $template, string $controllerName = '') {
        $templateVars = [
            'appSettings' => [],
            'controllerName' => 'ListControlStock',
            'debugBarRender' => true,
            'fsc' => $this,
            'menuManager' => [],
            'template' => $template,
        ];
        $webRender = new Html();

        $html = $webRender->render($template, $templateVars);
        // $html = Html::render($template, $templateVars);
        return $html;
    }

    private function getData() {
        $tmpregistro = [];
        $sql = 'SELECT atributos.nombre,atributos_valores.valor, variantes.referencia,productos.descripcion,variantes.stockfis,'
                . 'stocks.reservada,stocks.pterecibir '
                . 'FROM `variantes` inner join productos inner join atributos_valores inner join atributos inner join stocks '
                . ' on variantes.idproducto=productos.idproducto and variantes.idatributovalor1=atributos_valores.id '
                . ' and atributos.codatributo=atributos_valores.codatributo and stocks.referencia=variantes.referencia; ';

        $db = new DataBase();
        $sinpedido = [
            'cantpedida' => 0
            , 'fecha' => ''
            , 'codigo' => ''
        ];
        $data = $db->select($sql);
        foreach ($data as $registro) {
            $sqlped = ' SELECT lineaspedidosprov.cantidad-lineaspedidosprov.servido as cantpedida,pedidosprov.fecha,pedidosprov.codigo '
                    . ' from lineaspedidosprov '
                    . ' inner join pedidosprov'
                    . ' on  lineaspedidosprov.idpedido=pedidosprov.idpedido'
                    . ' where lineaspedidosprov.referencia="' . $registro['referencia'] . '"'
                    . ' and pedidosprov.idestado in (select idestado from estados_documentos where tipodoc="PedidoProveedor" and editable=1) ';
            
            $dataped = $db->select($sqlped);
            // error_log("dataped es " . var_export($dataped, 1), 3, "./r");
            if (count($dataped) > 0) {
                $tmpregistro[] = array_merge($registro, $dataped[0]);
            } else {
                $tmpregistro[] = array_merge($registro, $sinpedido);
            }
            
        }
        $this->data = count($tmpregistro) ? $tmpregistro : [];
    }

    public function exportToPDF() {

        $this->getData();
        $basePath = './Plugins/FSReports/View';
        $html = $this->renderHtml($this->pdfParams['templateFile'] . '.html.twig');
        //echo $html;
        $mpdf = new \Mpdf\Mpdf(['tempDir' => './Plugins/FSReports/tempFiles', 'mode' => 'utf-8',
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

}
