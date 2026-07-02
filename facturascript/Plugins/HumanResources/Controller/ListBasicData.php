<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

/**
 * Controller to agroup RRHH basic data
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class ListBasicData extends ListController
{

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'basic-data';
        $pagedata['icon'] = 'fa-solid fa-cogs';
        $pagedata['menu'] = 'rrhh';
        $pagedata['ordernum'] = 0;

        return $pagedata;
    }

    /**
     * Load views
     */
    protected function createViews()
    {
        $this->createViewAbsenceConcept();
        $this->createViewConcept();
        $this->createViewContract();
        $this->createViewDepartment();
        $this->createViewPublicHoliday();
        $this->createViewDocument();
        $this->createViewSanction();
        $this->createViewOffense();
    }

    /**
     * Add and configure absence concept view
     *
     * @param string $viewName
     */
    private function createViewAbsenceConcept(string $viewName = 'ListAbsenceConcept')
    {
        $this->addView($viewName, 'AbsenceConcept', 'absence-concept', 'fa-solid fa-unlink');
        $this->addSearchFields($viewName, ['name']);

        $this->addOrderBy($viewName, ['name'], 'name');
    }

    /**
     * Add and configure salary concepts view
     *
     * @param string $viewName
     */
    private function createViewConcept(string $viewName = 'ListSalaryConcept')
    {
        $this->addView($viewName, 'SalaryConcept', 'salary-concepts', 'fa-solid fa-money-bill-alt');
        $this->addSearchFields($viewName, ['name']);
        $this->addOrderBy($viewName, ['name'], 'name');
        $this->addOrderBy($viewName, ['codsubaccount'], 'subaccount');
    }

    /**
     * Add and configure contracts view
     *
     * @param string $viewName
     */
    private function createViewContract(string $viewName = 'ListContract')
    {
        $this->addView($viewName, 'Contract', 'contracts', 'fa-solid fa-handshake');
        $this->addSearchFields($viewName, ['name', 'id']);

        $this->addOrderBy($viewName, ['name'], 'name');
    }

    /**
     * Add and configure department view
     *
     * @param string $viewName
     */
    private function createViewDepartment(string $viewName = 'ListDepartment')
    {
        $this->addView($viewName, 'Department', 'departments', 'fa-solid fa-id-card');
        $this->addSearchFields($viewName, ['name']);

        $this->addOrderBy($viewName, ['name'], 'name');
        $this->addOrderBy($viewName, ['idcompany', 'name'], 'company');
    }

    /**
     * Add view Document Types.
     *
     * @param string $viewName
     */
    private function createViewDocument(string $viewName = 'ListDocumentType')
    {
        $this->addView($viewName, 'DocumentType', 'documents-types', 'fa-solid fa-copy');
        $this->addSearchFields($viewName, ['name', 'id']);
        $this->addOrderBy($viewName, ['name'], 'name');
        $this->addOrderBy($viewName, ['id'], 'code');
    }

    /**
     * Add and configure public holidays view
     *
     * @param string $viewName
     */
    private function createViewPublicHoliday(string $viewName = 'ListPublicHoliday')
    {
        $this->addView($viewName, 'PublicHoliday', 'public-holidays', 'fa-solid fa-calendar-times');
        $this->addSearchFields($viewName, ['name', 'CAST(holiday AS CHAR(30))']);

        $this->addOrderBy($viewName, ['holiday'], 'date', 2);
        $this->addOrderBy($viewName, ['name'], 'desc');

        $this->addFilterPeriod($viewName, 'holiday', 'date', 'holiday');
    }

    /**
     * Add view Dicciplinary Offenses
     * 
     * @param string $viewName
     */
    private function createViewOffense(string $viewName = 'ListDisciplinaryOffense')
    {
        $this->addView($viewName, 'DisciplinaryOffense', 'disciplinary-offenses', 'fa-solid fa-people-arrows');
        $this->addSearchFields($viewName, ['name', 'id']);

        $this->addOrderBy($viewName, ['name'], 'name');
    }

    /**
     * Add view Disciplinary Sanctions
     *
     * @param string $viewName
     */
    private function createViewSanction(string $viewName = 'ListSanction')
    {
        $this->addView($viewName, 'Sanction', 'sanctions', 'fa-solid fa-gavel');
        $this->addSearchFields($viewName, ['name', 'id']);

        $this->addOrderBy($viewName, ['name'], 'name');
    }
}
