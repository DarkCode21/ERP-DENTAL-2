<?php
/**
 * This file is part of InformeSII plugin for FacturaScripts
 * Copyright (C) 2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\InformeSII\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ListFacturaProveedor
{
    public function createViews(): Closure
    {
       return function() {
           $i18n = $this->toolBox()->i18n();
           $this->addFilterSelectWhere('ListFacturaProveedor', 'sii-status', [
               ['label' => $i18n->trans('all'), 'where' => []],
               ['label' => $i18n->trans('not-sent'), 'where' => [new DataBaseWhere('sii_status', null)]],
               ['label' => $i18n->trans('correct'), 'where' => [
                   new DataBaseWhere('sii_status', 'Correcto'),
                   new DataBaseWhere('sii_status', 'Correcta', '=', 'OR'),
               ]],
               ['label' => $i18n->trans('incorrect'), 'where' => [new DataBaseWhere('sii_status', 'Incorrecto')]],
               ['label' => $i18n->trans('accepted-with-errors'), 'where' => [new DataBaseWhere('sii_status', 'AceptadaConErrores')]],
           ]);
       };
    }
}
