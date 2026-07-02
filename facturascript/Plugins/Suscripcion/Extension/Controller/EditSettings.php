<?php

namespace FacturaScripts\Plugins\Suscripcion\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Settings;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Core\Tools;

class EditSettings
{
	const KEY_SETTINGS = 'Settings';

	public function createViews(): Closure
	{
		return function () {
			$this->createViewSettings();
		};
	}


	protected function editAction(): Closure
	{
		return function () {
			if (false === parent::editAction()) {
				return false;
			}

			Tools::settingsClear();
			// check relations
			return true;
		};
	}

	protected function getSizeUsed(): Closure
	{
		return function ($dir) {
			$size = 0;
			foreach (glob(rtrim($dir, '/') . '/*', GLOB_NOSORT) as $each) {
				$size += is_file($each) ? filesize($each) : $this->getSizeUsed($each);
			}
			return $size;
		};
	}

	protected function createViewSettings()
	{
		return function (string $name = 'SettingsSuscription', string $model = 'Settings', string $icon = 'fas fa-money-check') {
			#$appSettings = $this->toolBox()->appSettings();
			$title = $this->getKeyFromViewName();
			$this->addHtmlView($name, 'Tab/Suscripcion', $model, $title, $icon);
			#PARA SETEAR LOS VALORES
			$usuarios = new User();
			$where = [
				new DataBaseWhere('enabled', 1)
			];
			$numEmpresas =  Tools::settings('suscription', 'empresas');
			if ($numEmpresas == "" || $numEmpresas != 0) {
				Tools::settingsSet('suscription', 'empresas', value: 1);
			}

			// assign no payment method
			Tools::settingsSet('suscription', 'maxusuarios', $usuarios->count($where));
			$size = $this->getSizeUsed($_SERVER['DOCUMENT_ROOT'] . '/');
			$licencia = Tools::settings('default', 'nombrecrm') . ' (' . Tools::settings('default', 'infoextra') . ')';
			Tools::settingsSet('suscription', 'capacidadalmacenamiento', number_format($size / pow(10, 9), 4) . ' GBytes');
			Tools::settingsSet('suscription', 'capacidadmaxalmacenamiento', '2 GBytes');
			Tools::settingsSet('suscription', 'maxfacturaspermitidas', 12);
			Tools::settingsSet('suscription', 'maxclientespermitidos', 10);
			Tools::settingsSet('suscription', 'maxproveedorespermitidos', 10);
			Tools::settingsSet('suscription', 'maxproductoserviciospermitidos', 5);
			Tools::settingsSet('suscription', 'licencia', $licencia);
			Tools::settingsSave();

			/*$appSettings->set($title, 'maxusuarios', $usuarios->count());
			$size = $this->getSizeUsed($_SERVER['DOCUMENT_ROOT'].'/');
			$appSettings->set($title, 'capacidadalmacenamiento', number_format($size/pow(10,9), 4). ' GBytes');
			$appSettings->set($title, 'capacidadmaxalmacenamiento', '2 GBytes');
			$appSettings->set($title, 'maxfacturaspermitidas', "Ilimitadas");
			$appSettings->set($title, 'maxclientespermitidos', "Ilimitados");
			$appSettings->set($title, 'maxproveedorespermitidos', "Ilimitados");
			$appSettings->set($title, 'maxproductoserviciospermitidos', "Ilimitados");*/

			/*$appSettings->set($title, 'licencia', $appSettings->get($codeDefault, 'nombrecrm'). ' ('. 
							  $appSettings->get($codeDefault, 'infoextra') .')');*/
			#$appSettings->save();


			// change icon
			$groups = $this->views[$name]->getColumns();
			foreach ($groups as $group) {
				if (!empty($group->icon)) {
					$this->views[$name]->icon = $group->icon;
					break;
				}
			}
			$this->setSettings($name, 'btnDelete', false);
			$this->setSettings($name, 'btnNew', false);
			$this->setSettings($name, 'btnSave', false);
			$this->setSettings($name, 'btnUndo', false);
		};
	}

	protected function loadData(): Closure
	{
		return function ($viewName, $view) {
			switch ($viewName) {
				case 'SettingsSuscription':
					#Tools::settingsClear();
					$code = $this->getKeyFromViewName();
					$view->loadData($code);
					if ($view->model instanceof Settings && empty($view->model->name)) {
						$view->model->name = $code;
					}
					break;
				case 'SettingsDefault':
					$code = $this->getKeyFromViewName($viewName);
					$view->loadData($code);
					if ($view->model instanceof Settings && empty($view->model->name)) {
						$view->model->name = $code;
					}
					#SETEAMOS EN SUSCRIPCION
					#$title = $this->getKeyFromViewName();
					#$appSettings = $this->toolBox()->appSettings();
					/*$licencia = Tools::settings($code, 'nombrecrm'). ' ('. 
							  	Tools::settings($code, 'infoextra') .')';
					
					Tools::settingsSet('suscription', 'licencia', $licencia);
        			Tools::settingsSave();*/

					/*$appSettings->set($title, 'licencia', $appSettings->get($code, 'nombrecrm'). ' ('. 
							  $appSettings->get($code, 'infoextra') .')');
					$appSettings->save();*/
					break;
			}
		};
	}

	/**
	 * Returns the view id for a specified $viewName
	 *
	 * @param string $viewName
	 *
	 * @return string
	 */
	protected function getKeyFromViewName(): Closure
	{
		return function ($viewName = 'SettingsSuscription') {
			#die(substr($viewName, strlen(self::KEY_SETTINGS));
			return strtolower(substr($viewName, strlen(self::KEY_SETTINGS)));
		};
	}
}
