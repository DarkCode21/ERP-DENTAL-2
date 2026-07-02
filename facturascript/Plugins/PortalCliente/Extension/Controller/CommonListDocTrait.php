<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;

trait CommonListDocTrait
{
    public function createViews(): Closure
    {
        return function () {
            $mvn = $this->getMainViewName();
            $viewNames = ['ListPresupuestoCliente', 'ListPedidoCliente', 'ListAlbaranCliente', 'ListFacturaCliente'];
            foreach ($viewNames as $viewName) {
                if ($viewName !== $mvn || false === $this->user->can($viewName)) {
                    continue;
                }

                $this->listView($viewName)
                    ->addSearchFields(['pc_uuid'])
                    ->addFilterCheckbox('pc_created', 'created-in-client-portal', 'pc_created')
                    ->addFilterSelectWhere('paid-online', [
                        ['label' => Tools::lang()->trans('paid-online'), 'where' => []],
                        ['label' => '------', 'where' => []],
                        ['label' => Tools::lang()->trans('redsys'), 'where' => [
                            new DataBaseWhere('pc_paid', true),
                            new DataBaseWhere('pc_payment_redsys', null, 'IS NOT')
                        ]],
                        ['label' => Tools::lang()->trans('paypal'), 'where' => [
                            new DataBaseWhere('pc_paid', true),
                            new DataBaseWhere('pc_payment_paypal', null, 'IS NOT')
                        ]],
                        ['label' => Tools::lang()->trans('stripe'), 'where' => [
                            new DataBaseWhere('pc_paid', true),
                            new DataBaseWhere('pc_payment_intent_stripe', null, 'IS NOT')
                        ]],
                        ['label' => Tools::lang()->trans('manual'), 'where' => [
                            new DataBaseWhere('pc_paid', true),
                            new DataBaseWhere('pc_payment_redsys', null),
                            new DataBaseWhere('pc_payment_paypal', null),
                            new DataBaseWhere('pc_payment_intent_stripe', null)
                        ]]
                    ]);
            }
        };
    }
}