<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Worker;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Model\WorkEvent;
use FacturaScripts\Core\Template\WorkerClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Email\ButtonBlock;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Lib\Email\SpaceBlock;
use FacturaScripts\Dinamic\Lib\Email\TextBlock;
use FacturaScripts\Dinamic\Model\RoleUser;
use FacturaScripts\Dinamic\Model\User;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalDocPaymentAdminWorker extends WorkerClass
{
    public function run(WorkEvent $event): bool
    {
        // obtenemos los parámetros del evento
        $params = $event->params();

        // campos obligatorios
        $fields = ['model_name', 'payment_success'];

        // si no se han pasado los campos obligatorios, terminamos
        foreach ($fields as $field) {
            if (false === isset($params[$field])) {
                return $this->done();
            }
        }

        // obtenemos la clase del modelo
        $modelClass = 'FacturaScripts\\Dinamic\Model\\' . $params['model_name'];
        if (false === class_exists($modelClass)) {
            return $this->done();
        }

        // cargamos el modelo
        $model = new $modelClass();
        if (false === $model->loadFromCode($event->value)) {
            return $this->done();
        }

        // si no es una instancia de SalesDocument, terminamos
        if (false === $model instanceof SalesDocument) {
            return $this->done();
        }

        switch ($params['model_name']) {
            case 'AlbaranCliente':
                $subject = 'delivery-note-payment-made';
                $messageSuccess = 'delivery-note-payment-made-success';
                $messageFail = 'delivery-note-payment-made-failed';
                $userGroup = Tools::settings('portalcliente', 'group_payment_delivery_notes');
                break;

            case 'FacturaCliente':
                $subject = 'invoice-payment-made';
                $messageSuccess = 'invoice-payment-made-success';
                $messageFail = 'invoice-payment-made-failed';
                $userGroup = Tools::settings('portalcliente', 'group_payment_invoices');
                break;

            case 'PedidoCliente':
                $subject = 'order-payment-made';
                $messageSuccess = 'order-payment-made-success';
                $messageFail = 'order-payment-made-failed';
                $userGroup = Tools::settings('portalcliente', 'group_payment_orders');
                break;

            case 'PresupuestoCliente':
                $subject = 'estimation-payment-made';
                $messageSuccess = 'estimation-payment-made-success';
                $messageFail = 'estimation-payment-made-failed';
                $userGroup = Tools::settings('portalcliente', 'group_payment_estimations');
                break;
        }

        if (empty($userGroup)) {
            return $this->done();
        }

        $roleUserModel = new RoleUser();
        $where = [new DataBaseWhere('codrole', $userGroup)];
        foreach ($roleUserModel->all($where, [], 0, 0) as $roleUser) {
            $user = new User();
            if (false === $user->loadFromCode($roleUser->nick)) {
                continue;
            }

            // preparamos y enviamos el email
            $mail = NewMail::create()
                ->to($user->email)
                ->subject(Tools::lang($user->langcode)->trans($subject, ['%code%' => $model->codigo]));

            if ((bool)$params['payment_success']) {
                $mail->addMainBlock(new TextBlock(Tools::lang($user->langcode)->trans($messageSuccess, ['%code%' => $model->codigo])));
            } else {
                $mail->addMainBlock(new TextBlock(Tools::lang($user->langcode)->trans($messageFail, ['%code%' => $model->codigo])));
            }

            $mail->addMainBlock(new SpaceBlock(10))
                ->addMainBlock(new ButtonBlock(Tools::lang($user->langcode)->trans('view-document'), Tools::siteUrl() . "/" . $model->url()))
                ->send();
        }

        return $this->done();
    }
}