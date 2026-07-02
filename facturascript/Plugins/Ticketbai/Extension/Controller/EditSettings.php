<?php
/**
 * Copyright (C) 2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Ticketbai\Extension\Controller;

use Closure;

/**
 * @author Alayn Gortazar Huete - Barnetik Koop <alayn@barnetik.com>
 */
class EditSettings
{
    protected function createViews(): Closure
    {
        return function () {
            $countries = $this->codeModel->all('paises', 'codpais', 'nombre');
            $provinces = $this->codeModel->all('provincias', 'idprovincia', 'provincia');

            $this->addListView('ListCodigoIae', 'CodigoIae', 'codes-iae', 'fa-solid fa-wallet')
                ->addSearchFields(['descripcion', 'iae'])
                ->addOrderBy(['iae'], 'code-iae')
                ->addOrderBy(['descripcion'], 'description')
                ->addFilterSelect('codpais', 'country', 'codpais', $countries)
                ->addFilterSelect('idprovincia', 'province', 'idprovincia', $provinces);
        };
    }
}
