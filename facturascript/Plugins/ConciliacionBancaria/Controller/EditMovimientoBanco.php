<?php
/**
 * Copyright (C) 2023-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\ConciliacionBancaria\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditMovimientoBanco extends EditController
{
    public function getModelClassName(): string
    {
        return "MovimientoBanco";
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data["title"] = "bank-movement";
        $data["icon"] = "fas fa-list";
        return $data;
    }

    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        // desactivamos los botones de nuevo, deshacer y guardar
        $mvn = $this->getMainViewName();
        $this->setSettings($mvn, 'btnNew', false);
        $this->setSettings($mvn, 'btnUndo', false);
        $this->setSettings($mvn, 'btnSave', false);

        $this->createViewsRecibos();
        $this->createViewsAsientos();
    }

    protected function createViewsAsientos($viewName = 'ListAsiento'): void
    {
        $model = $this->getModel();
        if (false === $model->loadFromCode($this->request->get('code'))) {
            return;
        }

        $this->addListView($viewName, 'Asiento', "accounting-entries", "fas fa-balance-scale")
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false)
            ->setSettings('checkBoxes', false);

    }

    protected function createViewsRecibos(): void
    {
        $model = $this->getModel();
        if (false === $model->loadFromCode($this->request->get('code'))) {
            return;
        }

        $loadModel = $model->amount > 0 ? 'ReciboCliente' : 'ReciboProveedor';
        $viewName = $model->amount > 0 ? 'ListReciboCliente' : 'ListReciboProveedor';
        $this->addListView($viewName, $loadModel, "receipts", "fas fa-dollar-sign")
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false)
            ->setSettings('checkBoxes', false);
    }

    protected function loadData($viewName, $view)
    {
        $mvn = $this->getMainViewName();
        switch ($viewName) {
            case 'ListAsiento':
            case 'ListReciboCliente':
            case 'ListReciboProveedor':
                $where = [new DataBaseWhere('idbankmovement', $this->getViewModelValue($mvn, 'id'))];
                $view->loadData('', $where);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}
