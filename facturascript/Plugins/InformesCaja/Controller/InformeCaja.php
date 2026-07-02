<?php
/**
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\InformesCaja\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\ControllerPermissions;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\DivisaTools;
use FacturaScripts\Core\Base\ExtensionsTrait;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Model\CodeModel;
use FacturaScripts\Dinamic\Lib\InformesCaja\ReportList;


/**
 *
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class InformeCaja extends Controller
{
    use ExtensionsTrait;

 	public $f_inicio;
	public $f_fin;
	public $s_ventas;
	public $codserie;
	
    private $logLevels = ['critical', 'error', 'info', 'notice', 'warning'];

	public function getPageData(): array
    {
        $data = parent::getPageData();
        $data["menu"] = "reports";
        $data["title"] = "name-plugin-report";
        $data["icon"] = "fas fa-cash-register";
        return $data;
    }
    /**
     * @param Response $response
     * @param User $user
     * @param ControllerPermissions $permissions
     */
    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $action = $this->request->request->get('action');
		
		if (is_null($action)) {
			$this->f_fin = date('Y-m-d');
			$this->f_inicio = date('Y-m-d');
			$this->codserie = '';
			
			$this->setTemplate('informeCaja');
		} else {
			$this->f_inicio = $this->request->request->get('f_inicio');
			$this->f_fin = $this->request->request->get('f_fin');
			$this->s_ventas = $this->request->request->get('s_ventas')??false;
			$this->codserie = $this->request->request->get('codserie', '');
			$this->setTemplate(false);
			if (!empty($this->f_inicio) && !empty($this->f_fin)) {
				$this->renderReport();
			} else {
				$content = [
					'reporte' => null,
					'messages' => $this->toolBox()->i18n()->trans('not-field-defined')
				];
		
				$this->response->setContent(json_encode($content));
			}
		}
	}

	public function renderReport(): void 
	{
	  	$content = [
			'reporte' => ReportList::render($this->f_inicio, $this->f_fin, $this->s_ventas, $this->user->nick, $this->codserie),
			'messages' => $this->toolBox()->log()->read('master', $this->logLevels)
		];
		
		$this->response->setContent(json_encode($content));
	}

	public function getSeriesValues(): array
	{
		$values = ['' => 'Todas'];
		foreach (CodeModel::all('series', 'codserie', 'descripcion') as $row) {
			$values[$row->code] = $row->code . ' - ' . $row->description;
		}

		return $values;
	}
    
}