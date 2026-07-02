<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\RemesasSEPA\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

/**
 * Description of ListRemesaSEPA
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ListRemesaSEPA extends ListController
{

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'accounting';
        $data['title'] = 'remittances';
        $data['icon'] = 'fas fa-piggy-bank';
        return $data;
    }

    protected function createViews()
    {
        $viewName = 'ListRemesaSEPA';
        $this->addView($viewName, 'RemesaSEPA', 'charges', 'fas fa-file-import');
        $this->addOrderBy($viewName, ['idremesa'], 'code');
        $this->addOrderBy($viewName, ['fecha'], 'date');
        $this->addOrderBy($viewName, ['fechacargo'], 'charge-date', 2);
        $this->addOrderBy($viewName, ['total'], 'amount');
        $this->addSearchFields($viewName, ['descripcion', 'idremesa']);

        // filters
        $this->addFilterPeriod($viewName, 'fecha', 'date', 'fecha');

        $banks = $this->codeModel->all('cuentasbanco', 'codcuenta', 'descripcion');
        $this->addFilterSelect($viewName, 'codcuenta', 'bank-account', 'codcuenta', $banks);

        $status = $this->codeModel->all('remesas_sepa', 'estado', 'estado');
        $this->addFilterSelect($viewName, 'estado', 'status', 'estado', $status);
    }
}
