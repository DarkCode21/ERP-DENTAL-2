<?php
namespace FacturaScripts\Plugins\Integraciones\Controller;

use FacturaScripts\Core\Base\Controller;
#use FacturaScripts\Core\Base\PluginManager;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Cache;
use FacturaScripts\Core\Internal\Forja;
#use FacturaScripts\Core\Base\ExtensionsTrait;

class Integracion extends Controller 
{
   #use ExtensionsTrait;
   private $logLevels = ['critical', 'error', 'info', 'notice', 'warning'];
	
	/** @var array */
   public $pluginList = [];
  
   /** @var array */
   public $remotePluginList = [];

   public function getPageData(): array
   {
        $pageData = parent::getPageData();
        $pageData["title"] = "Integraciones";
        $pageData["menu"] = "admin";
        $pageData["icon"] = "fab fa-confluence";
        return $pageData;
   }

   /**
     * @param Response $response
     * @param User $user
     * @param ControllerPermissions $permissions
     */
    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

		$action = $this->request->get('action', '');
		if ($action === 'enable' || $action === 'disable') {
			if ($this->validateFormToken()) {
				$pluginName = $this->request->get('plugin', '');
				if ($action === 'enable') {
					Plugins::enable($pluginName);
				} else {
					Plugins::disable($pluginName);
				}
				Cache::clear();
			}
		}

		#$this->getPlugins();
		$this->setTemplate('Integracion');
	}
	
	/**
     * Return installed plugins without hidden ones.
     *
     * @return array
     */
    public function getPlugins(): array
    {
		include(getcwd().'/Plugins/Integraciones/config.php');
		#$this->pluginManager = new PluginManager();
	
        $this->pluginList = Plugins::list(); #$this->pluginManager->installedPlugins();
		/*foreach (Forja::plugins() as $item) {
			 $this->remotePluginList[] = $item;
		}*/
		if (false === defined('FS_SHOW_INTEGRATED_PLUGINS')) {
            return $this->pluginList;
        }

        // exclude hidden plugins
        $showPlugins = explode(',', FS_SHOW_INTEGRATED_PLUGINS);
		#die(var_dump($showPlugins));
        foreach ($this->pluginList as $key => $plugin) {
			#die(var_dump([$plugin->name, $key, $showPlugins]));
            if (!in_array($plugin->name, $showPlugins, false)) {
                unset($this->pluginList[$key]);
            }
        }
        return $this->pluginList;
    }

	public function urlPlugins(): string
	{
		return '/AdminPlugins';
	}
}