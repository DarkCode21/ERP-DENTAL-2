<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Lib\Pay;

use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Plugins\PortalCliente\Contract\PortalPaymentGatewayInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalPaymentGateway
{
    /** @var PortalPaymentGatewayInterface[] */
    protected static $gateways = [];

    public static function getHtml(SalesDocument $model, Contacto $contact): string
    {
        $html = '';
        foreach (static::$gateways as $gateway) {
            $html .= $gateway->getHtml($model, $contact);
        }
        return $html;
    }

    public static function payAction(SalesDocument &$model, Request $request): bool
    {
        $platform = $request->get('platform', '');
        foreach (static::$gateways as $gateway) {
            if ($gateway->name() === $platform) {
                return $gateway->payAction($model, $request);
            }
        }

        $modelName = match ($model->modelClassName()) {
            'AlbaranCliente' => Tools::lang()->trans('delivery-note'),
            'FacturaCliente' => Tools::lang()->trans('invoice'),
            'PedidoCliente' => Tools::lang()->trans('order'),
            'PresupuestoCliente' => Tools::lang()->trans('estimation'),
            default => 'document',
        };

        Tools::log()->critical('no-payment-gateway : ' . $modelName . ' #' . $model->primaryColumnValue());
        return false;
    }

    public static function register(PortalPaymentGatewayInterface $gateway): void
    {
        static::$gateways[] = $gateway;
    }
}
