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

namespace FacturaScripts\Plugins\InformeSII\Mod;

use FacturaScripts\Core\Base\Contract\SalesModInterface;
use FacturaScripts\Core\Base\Translator;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Model\User;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class SalesHeaderHTMLMod implements SalesModInterface
{
    public function apply(SalesDocument &$model, array $formData, User $user)
    {
    }

    public function applyBefore(SalesDocument &$model, array $formData, User $user)
    {
    }

    public function assets(): void
    {
    }

    public function newBtnFields(): array
    {
        return [];
    }

    public function newFields(): array
    {
        return [];
    }

    public function newModalFields(): array
    {
        return ['sii_status', 'sii_sent'];
    }

    public function renderField(Translator $i18n, SalesDocument $model, string $field): ?string
    {
        switch ($field) {
            case 'sii_status':
                return $this->siiStatus($i18n, $model);

            case 'sii_sent':
                return $this->siiSent($i18n, $model);

            default:
                return null;
        }
    }

    private function siiSent(Translator $i18n, SalesDocument $model): string
    {
        $value = empty($model->sii_sent) ? '' : date('Y-m-d', strtotime($model->sii_sent));
        return empty($model->subjectColumnValue()) || $model->modelClassName() !== 'FacturaCliente'
            ? ''
            : '<div class="col-sm-6">'
            . '<div class="form-group">'
            . $i18n->trans('sii-sent')
            . '<input type="datetime-local" class="form-control" value="' . $value . '" disabled/>'
            . '</div>'
            . '</div>';
    }

    private function siiStatus(Translator $i18n, SalesDocument $model): string
    {
        return empty($model->subjectColumnValue()) || $model->modelClassName() !== 'FacturaCliente'
            ? ''
            : '<div class="col-sm-6">'
            . '<div class="form-group">'
            . $i18n->trans('sii-status')
            . '<input type="text" class="form-control" value="' . $model->sii_status . '" disabled/>'
            . '</div>'
            . '</div>';
    }
}