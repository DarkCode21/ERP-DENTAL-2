<?php
/**
 * Copyright (C) 2024-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PrePagos\Mod;

use FacturaScripts\Core\Base\Contract\SalesModInterface;
use FacturaScripts\Core\Base\Translator;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Model\User;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class SalesFooterHTMLMod implements SalesModInterface
{
    public function apply(SalesDocument &$model, array $formData, User $user)
    {
    }

    public function applyBefore(SalesDocument &$model, array $formData, User $user): void
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
        return ['total_pending'];
    }

    public function newModalFields(): array
    {
        return [];
    }

    public function renderField(Translator $i18n, SalesDocument $model, string $field): ?string
    {
        if ($field === 'total_pending') {
            return self::totalPending($i18n, $model);
        }

        return null;
    }

    private static function totalPending(Translator $i18n, SalesDocument $model): string
    {
        if (false === property_exists($model, 'total_pending')) {
            return '';
        }

        return '<div class="col-sm-2 order-last">'
            . '<div class="form-group">' . $i18n->trans('total-pending')
            . '<input type="number" value="' . $model->total_pending . '" class="form-control" readonly/>'
            . '</div>'
            . '</div>';
    }
}
