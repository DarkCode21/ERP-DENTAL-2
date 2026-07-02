<?php

namespace FacturaScripts\Plugins\FSReports\Controller;

use \FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\DocumentReportsBase\DocumentReportsFilterList;


use Symfony\Component\HttpFoundation\Response;

/**
 * Description of ReportTarifa
 *
 * @author Raul Jimenez <raljopa@gmail.com>
 */



class ReportTarifa extends \FacturaScripts\Core\Base\Controller
{

    private $labels;

    /**
     * List of filters.
     *
     * @var DocumentReportsFilterList[]
     */
    public $filters;
    public $results;

    public function __construct(string $className, string $uri = '')
    {
        parent::__construct($className, $uri);
        
        $this->labels = ['Referencia', 'Descripcion', 'Precio'];
    }

    public function getPageData(): array {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'Debug';
        $pageData['submenu'] = 'Products';
        $pageData['title'] = 'Tarifa productos';
        $pageData['icon'] = 'fas fa-plug';
        $pageData['showonmenu'] = 'false';

        return $pageData;
    }

    public function getLabels()
    {
        return $this->labels;
    }

    public function generateResults()
    {

        $dataBase = new \FacturaScripts\Core\Base\DataBase();
        $where = [];
        $sql = 'select codfamilia,referencia,descripcion,precio from productos';
        $sql = 'SELECT referencia,pr.descripcion nomProducto,precio,fam.codfamilia,fam.descripcion nomFamilia '
            . ' FROM `productos` pr inner join familias fam '
            . 'on fam.codfamilia=pr.codfamilia';

        foreach (array_keys($this->filters) as $filter) {

            if ($this->request->get(str_replace('.', '_', $filter), '') !== '') {

                $where[] = new \FacturaScripts\Core\Base\DataBase\DataBaseWhere($filter, $this->request->get(str_replace('.', '_', $filter), ''));
            }
        }

        $sql .= \FacturaScripts\Core\Base\Database\DataBaseWhere::getSQLWhere($where);
        $sql .= ' order by codfamilia';

        $data = $dataBase->selectLimit($sql);
        $this->results = $data;

        return $this->results;
    }

    public function descripionFamilia($codFamilia)
    {
        $familia = new \FacturaScripts\Dinamic\Model\Familia();
        echo $codFamilia;
        $familia->loadFromCode($codFamilia);
        return $familia->descripcion;
    }
}
