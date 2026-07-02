<?php

namespace FacturaScripts\Plugins\ImportFacEmail\Extension\Controller;

use Closure;
use FacturaScripts\Core\Model\Settings;
use FacturaScripts\Core\Tools;

class EditSettings
{
    const KEY_SETTINGS = 'Settings';

    public function createViews(): Closure
    {
        return function () {
            $this->createViewImportFacEmail();
        };
    }

    protected function createViewImportFacEmail(): Closure
    {
        return function (string $name = 'SettingsImportFacEmail', string $model = 'Settings', string $icon = 'fas fa-envelope-open-text') {
            $title = $this->getKeyFromViewName($name);
            $this->addHtmlView($name, $model, $title, $icon);

            // Inicializar valores por defecto si no existen
            if (empty(Tools::settings('importfacemail', 'usuario'))) {
                Tools::settingsSet('importfacemail', 'usuario', '');
                Tools::settingsSet('importfacemail', 'contrasena', '');
                Tools::settingsSet('importfacemail', 'tipeserv', 'imap');
                Tools::settingsSet('importfacemail', 'nomserv', '');
                Tools::settingsSet('importfacemail', 'port', 587);
                Tools::settingsSave();
            }

            // Cambiar el icono según el grupo
            $groups = $this->views[$name]->getColumns();
            foreach ($groups as $group) {
                if (!empty($group->icon)) {
                    $this->views[$name]->icon = $group->icon;
                    break;
                }
            }

            // Deshabilitar botones innecesarios
            $this->setSettings($name, 'btnDelete', false);
            $this->setSettings($name, 'btnNew', false);
        };
    }

    protected function editAction(): Closure
    {
        return function () {
            if (false === parent::editAction()) {
                return false;
            }

            // Limpiar la caché de configuraciones
            Tools::settingsClear();
            return true;
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName === 'SettingsImportFacEmail') {
                $code = $this->getKeyFromViewName($viewName);
                $view->loadData($code);
                if ($view->model instanceof Settings && empty($view->model->name)) {
                    $view->model->name = $code;
                }
            }
        };
    }

    /**
     * Retorna el código de configuración a partir del nombre de la vista
     *
     * @param string $viewName
     * @return string
     */
    protected function getKeyFromViewName(): Closure
    {
        return function ($viewName = 'SettingsImportFacEmail') {
            return strtolower(substr($viewName, strlen(self::KEY_SETTINGS)));
        };
    }
}
