<?php

namespace FacturaScripts\Plugins\Nominas\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Dinamic\Lib\ExtendedController\PanelController;

use Symfony\Component\HttpFoundation\Response;
use FacturaScripts\Core\Base\ExtensionsTrait;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Model\Employee;

class Nomina extends Controller
{
    use ExtensionsTrait;

	private $logLevels = ['critical', 'error', 'info', 'notice', 'warning'];

	public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData["title"] = "Nominas";
        $pageData["menu"] = false;
        $pageData["icon"] = "fas fa-users";
        return $pageData;
    }

	protected function createViews()
    {
        #$this->setTemplate('Nominas');
        #$this->createViewEditConfig();
        #$this->createViewStatus();
        #$this->createViewPriorities();
    }

	protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case self::VIEW_CONFIG_PROJECTS:
                $view->loadData('servicios');
                $view->model->name = 'servicios';
                break;

            case self::VIEW_LIST_PRIORITIES:
            case self::VIEW_LIST_STATUS:
                $view->loadData();
                break;
        }
    }
	
	public function privateCore(&$response, $user, $permissions)
	{
		parent::privateCore($response, $user, $permissions);
		$action = $this->request->request->get('action');
		
		switch ($action) {
			case 'autocomplete-employee':
				$this->autocompleteEmployee();
				break;
			case 'autocomplete-data-employee':
				$this->autocompleteDataEmployee();
				break;
			default:
		        #$this->setTemplate('Nominas');
				#break;			
		}
    }
	
	protected function autocompleteEmployee()
    {
        $this->setTemplate(false);

        $list = [];
        $empleado = new Employee();
        $query = $this->request->get('query');
        foreach ($empleado->codeModelSearch($query, 'cifnif') as $value) {
		    $list[] = [
                'key' => $this->toolBox()->utils()->fixHtml($value->code),
                'value' => $this->toolBox()->utils()->fixHtml($value->description)
            ];
        }

        if (empty($list)) {
            $list[] = ['key' => null, 'value' => $this->toolBox()->i18n()->trans('no-data')];
        }
		$this->response->setContent(\json_encode($list));
    }
	
	protected function autocompleteDataEmployee()
	{
	    $this->setTemplate(false);
		/*$empleado = new Employee();
		$where = [
			new DataBaseWhere()
		];
        $empleado->loadFromCode('', $where);
		*/
	}

}