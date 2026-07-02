<?php
namespace FacturaScripts\Plugins\FSReports\Lib;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Clase para unificar funciones para generar los listados de facturas
 *
 * @author Raul
 */
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\App\AppSettings;

class BaseLibrosFacturas {

    /**
     * Tabla de donde coger los datos para cabecera de datos
     * @var string
     */
    private $headerTable;

    /**
     * Tabla de donde coger las líneas de detalle.
     * @var string
     */
    private $detailTable;
    public $view;

    public function __construct($headerTable, $detailTable) {
        $this->headerTable = $headerTable;
        $this->detailTable = $detailTable;
    }

    public function calculateFilters() {
        if (!is_null($this->view)) {
            $montarfiltro = $this->getFilters($this->view->where);
        } else {
            $montarfiltro = [];
        }
        return $montarfiltro;
    }

    public static function getFilters($whereItems): array {

        //$whereItems = $this->view->where;
        $filtro = [];
        foreach ($whereItems as $whereItem) {

            $unWhere = (array) $whereItem;
            $campo = '';
            $operador = '';
            $valor = '';
            foreach ($unWhere as $key => $value) {
                if (strpos($key, 'fields') > 0) {
                    $campo = $value;
                }
                if (strpos($key, 'operator') > 0) {
                    $operador = $value;
                }
                if (strpos($key, 'value') > 0) {
                    $valor = $value;
                }
                if ($campo !== '' && $operador !== '' && $valor !== '') {
                    $filtro[] = ['campo' => $campo, 'operador' => $operador, 'value' => $valor];
                    $campo = '';
                    $operador = '';
                    $valor = '';
                }
            }
        }

        return $filtro;
    }

    public function listHeaderDoc() {
        $dataBase = new \FacturaScripts\Core\Base\DataBase();
        $where = [];
        if ($this->headerTable === 'facturascli' ) {
            $sql = 'Select * from ' . $this->headerTable;
        } else {
            $sql = 'Select *,nombre as nombrecliente from ' . $this->headerTable;
        }
        $sqlWhere = $this->getWhere();
        $codeCompany = $idempresa ?? AppSettings::get('default', 'idempresa', '');
       /* if ($sqlWhere !== '') {
          $sqlWhere .= ' and idempresa=' . $codeCompany;
          } else {
          $sqlWhere = ' where idempresa=' . $codeCompany;
          } */
        $sql .= $sqlWhere . ' order by codigo,fecha,idfactura';
       
        $data = $dataBase->select($sql);

        $this->results = $data;
        return $this->results;
    }

    public function getDetailLines() {
        $dataBase = new \FacturaScripts\Core\Base\DataBase();
        $where = [];
        $sql = 'Select * from ' . $this->detailTable
                . ' inner join ' . $this->headerTable
                . ' on ' . $this->detailTable . '.idfactura=' . $this->headerTable . '.idfactura';
        $sql = 'SELECT ' . $this->detailTable . '.idfactura,iva,' . $this->detailTable . '.irpf,recargo, sum(pvptotal) as base, '
                . ' sum(pvptotal)*(iva/100) as importe_iva,sum(pvptotal)*(' . $this->detailTable . '.irpf/100) as importe_irpf '
                . ',sum(pvptotal)*(recargo/100) as importe_recargo FROM ' . $this->detailTable
                . ' inner join ' . $this->headerTable
                . ' on ' . $this->detailTable . '.idfactura=' . $this->headerTable . '.idfactura';

        $sqlWhere = $this->getWhere();
        $codeCompany = $idempresa ?? AppSettings::get('default', 'idempresa', '');
        /* if ($sqlWhere !== '') {
          $sqlWhere .= ' and idempresa=' . $codeCompany;
          } else {
          $sqlWhere = ' where idempresa=' . $codeCompany;
          } */
        $sqlgroup = ' group by idfactura,iva,' . $this->detailTable . '.irpf,recargo; ';
        
        $data = $dataBase->select($sql . $sqlWhere . $sqlgroup);

        $this->results = $data;
        return $this->results;
    }

    public function getWhere() {
        if (is_null($this->view)) {
            return '';
        }
        
        $sqlWhere = DataBaseWhere::getSQLWhere($this->view->where);
        if (is_null($sqlWhere)) {
            error_log("el where es nulo", 3, "./r");
        }
        return $sqlWhere;
    }

    public function countPrintLinesHeaderDoc() {
        $dataBase = new \FacturaScripts\Core\Base\DataBase();
        $where = [];
        $sql = 'Select count(idfactura) as cuantas from ' . $this->headerTable;
        $sqlWhere = $this->getWhere();
        if ($sqlWhere !== '') {
            $codeCompany = $idempresa ?? AppSettings::get('default', 'idempresa', '');
            $sqlWhere .= ' and idempresa=' . $codeCompany;
            $sql .= $sqlWhere;
        }
        $data = $dataBase->selectLimit($sql);

        $this->results = $data[0]['cuantas'];

        $this->headerTotalLines = (int) $this->results * 2;

        return $this->results;
    }

    public function countPrintLinesDetailDoc() {
        $dataBase = new \FacturaScripts\Core\Base\DataBase();
        $where = [];
        $sql = 'SELECT ' . $this->headerTable . '.idfactura,iva,' . $this->detailTable . '.irpf,recargo '
                . ' FROM `' . $this->headerTable . '`,' . $this->detailTable . ' ';
        $where = $this->getWhere();
        if ($where !== '') {
            $where .= ' and ';
        } else {
            $where = ' where ';
        }
        $where .= $this->headerTable . '.idfactura=' . $this->detailTable . '.idfactura ';
        $groupby = ' group by ' . $this->detailTable . '.idfactura,iva,' . $this->detailTable . '.irpf,recargo;';

        $data = $dataBase->select($sql . $where . $groupby);
        return (int) count($data);
    }

