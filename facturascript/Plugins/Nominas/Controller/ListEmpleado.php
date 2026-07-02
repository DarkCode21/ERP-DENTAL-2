<?php

namespace FacturaScripts\Plugins\Nominas\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Lib\ExtendedController\ListView;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use Symfony\Component\HttpFoundation\Response;
use FacturaScripts\Core\Base\ExtensionsTrait;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Model\Employee;

class ListEmpleado extends ListController
{

	private $logLevels = ['critical', 'error', 'info', 'notice', 'warning'];

	public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData["title"] = "Empleados";
        $pageData["menu"] = "admin";
        $pageData["icon"] = "fas fa-user";
        return $pageData;
    }

	protected function createViews()
    {
        $this->createViewsEmployee();
        $this->createViewsNominas();
    }

	protected function createViewsEmployee(string $viewName = 'ListEmpleado')
    {
	    $this->addView($viewName, 'Empleado', 'employees', 'fas fa-user');
        $this->addSearchFields($viewName, ['nombre', 'numfiscal','direccion','grupocotizacion', 'localidad']);
		/*$this->addOrderBy($viewName, ['fecha', 'idproyecto'], 'date', 2);
        $this->addOrderBy($viewName, ['fechainicio'], 'start-date');
        $this->addOrderBy($viewName, ['fechafin'], 'end-date');
        $this->addOrderBy($viewName, ['nombre'], 'name');
        $this->addOrderBy($viewName, ['totalcompras'], 'total-purchases');
        $this->addOrderBy($viewName, ['totalventas'], 'total-sales');
        $this->addSearchFields($viewName, ['nombre', 'descripcion']);

        // filters
        $where = [
            ['label' => $this->toolBox()->i18n()->trans('only-active'), 'where' => [new DataBaseWhere('editable', true)]],
            ['label' => $this->toolBox()->i18n()->trans('only-closed'), 'where' => [new DataBaseWhere('editable', false)]],
            ['label' => $this->toolBox()->i18n()->trans('all'), 'where' => []]
        ];
        foreach ($this->codeModel->all('proyectos_estados', 'idestado', 'nombre') as $status) {
            $where[] = ['label' => $status->description, 'where' => [new DataBaseWhere('idestado', $status->code)]];
        }
        $this->addFilterSelectWhere($viewName, 'status', $where);

        $this->addFilterPeriod($viewName, 'fecha', 'date', 'fecha');
        $this->addFilterNumber($viewName, 'totalcompras-gt', 'total-purchases', 'totalcompras', '>=');
        $this->addFilterNumber($viewName, 'totalcompras-lt', 'total-purchases', 'totalcompras', '<=');
        $this->addFilterNumber($viewName, 'totalventas-gt', 'total-sales', 'totalventas', '>=');
        $this->addFilterNumber($viewName, 'totalventas-lt', 'total-sales', 'totalventas', '<=');
        $this->addFilterAutocomplete($viewName, 'codcliente', 'customer', 'codcliente', 'clientes', 'codcliente', 'nombre');
    	*/
	}


	protected function createViewsNominas(string $viewName = 'ListNomina')
    {
	    $this->addView($viewName, 'Nomina', 'nominas', 'fas fa-users');
        #$this->addSearchFields($viewName, ['nombre', 'numfiscal','direccion','grupocotizacion', 'localidad']);
		/*$this->addOrderBy($viewName, ['fecha', 'idproyecto'], 'date', 2);
        $this->addOrderBy($viewName, ['fechainicio'], 'start-date');
        $this->addOrderBy($viewName, ['fechafin'], 'end-date');
        $this->addOrderBy($viewName, ['nombre'], 'name');
        $this->addOrderBy($viewName, ['totalcompras'], 'total-purchases');
        $this->addOrderBy($viewName, ['totalventas'], 'total-sales');
        $this->addSearchFields($viewName, ['nombre', 'descripcion']);

        // filters
        $where = [
            ['label' => $this->toolBox()->i18n()->trans('only-active'), 'where' => [new DataBaseWhere('editable', true)]],
            ['label' => $this->toolBox()->i18n()->trans('only-closed'), 'where' => [new DataBaseWhere('editable', false)]],
            ['label' => $this->toolBox()->i18n()->trans('all'), 'where' => []]
        ];
        foreach ($this->codeModel->all('proyectos_estados', 'idestado', 'nombre') as $status) {
            $where[] = ['label' => $status->description, 'where' => [new DataBaseWhere('idestado', $status->code)]];
        }
        $this->addFilterSelectWhere($viewName, 'status', $where);

        $this->addFilterPeriod($viewName, 'fecha', 'date', 'fecha');
        $this->addFilterNumber($viewName, 'totalcompras-gt', 'total-purchases', 'totalcompras', '>=');
        $this->addFilterNumber($viewName, 'totalcompras-lt', 'total-purchases', 'totalcompras', '<=');
        $this->addFilterNumber($viewName, 'totalventas-gt', 'total-sales', 'totalventas', '>=');
        $this->addFilterNumber($viewName, 'totalventas-lt', 'total-sales', 'totalventas', '<=');
        $this->addFilterAutocomplete($viewName, 'codcliente', 'customer', 'codcliente', 'clientes', 'codcliente', 'nombre');
    	*/
	}

	protected function loadData($viewName, $view)
    {
		$mainViewName = $this->getMainViewName();
		#die(var_dump($this->request));
		switch ($viewName) {
			case 'ListEmpleado':
				$where = [new DataBaseWhere('activo', 1)];
                $view->loadData('', $where);
         		break;
			case 'ListNomina':
				$where = [new DataBaseWhere('activo', 1)];
                $view->loadData('', $where);
         		break;
			case $mainViewName:
				parent::loadData($viewName, $view);
                break;
		}
        #$idempleado = $this->getViewModelValue($mainViewName, 'idempleado');
		#die(var_dump($idempleado));
	}

}