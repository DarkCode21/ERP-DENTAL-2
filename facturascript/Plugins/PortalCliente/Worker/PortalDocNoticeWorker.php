<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Worker;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\Contact;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Model\WorkEvent;
use FacturaScripts\Core\Template\WorkerClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Email\ButtonBlock;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Lib\Email\SpaceBlock;
use FacturaScripts\Dinamic\Lib\Email\TextBlock;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\RoleUser;
use FacturaScripts\Dinamic\Model\User;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalDocNoticeWorker extends WorkerClass
{
    public function run(WorkEvent $event): bool
    {
        // obtenemos los parámetros del evento
        $params = $event->params();

        // campos obligatorios
        $fields = ['model_name', 'notice_name'];

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

        // obtenemos el cliente del documento
        $client = $model->getSubject();
        if (false === $client->exists()) {
            return $this->done();
        }

        $emails = [];

        // obtenemos el email del agente del cliente
        $this->getEmailAgent($client, $emails);

        // obtenemos el email del agente del contacto de la dirección de facturación
        $this->getEmailAgentContactBilling($model, $emails);

        // obtenemos los emails de los usuarios del grupo solicitado
        $this->getEmailUserGroup($params['notice_name'], $emails);

        foreach ($emails as $email => $langcode) {
            // preparamos el email
            $newEmail = NewMail::create()
                ->to($email);

            // añadimos el contenido del email y lo enviamos
            if ($this->getEmailContent($newEmail, $model, $params['notice_name'], $langcode)) {
                $newEmail->send();
            }
        }

        return $this->done();
    }

    private function getEmailAgent(Contact $client, array &$emails): void
    {
        $agent = new Agente();
        if (false === $agent->loadFromCode($client->codagente)) {
            return;
        }

        if (false === in_array($agent->email, array_keys($emails))) {
            $emails[$agent->email] = $agent->langcode;
        }
    }

    private function getEmailAgentContactBilling(SalesDocument $model, array &$emails): void
    {
        $contact = new Contacto();
        if (false === $contact->loadFromCode($model->idcontactofact)) {
            return;
        }

        $agent = new Agente();
        if (false === $agent->loadFromCode($contact->codagente)) {
            return;
        }

        if (false === in_array($agent->email, array_keys($emails))) {
            $emails[$agent->email] = $agent->langcode;
        }
    }

    private function getEmailContent(&$newEmail, SalesDocument $model, string $type, ?string $langcode): bool
    {
        switch ($type) {
            case 'group_approve_estimations':
                $newEmail->subject(Tools::lang($langcode)->trans('estimation-approved-by-customer', ['%code%' => $model->codigo]))
                    ->addMainBlock(new TextBlock(Tools::lang($langcode)->trans('estimation-approved-by-customer', ['%code%' => $model->codigo])))
                    ->addMainBlock(new SpaceBlock(10))
                    ->addMainBlock(new ButtonBlock(Tools::lang($langcode)->trans('view-estimation'), Tools::siteUrl() . "/" . $model->url()));
                return true;

            case 'group_cancel_estimations':
                $newEmail->subject(Tools::lang($langcode)->trans('estimation-canceled-by-customer', ['%code%' => $model->codigo]))
                    ->addMainBlock(new TextBlock(Tools::lang($langcode)->trans('estimation-canceled-by-customer', ['%code%' => $model->codigo])))
                    ->addMainBlock(new SpaceBlock(10))
                    ->addMainBlock(new ButtonBlock(Tools::lang($langcode)->trans('view-estimation'), Tools::siteUrl() . "/" . $model->url()));
                return true;

            case 'group_cancel_orders':
                $newEmail->subject(Tools::lang($langcode)->trans('order-canceled-by-customer', ['%code%' => $model->codigo]))
                    ->addMainBlock(new TextBlock(Tools::lang($langcode)->trans('order-canceled-by-customer', ['%code%' => $model->codigo])))
                    ->addMainBlock(new SpaceBlock(10))
                    ->addMainBlock(new ButtonBlock(Tools::lang($langcode)->trans('view-order'), Tools::siteUrl() . "/" . $model->url()));
                return true;

            default:
                return false;
        }
    }

    private function getEmailUserGroup(string $type, array &$emails): void
    {
        $userGroup = Tools::settings('portalcliente', $type);
        if (empty($userGroup)) {
            return;
        }

        $roleUserModel = new RoleUser();
        $where = [new DataBaseWhere('codrole', $userGroup)];
        foreach ($roleUserModel->all($where, [], 0, 0) as $roleUser) {
            $user = new User();
            if (false === $user->loadFromCode($roleUser->nick)) {
                continue;
            }

            if (false === in_array($user->email, array_keys($emails))) {
                $emails[$user->email] = $user->langcode;
            }
        }
    }
}