    public function listLinesDoc($idfactura) {
        $dataBase = new \FacturaScripts\Core\Base\DataBase();
        $data = [];
        $where = [];
        $sql = 'SELECT idfactura,iva,irpf,recargo, sum(pvptotal) as base, '
        . ' sum(pvptotal)*(iva/100) as importe_iva,sum(pvptotal)*(irpf/100) as importe_irpf '
        . ',sum(pvptotal)*(recargo/100) as importe_recargo FROM `' . $this->detailTable . '`'
        . ' where idfactura=' . $idfactura['idfactura'];
        $sql .= ' group by idfactura,iva,irpf,recargo; ';

        //$groupby = ' group by iva,' . $this->detailTable . '.irpf,recargo;';
       

        $data = $dataBase->select($sql);

        return $data;
    }

    public function listLinesDoc_NO($idfactura) {
        $dataBase = new \FacturaScripts\Core\Base\DataBase();
        $data = [];
        $where = [];
        $sql = 'SELECT idfactura,iva,irpf,recargo, sum(pvptotal) as base, '
                . ' sum(pvptotal)*(iva/100) as importe_iva,sum(pvptotal)*(irpf/100) as importe_irpf '
                . ',sum(pvptotal)*(recargo/100) as importe_recargo FROM `' . $this->detailTable . '`';
        //. ' where idfactura=' . $idfactura['idfactura']
        $sql .= ' group by idfactura,iva,irpf,recargo; ';

         $sql = 'SELECT idfactura,iva,irpf,recargo, sum(pvptotal) as base, '
                . ' sum(pvptotal)*(iva/100) as importe_iva,sum(pvptotal)*(irpf/100) as importe_irpf '
                . ',sum(pvptotal)*(recargo/100) as importe_recargo FROM `' . $this->detailTable . '`';
         $where = $this->getWhere();
        if ($where !== '') {
            $where .= ' and ';
        } else {
            $where = ' where  ';
        }
        $where .= $this->detailTable . '.idfactura=' . $this->headerTable . '.idfactura ';

        //$groupby = ' group by iva,' . $this->detailTable . '.irpf,recargo;';
        $sql .= ' group by ' . $this->detailTable . '.idfactura,iva,irpf,recargo; ';

        $data = $dataBase->select($sql);

        return $data;
    }

    public function resumen() {
        $dataBase = new \FacturaScripts\Core\Base\DataBase();
        $where = [];
        $sql = 'SELECT iva,' . $this->detailTable . '.irpf,recargo, sum(pvptotal) as base, '
                . ' sum(pvptotal)*(iva/100) as importe_iva,sum(pvptotal)*(' . $this->detailTable . '.irpf/100) as importe_irpf '
                . ',sum(pvptotal)*(recargo/100) as importe_recargo '
                . ' FROM `' . $this->detailTable . '`,' . $this->headerTable;
        $where = $this->getWhere();
        if ($where !== '') {
            $where .= ' and ';
        } else {
            $where = ' where  ';
        }
        $where .= $this->detailTable . '.idfactura=' . $this->headerTable . '.idfactura ';
        $codeCompany = $idempresa ?? AppSettings::get('default', 'idempresa', '');
        $where .= ' and idempresa=' . $codeCompany;
        $groupby = ' group by iva,' . $this->detailTable . '.irpf,recargo;';

        $data = $dataBase->select($sql . $where . $groupby);

        $this->results = $data;
        $this->numLineasResumen = count($data);
        return $data;
    }

    public function countNumLineasResumen() {
        $dataBase = new \FacturaScripts\Core\Base\DataBase();
        $where = [];
        $sql = 'SELECT iva,' . $this->detailTable . '.irpf,recargo '
                . ' FROM `' . $this->detailTable . '`,facturascli  ';
        $where = $this->getWhere();
        if ($where !== '') {
            $where .= ' and ';
        } else {
            $where = ' where ';
        }
        $where .= $this->detailTable . '.idfactura=' . $this->headerTable . '.idfactura ';
        $groupby = ' group by iva,' . $this->detailTable . '.irpf,recargo;';
        $data = $dataBase->select($sql . $where . $groupby);
        $this->results = $data;
        $this->numLineasResumen = count($data);
        return $this->numLineasResumen;
    }

    public function getExportData() {
        $dataBase = new \FacturaScripts\Core\Base\DataBase();
        $where = [];
        if ($this->headerTable === 'facturascli') {
            $sujetopasivo = 'nombrecliente';
        } else {
            $sujetopasivo = 'nombre';
        }
        $sql = 'SELECT f.codigo,f.fecha,f.cifnif,f.' . $sujetopasivo . ' razonsocial,sum(l.pvptotal)-sum(totalirpf) as base,l.iva as poriva,'
                . ' round(sum(l.pvptotal *l.iva/100),2) as totiva,l.irpf as pirpf,sum(totalirpf) as totirpf, f.total from '
                . $this->headerTable . ' f inner join '
                . $this->detailTable . ' l on f.idfactura=l.idfactura group by f.codigo,f.fecha,f.cifnif,f.' . $sujetopasivo . ',l.iva,f.total ';
        $sqlWhere = $this->getWhere();
        $codeCompany = $idempresa ?? AppSettings::get('default', 'idempresa', '');
       /* if ($sqlWhere !== '') {
          $sqlWhere .= ' and idempresa=' . $codeCompany;
          } else {
          $sqlWhere = ' having idempresa=' . $codeCompany;
          } */
       
        $data = $dataBase->select($sql . $sqlWhere);

        $this->results = $data;
        return $this->results;
    }

}
