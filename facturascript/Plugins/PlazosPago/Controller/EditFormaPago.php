<?php
/**
 * Copyright (C) 2019-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PlazosPago\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Controller\EditFormaPago as ParentController;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;

/**
 * Description of EditFormaPago
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditFormaPago extends ParentController
{
    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');
        $this->createViewPlazos();
    }

    protected function createViewPlazos(string $viewName = 'EditFormaPagoPlazo'): void
    {
        $this->addEditListView($viewName, 'FormaPagoPlazo', 'payment-term', 'fas fa-calendar-alt')
            ->setInLine(true);
    }

    protected function disableExpirationWidgets(string $viewName): void
    {
        if ($this->views[$viewName]->count > 0) {
            $mainViewName = $this->getMainViewName();
            $this->views[$mainViewName]->disableColumn('expiration');
            $this->views[$mainViewName]->disableColumn('expiration-type');
        }
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'EditFormaPagoPlazo':
                $codpago = $this->getViewModelValue($this->getMainViewName(), 'codpago');
                $where = [new DataBaseWhere('codpago', $codpago)];
                $view->loadData('', $where, ['id' => 'DESC']);
                $this->disableExpirationWidgets($viewName);
                break;

            default:
                parent::loadData($viewName, $view);
        }
    }
}
