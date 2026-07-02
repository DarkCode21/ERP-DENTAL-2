<?php

namespace FacturaScripts\Plugins\Dental\Extension\Controller;

use Closure;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\User AS DinUser;

class EditEspecialista
{
    protected function execAfterAction($action)
    {
        return function ($action) {
            if ($action === 'link-user') {
                return $this->linkUserAction();
            }
            if ($action === 'create-user') {
                return $this->createUserAction();
            }
            if ($action === 'unlink-user') {
                return $this->unlinkUserAction();
            }
            return parent::execAfterAction($action);
        };
    }

    protected function loadData($viewName, $view)
    {
        return function ($viewName, $view) {
            parent::loadData($viewName, $view);

            if ($viewName === $this->getMainViewName()) {
                $this->loadLinkedUser();
            }
        };
    }

    private function loadLinkedUser(): void
    {
        $mainView = $this->getMainViewName();
        $codusuario = $this->getViewModelValue($mainView, 'codusuario');

        if (!empty($codusuario)) {
            $user = new DinUser();
            if ($user->loadFromCode($codusuario)) {
                $this->views[$mainView]->model->linkedUser = $user;
                $this->views[$mainView]->model->linkedUserStatus = $user->enabled ? 'user-linked' : 'inactive';
            } else {
                $this->views[$mainView]->model->linkedUserStatus = 'user-not-linked';
            }
        } else {
            $this->views[$mainView]->model->linkedUserStatus = 'user-not-linked';
        }
    }

    private function linkUserAction(): bool
    {
        $nick = $this->request->request->get('nick', '');
        if (empty($nick)) {
            Tools::log()->warning('user-not-found');
            return true;
        }

        $user = new DinUser();
        if (!$user->loadFromCode($nick)) {
            Tools::log()->warning('user-not-found');
            return true;
        }

        $codrole = $this->request->request->get('codrole', '');
        if (!empty($codrole)) {
            $user->addRole($codrole);
        }

        $mainView = $this->getMainViewName();
        $code = $this->request->request->get('code', '');
        $model = $this->views[$mainView]->model;
        $model->loadFromCode($code);
        $model->codusuario = $nick;

        if (!$model->test()) {
            Tools::log()->error('record-save-error');
            return true;
        }

        if (!$model->save()) {
            Tools::log()->error('record-save-error');
            return true;
        }

        Tools::log()->notice('record-saved-correctly');
        return true;
    }

    private function createUserAction(): bool
    {
        $nick = $this->request->request->get('new_nick', '');
        $email = $this->request->request->get('new_email', '');
        $password = $this->request->request->get('new_password', '');
        $codrole = $this->request->request->get('new_codrole', '');
        $isOdontologo = $this->request->request->get('is_odontologo', false);

        if (empty($nick) || empty($password)) {
            Tools::log()->warning('invalid-data');
            return true;
        }

        $user = new DinUser();
        $user->nick = $nick;
        $user->email = $email ?: $nick . '@local.local';
        $user->newPassword = $password;
        $user->newPassword2 = $password;
        $user->enabled = true;
        $user->admin = false;

        if (!$user->test()) {
            Tools::log()->error('record-save-error');
            return true;
        }

        if (!$user->save()) {
            Tools::log()->error('record-save-error');
            return true;
        }

        if (!empty($codrole)) {
            $user->addRole($codrole);
        } elseif ($isOdontologo) {
            $user->addRole('odontologo');
        }

        $mainView = $this->getMainViewName();
        $code = $this->request->request->get('code', '');
        $model = $this->views[$mainView]->model;
        $model->loadFromCode($code);
        $model->codusuario = $nick;

        if (!$model->test()) {
            Tools::log()->error('record-save-error');
            return true;
        }

        if (!$model->save()) {
            Tools::log()->error('record-save-error');
            return true;
        }

        Tools::log()->notice('record-saved-correctly');
        return true;
    }

    private function unlinkUserAction(): bool
    {
        $mainView = $this->getMainViewName();
        $code = $this->request->request->get('code', '');
        $model = $this->views[$mainView]->model;
        $model->loadFromCode($code);

        $model->codusuario = null;

        if (!$model->test()) {
            Tools::log()->error('record-save-error');
            return true;
        }

        if (!$model->save()) {
            Tools::log()->error('record-save-error');
            return true;
        }

        Tools::log()->notice('record-saved-correctly');
        return true;
    }
}
