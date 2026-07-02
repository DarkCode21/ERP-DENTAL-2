<?php
/**
 * This file is part of DocumentosRecurrentes plugin for FacturaScripts.
 * FacturaScripts         Copyright (C) 2015-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DocumentosRecurrentes  Copyright (C) 2020-2021 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DocumentosRecurrentes\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Lib\DocumentosRecurrentes\DocRecurringManager;
use FacturaScripts\Dinamic\Lib\DocumentosRecurrentes\ListDocRecurring;
use FacturaScripts\Plugins\DocumentosRecurrentes\Model\DocRecurringSale;

/**
 * Description of ListDocRecurringSale
 *
 * @author Carlos Garcia Gomez  <carlos@facturascripts.com>
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class ListDocRecurringSale extends ListDocRecurring
{

    private const VIEW_RECURRING = 'ListDocRecurringSale';
    private const VIEW_EXPIRED = 'ListDocExpiredSale';

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['menu'] = 'sales';
        $pagedata['title'] = 'recurring';
        $pagedata['icon'] = 'fas fa-calendar-plus';
        return $pagedata;
    }

    protected function createViews()
    {
        $this->createViewsRecurring();
        $this->createViewsExpired();
    }

    /**
     *
     * @param string $viewName
     */
    protected function createViewsExpired()
    {
        $this->createViewsDocRecurring(self::VIEW_EXPIRED, 'expired');
        $this->setSettings(self::VIEW_EXPIRED, 'btnNew', false);
        $this->setSettings(self::VIEW_EXPIRED, 'btnDelete', false);
    }

    /**
     *
     * @param string $viewName
     */
    protected function createViewsRecurring()
    {
        $this->createViewsDocRecurring(self::VIEW_RECURRING, 'recurring');

        $i18n = $this->toolBox()->i18n();
        $values = [
            ['label' => $i18n->trans('unexpired'), 'where' => [new DataBaseWhere('enddate', $this->toolBox()->today(), '>'), new DataBaseWhere('enddate', null, 'IS', 'OR')]],
            ['label' => $i18n->trans('expired'), 'where' => [new DataBaseWhere('enddate', $this->toolBox()->today(), '<=')]],
            ['label' => $i18n->trans('all'), 'where' => []]
        ];
        $this->addFilterSelectWhere(self::VIEW_RECURRING, 'status', $values);

        $this->addButton(self::VIEW_RECURRING, [
            'action' => 'generate-recurring',
            'color' => 'warning',
            'icon' => 'fas fa-magic',
            'label' => 'generate',
            'type' => 'modal'
        ]);
    }

    /**
     *
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'generate-recurring':
                return $this->generateDocsAction();

            default:
                return parent::execPreviousAction($action);
        }
    }

    /**
     *
     * @return bool
     */
    protected function generateDocsAction(): bool
    {
        $where = $this->generateDocsWhere();
        if (empty($where)) {
            $this->toolBox()->i18nLog()->warning('no-selected-item');
            return true;
        }

        $num = 0;
        $docRecurring = new DocRecurringManager();
        $model = new DocRecurringSale();
        foreach ($model->all($where, [], 0, 0) as $template) {
            if ($docRecurring->generateSaleDoc($template->id, ['date' => $template->nextdate])) {
                $num++;
            }
        }

        $this->toolBox()->i18nLog()->notice('generated-documents-quantity', ['%quantity%' => $num]);
        return true;
    }

    /**
     * Load data for view.
     * if it is the main view it assigns the date of the day to the modal form.
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view) {
        switch ($viewName) {
            case self::VIEW_EXPIRED:
                $where = [new DataBaseWhere('enddate', $this->toolBox()->today(), '<=')];
                $view->loadData('', $where);
                $view->model->untilNextDate = $this->toolBox()->today();
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    /**
     *
     * @param string $viewName
     */
    private function createViewsDocRecurring(string $viewName, string $label)
    {
        $this->addView($viewName, 'DocRecurringSale', $label, 'fas fa-calendar-plus');
        $this->addCommonSearchFields($viewName);
        $this->addCommonOrderBy($viewName);
        $this->addCommonFilters($viewName);

        $this->addFilterAutocomplete($viewName, 'customer', 'customer', 'codcliente', 'Cliente');
    }
}
