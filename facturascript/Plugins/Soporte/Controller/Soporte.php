<?php

namespace FacturaScripts\Plugins\Soporte\Controller;

use FacturaScripts\Core\Base\ExtensionsTrait;
use FacturaScripts\Core\Lib\ExtendedController\PanelController;

class Soporte extends PanelController
{

	public $usu = [];
	public $emp = [];

	use ExtensionsTrait;

	public function getPageData(): array
	{
		$pageData = parent::getPageData();
		$pageData['title'] = 'Soporte';
		$pageData['showmenu'] = false;
		$pageData['menu']  = 'admin';
		$pageData['icon'] = 'fas fa-cog';
		return $pageData;
	}

	protected function loadData($viewName, $view) {}

	public function privateCore(&$response, $user, $permissions)
	{
		parent::privateCore($response, $user, $permissions);
	}

	protected function createViews()
	{
		$this->addHtmlView('Soporte', 'Soporte', 'Empresa', 'admin', 'fas fa-rocket');
	}
}
