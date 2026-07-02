<?php
/**
 * Copyright (C) 2023-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\FacturasCompraUniq\Mod;

use FacturaScripts\Core\Base\Contract\PurchasesModInterface;
use FacturaScripts\Core\Base\Translator;
use FacturaScripts\Core\Model\Base\PurchaseDocument;
use FacturaScripts\Core\Model\User;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PurchasesHeaderHTMLMod implements PurchasesModInterface
{
    public function apply(PurchaseDocument &$model, array $formData, User $user)
    {
        if ($model->modelClassName() === 'FacturaProveedor') {
            $model->fechaprov = isset($formData['fechaprov']) && !empty($formData['fechaprov']) ? $formData['fechaprov'] : null;
        }
    }

    public function applyBefore(PurchaseDocument &$model, array $formData, User $user)
    {
    }

    public function assets(): void
    {
    }

    public function newFields(): array
    {
        return ['fechaprov'];
    }

    public function newBtnFields(): array
    {
        return [];
    }

    public function newModalFields(): array
    {
        return [];
    }

    public function renderField(Translator $i18n, PurchaseDocument $model, string $field): ?string
    {
        if ($field === 'fechaprov') {
            return $this->fechaprov($i18n, $model);
        }

        return null;
    }

    private function fechaprov(Translator $i18n, PurchaseDocument $model): string
    {
        if ($model->modelClassName() !== 'FacturaProveedor') {
            return '';
        }

        $attributes = $model->editable ? 'name="fechaprov"' : 'disabled=""';
        $date = $model->fechaprov ? date('Y-m-d', strtotime($model->fechaprov)) : '';
        return '<div class="col-sm-2 col-lg">'
            . '<div class="form-group">'
            . $i18n->trans('date-supplier')
            . '<input type="date" value="' . $date . '" ' . $attributes . ' class="form-control">'
            . '</div>'
            . '</div>';
    }
}
