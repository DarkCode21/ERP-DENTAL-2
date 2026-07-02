<?php
namespace FacturaScripts\Plugins\FSReports\Lib;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of BaseReport
 * Clase base para la generación de informes a partir de plantillas twig
 *
 * @author Raúl Jiménez <raljopa@gmail.com>
 */
/* require_once __DIR__ . '/../vendor/autoload.php';
  require_once './vendor/autoload.php'; */


//use Mpdf;
use FacturaScripts\Core\Lib\ExtendedController;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;


class BaseReport {

    public $params;
    public $settings;
    public $controllerName;
    public $menuManager;
    public $fsc;

    public function __construct($data) {
        if (!empty($data)) {
            $this->params = $data;
        } else {
            $data = [];
        }
    }

    public function generatePDF($html, $header = '', $footer = '') {
        ini_set('max_execution_time', '500');
        ini_set("pcre.backtrack_limit", "80000000");
        $basePath = './Plugins/FSReports/View';
        $mpdf = new \Mpdf\Mpdf(['tempDir' => './Plugins/FSReports/tempFiles', 'mode' => 'utf-8',
            'format' => 'A4-L', 'setAutoTopMargin' => 'stretch', 'setAutoBottomMargin' => 'stretch']);

        if (file_exists($basePath . '/CSS/' . $this->params['cssFile'] . '.css')) {

            $style = file_get_contents($basePath . '/CSS/' . $this->params['cssFile'] . '.css');
        }

        // $mpdf['collapseBlockMarigns'] = True;
        /* if ($header !== '') {
          $mpdf->SetHTMLHeader($header);
          }
          if ($footer !== '') {

          $mpdf->SetHTMLFooter($footer);
          } */

        $mpdf->WriteHTML($style, 1);
        $mpdf->WriteHTML($html, 2);

        $mpdf->Output();
    }

}
