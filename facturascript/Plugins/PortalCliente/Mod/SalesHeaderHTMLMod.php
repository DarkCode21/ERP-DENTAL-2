<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Mod;

use FacturaScripts\Core\Base\Contract\SalesModInterface;
use FacturaScripts\Core\Base\Translator;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Model\User;
use FacturaScripts\Plugins\PortalCliente\Lib\Pay\PaypalApi;
use FacturaScripts\Plugins\PortalCliente\Lib\Pay\RedsysApi;
use FacturaScripts\Plugins\PortalCliente\Lib\Pay\StripeApi;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class SalesHeaderHTMLMod implements SalesModInterface
{

    public function apply(SalesDocument &$model, array $formData, User $user)
    {
        $model->pc_paid = $formData['pc_paid'] ?? $model->pc_paid;
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
        return ['uuid', 'paymentOnline', 'paymentStripe', 'paymentPaypal', 'paymentRedsys'];
    }

    public function renderField(Translator $i18n, SalesDocument $model, string $field): ?string
    {
        return match ($field) {
            'uuid' => $this->uuid($i18n, $model),
            'paymentOnline' => $this->paymentOnline($i18n, $model),
            'paymentStripe' => $this->paymentStripe($i18n, $model),
            'paymentPaypal' => $this->paymentPaypal($i18n, $model),
            'paymentRedsys' => $this->paymentRedsys($i18n, $model),
            default => null,
        };

    }

    private function paymentOnline(Translator $i18n, SalesDocument $model): string
    {
        $attributes = $model->editable ? '' : 'disabled';
        return '<div class="col-sm-6">'
            . '<div class="form-group">'
            . $i18n->trans('paid-online')
            . '<select name="pc_paid" class="form-control" ' . $attributes . '>'
            . '<option value="0" ' . ($model->pc_paid ? '' : 'selected') . '>'
            . $i18n->trans('no')
            . '</option>'
            . '<option value="1" ' . ($model->pc_paid ? 'selected' : '') . '>'
            . $i18n->trans('yes')
            . '</option>'
            . '</select>'
            . '</div>'
            . '</div>';
    }

    private function paymentPaypal(Translator $i18n, SalesDocument $model): string
    {
        if (empty($model->pc_payment_paypal)) {
            return '';
        }

        $link = $model->exists() && false === empty($model->pc_payment_paypal)
            ? '<a href="' . PaypalApi::urlDashboard($model->pc_payment_paypal) . '" target="_blank">'
            . $i18n->trans('payment-paypal') . '</a>'
            : $i18n->trans('payment-paypal');

        return '<div class="col-sm-6">'
            . '<div class="form-group">'
            . $link
            . '<input type="text" value="' . $model->pc_payment_paypal . '" class="form-control" disabled>'
            . '</div>'
            . '</div>';
    }

    private function paymentRedsys(Translator $i18n, SalesDocument $model): string
    {
        if (empty($model->pc_payment_redsys)) {
            return '';
        }

        $link = $model->exists() && false === empty($model->pc_payment_redsys)
            ? '<a href="' . RedsysApi::urlDashboard($model->pc_payment_redsys) . '" target="_blank">'
            . $i18n->trans('payment-redsys') . '</a>'
            : $i18n->trans('payment-redsys');

        return '<div class="col-sm-6">'
            . '<div class="form-group">'
            . $link
            . '<input type="text" value="' . $model->pc_payment_redsys . '" class="form-control" disabled>'
            . '</div>'
            . '</div>';
    }

    private function paymentStripe(Translator $i18n, SalesDocument $model): string
    {
        if (empty($model->pc_payment_intent_stripe)) {
            return '';
        }

        $link = $model->exists() && false === empty($model->pc_payment_intent_stripe)
            ? '<a href="' . StripeApi::urlDashboard($model->getCompany(), 'payments/' . $model->pc_payment_intent_stripe) . '" target="_blank">'
            . $i18n->trans('payment-stripe') . '</a>'
            : $i18n->trans('payment-stripe');

        return '<div class="col-sm-6">'
            . '<div class="form-group">'
            . $link
            . '<input type="text" value="' . $model->pc_payment_intent_stripe . '" class="form-control" disabled>'
            . '</div>'
            . '</div>';
    }

    private function uuid(Translator $i18n, SalesDocument $model): string
    {
        if (empty($model->pc_uuid)) {
            return '';
        }

        return '<div class="col-sm-6">'
            . '<div class="form-group">'
            . $i18n->trans('uuid')
            . '<input type="text" value="' . $model->pc_uuid . '" class="form-control" disabled>'
            . '</div>'
            . '</div>';
    }
}