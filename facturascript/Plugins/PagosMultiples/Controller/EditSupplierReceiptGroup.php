<?php
/**
 * This file is part of PagosMultiples plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * PagosMultiples Copyright (C) 2020-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\PagosMultiples\Controller;

use FacturaScripts\Plugins\PagosMultiples\Lib\PagosMultiples\EditPaymentReceiptGroup;

/**
 * Controller to list the items in the SupplierReceiptGroup model
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditSupplierReceiptGroup extends EditPaymentReceiptGroup
{

    protected const VIEW_ADD = 'ListReciboProveedor-add';
    protected const VIEW_LIST = 'ListReciboProveedor';
    protected const VIEW_NOTE = 'EditCustomerReceiptGroupNote';

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'SupplierReceiptGroup';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'multiple-payment';
        $pagedata['icon'] = 'fas fa-coins';
        $pagedata['menu'] = 'purchases';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    /**
     * Create the view to display.
     *
     * Disable company column from main view, if there is only one company.
     * Set tabs to bottom position.
     */
    protected function createViews()
    {
        parent::createViews();
        $this->createViewAccounting();
    }

    /**
     * Add receipts pending list.
     */
    protected function createViewReceiptsAdd()
    {
        parent::createViewReceiptsAdd();

        $viewName = static::VIEW_ADD;
        $this->views[$viewName]->addOrderBy(['recibos.codproveedor'], 'supplier');
        $this->views[$viewName]->addFilterAutocomplete('codproveedor', 'supplier', 'recibos.codproveedor', 'proveedores', 'codproveedor', 'razonsocial');
    }
}