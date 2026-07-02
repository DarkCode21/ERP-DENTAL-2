<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Controller;

use FacturaScripts\Core\Lib\AssetManager;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\PortalViewController;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalNote extends PortalViewController
{
    public function getModelClassName(): string
    {
        return 'PortalNote';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'PortalCliente';
        $data['title'] = 'note';
        $data['icon'] = 'far fa-sticky-note';
        return $data;
    }

    protected function createViews()
    {
        if (false === $this->preloadModel()->exists()) {
            $this->error404();
            return;
        }

        $this->setContactPermissions();
        if (false === $this->permissions->allowAccess) {
            $this->error403();
            return;
        }

        AssetManager::addCss('Plugins/PortalCliente/node_modules/easymde/dist/easymde.min.css');
        AssetManager::addJs('Plugins/PortalCliente/node_modules/easymde/dist/easymde.min.js');

        parent::createViews();
        $this->addHtmlView('note', 'Tab/PortalNote', 'PortalNote', 'note', 'far fa-sticky-note');
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $this->hasData = true;

        switch ($viewName) {
            case self::MAIN_VIEW_NAME:
                parent::loadData($viewName, $view);
                $this->title = Tools::lang()->trans('note') . ' ' . $view->model->title;
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    private function setContactPermissions(): void
    {
        // anónimo
        if (false === $this->contact->exists()) {
            $this->permissions->set(false, 0, false, false);
            return;
        }

        if ($this->user) {
            $this->permissions->set(true, 99, true, true);
            return;
        }

        // si la nota va destinada al contacto o al cliente asociado
        $model = $this->preloadModel();
        if ($model->idcontacto == $this->contact->idcontacto || $model->codcliente == $this->contact->codcliente) {
            $this->permissions->set(true, 1, false, false);
            return;
        }

        // no autorizado
        $this->permissions->set(false, 0, false, false);
    }
}