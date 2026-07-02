<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\AssetManager;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\PortalViewController;
use FacturaScripts\Plugins\PortalCliente\Model\PortalTicket as ModelPortalTicket;
use FacturaScripts\Plugins\PortalCliente\Model\PortalTicketComment;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalTicket extends PortalViewController
{
    const MIN_TICKET_LEN = 40;

    public $newTicket = false;

    public $text = '';

    public function getModelClassName(): string
    {
        return 'PortalTicket';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'PortalCliente';
        $data['title'] = 'support-ticket';
        $data['icon'] = 'far fa-comment-dots';
        return $data;
    }

    protected function addCommentAction(): bool
    {
        $model = $this->preloadModel();
        if (false === $model->exists()) {
            $this->redirect('PortalCliente');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        } elseif (false === $this->permissions->allowAccess) {
            Tools::log()->warning('access-denied');
            return true;
        } elseif ($model->closed) {
            return true;
        }

        $ticket = $this->preloadModel();
        $numComments = (int)$this->request->request->get('num_comments', '0');
        if ($numComments < $ticket->num_comments) {
            Tools::log()->warning('there-are-new-comments-while-you-were-writing');
            return true;
        }

        $comment = new PortalTicketComment();
        $comment->body = $this->request->request->get('body');
        if (mb_strlen($comment->body) < self::MIN_TICKET_LEN) {
            $this->text = $comment->body;
            Tools::log()->warning('comment-too-short');
            return true;
        }

        if ($this->user) {
            $comment->nick = $this->user->nick;
        } else {
            $comment->idcontacto = $this->contact->idcontacto;
        }

        if (false === $ticket->addComment($comment)) {
            Tools::log()->error('record-save-error');
            return true;
        }

        foreach ($this->request->files->get('uploads', []) as $file) {
            $comment->addFile($file->getPathname(), $file->getClientOriginalName());
        }

        $url = $ticket->url('public');
        $urlTail = 'rand=' . mt_rand(0, 999) . '#comm' . $comment->primaryColumnValue();
        $this->redirect(!str_contains($url, '?') ? $url . '?' . $urlTail : $url . '&' . $urlTail);
        return true;
    }

    protected function closeTicketAction(): bool
    {
        $model = $this->preloadModel();
        if (false === $model->exists()) {
            $this->redirect('PortalCliente');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        } elseif (false === $this->permissions->allowAccess) {
            Tools::log()->warning('access-denied');
            return true;
        } elseif ($model->closed) {
            return true;
        }

        $model->closed = true;
        if (false === $model->save()) {
            Tools::log()->error('record-save-error');
            return true;
        }

        Tools::log()->notice('record-updated-correctly');
        return true;
    }

    protected function createAction(): bool
    {
        if (false === $this->validateFormToken()) {
            return true;
        }

        $ticket = new ModelPortalTicket();
        $ticket->idcontacto = $this->contact->idcontacto;
        $ticket->body = $this->request->request->get('body');
        if (mb_strlen($ticket->body) < self::MIN_TICKET_LEN) {
            $this->text = $ticket->body;
            Tools::log()->warning('ticket-too-short');
            return true;
        }

        if (false === $ticket->save()) {
            Tools::log()->warning('record-save-error');
            return true;
        }

        foreach ($this->request->files->get('uploads', []) as $file) {
            $ticket->addFile($file->getPathname(), $file->getClientOriginalName());
        }

        $this->redirect($ticket->url('public'));
        return true;
    }

    protected function createViews()
    {
        // comprobamos si no estamos creando un nuevo ticket
        // y existe el modelo
        $this->newTicket = empty($this->request->get('code'));
        if (false === $this->newTicket && false === $this->preloadModel()->exists()) {
            return $this->error404();
        }

        AssetManager::addCss('Plugins/PortalCliente/node_modules/easymde/dist/easymde.min.css');
        AssetManager::addJs('Plugins/PortalCliente/node_modules/easymde/dist/easymde.min.js');

        // comprobamos si estamos creando un nuevo ticket
        // y existe el contacto
        if ($this->newTicket && $this->contact) {
            parent::createViews();
            $this->addHtmlView('newTicket', 'Tab/PortalTicketNew', 'PortalTicket', 'new', 'fas fa-plus');
            return;
        }

        $this->setContactPermissions();
        if (false === $this->permissions->allowAccess) {
            return $this->error403();
        }

        parent::createViews();
        $this->addHtmlView('comments', 'Tab/PortalTicketComment', 'PortalTicketComment', 'comments', 'far fa-comments');
    }

    /**
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'add-comment':
                return $this->addCommentAction();

            case 'close-ticket':
                return $this->closeTicketAction();

            case 'create':
                return $this->createAction();

            case 'markCommentRead':
                $this->setTemplate(false);
                $this->response->setContent(json_encode($this->markCommentReadAction()));
                return false;
        }

        return parent::execPreviousAction($action);
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $this->hasData = true;

        switch ($viewName) {
            case self::MAIN_VIEW_NAME:
                parent::loadData($viewName, $view);
                $this->title = $this->newTicket
                    ? Tools::lang()->trans('new-support-ticket')
                    : $view->model->title();
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    protected function markCommentReadAction(): array
    {
        $ticket = $this->preloadModel();
        if (false === $ticket->exists()) {
            return ['markCommentRead' => false];
        }

        // obtenemos el comentario
        $comment = new PortalTicketComment();
        $where = [
            new DataBaseWhere('id', $this->request->get('commID')),
            new DataBaseWhere('id_ticket', $ticket->id)
        ];

        // si no existe el comentario o ya está marcado como leído, no hacemos nada
        if (false === $comment->loadFromCode('', $where) || $comment->read) {
            return ['markCommentRead' => false];
        }

        // si hay usuario
        if ($this->user) {
            // si no hay contacto, no hacemos nada
            if (empty($comment->idcontacto)) {
                return ['markCommentRead' => false];
            }

            // marcamos el comentario como leído
            $comment->read = Tools::dateTime();
            if (false === $comment->save()) {
                return ['markCommentRead' => false];
            }

            return [
                'commID' => $comment->id,
                'markCommentRead' => true,
                'nick' => $comment->nick
            ];
        }

        // si hay contacto
        // si el creador del comentario es el contacto actual, no hacemos nada
        if ($comment->idcontacto === $this->contact->idcontacto) {
            return ['markCommentRead' => false];
        }

        // marcamos el comentario como leído
        $comment->read = Tools::dateTime();
        if (false === $comment->save()) {
            return ['markCommentRead' => false];
        }

        return [
        'commID' => $comment->id,
            'markCommentRead' => true,
            'read' => $comment->read
        ];
    }

    private function setContactPermissions(): void
    {
        // anónimo
        if (false === $this->contact->exists()) {
            $this->permissions->set(false, 0, false, false);
            return;
        }

        if ($this->user) {
            $this->permissions->set(true, 99, true, true);
            return;
        }

        // autor del ticket
        $model = $this->preloadModel();
        if ($model->idcontacto == $this->contact->idcontacto) {
            $this->permissions->set(true, 1, false, false);
            return;
        }

        // no autorizado
        $this->permissions->set(false, 0, false, false);
    }
}