<?php
namespace FacturaScripts\Plugins\Nominas\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\DocFilesTrait;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Dinamic\Lib\ServiceToInvoice;

/**
 * Description of EditServicioAT
 *
 * @author Erick Lizana
 */
class EditEmpleado extends EditController
{
	use DocFilesTrait;

	public $where = [];
	
	public function getModelClassName(): string
    {
        return 'Empleado';
    }


	public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'employee';
        $data['icon'] = 'fas fa-user';
        $data['showonmenu'] = false;
        return $data;
    }

	protected function createViews()
    {
        parent::createViews();
		$this->createViewsNomina();
		#$this->createViewDocFiles();  
    }
	
	protected function createViewsNomina(string $viewName = 'ListNomina')
	{
		$this->addListView($viewName, 'Nomina', 'nominas', 'fas fa-users');
  	    $this->setSettings($viewName, 'btnNew', false);
		$this->setSettings($viewName, 'btnDelete', false);

        #$this->optionsFilters($viewName);
  	}
	
	protected function loadData($viewName, $view)
    {
		$mainViewName = $this->getMainViewName();
		$idempleado = $this->request->query->get('code');
		switch ($viewName) {
			case 'ListNomina':
				$where = [new DataBaseWhere('codempleado', $idempleado)];
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