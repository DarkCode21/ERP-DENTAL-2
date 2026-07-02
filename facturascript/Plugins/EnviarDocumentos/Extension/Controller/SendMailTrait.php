<?php
/**
 * Copyright (C) 2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\EnviarDocumentos\Extension\Controller;

use Closure;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait SendMailTrait
{
    protected function createViews(): Closure
    {
        return function () {
            $viewName = array_keys($this->views)[0];
            $this->addButton($viewName, [
                'action' => 'mail-docs',
                'color' => 'light',
                'icon' => 'fas fa-envelope',
                'label' => 'send'
            ]);
        };
    }

    protected function execPreviousAction(): Closure
    {
        return function ($action) {
            if ($action === 'mail-docs') {
                $viewName = array_keys($this->views)[0];
                $params = 'doc=' . $this->views[$viewName]->model->modelClassName();

                $code = $this->request->request->get('code', []);
                if (false === empty($code)) {
                    $params .= '&ids=' . implode(',', $code);
                }

                $this->redirect('SendPendingDocs?' . $params);
            }
        };
    }
}