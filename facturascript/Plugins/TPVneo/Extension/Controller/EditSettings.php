<?php
/**
 * Añade la pestaña de configuración ESC/POS de TPVneo
 * a la página de Configuración del sistema (Admin > Configuración).
 */

namespace FacturaScripts\Plugins\TPVneo\Extension\Controller;

use Closure;
use FacturaScripts\Core\Model\Settings;

class EditSettings
{
    public function createViews(): Closure
    {
        return function () {
            $viewName = 'SettingsTPVneo';
            $this->addEditView($viewName, 'Settings', 'TPVneo', 'fas fa-cash-register')
                ->setSettings('btnDelete', false)
                ->setSettings('btnNew', false);
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName !== 'SettingsTPVneo') {
                return;
            }

            $view->loadData('tpvneo');
            if ($view->model instanceof Settings && empty($view->model->name)) {
                $view->model->name = 'tpvneo';
            }

            if ($view->model instanceof Settings) {
            }
        };
    }
}
