<?php
/**
 * Copyright (C) 2019-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\EnviarDocumentos\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Model\Base\BusinessDocument;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\EmailNotification;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class SendPendingDocs extends Controller
{
    private const MODEL_NAMESPACE = '\\FacturaScripts\\Dinamic\\Model\\';

    /**
     * List of records with data.
     *
     * @var BusinessDocument[]
     */
    public $cursor;

    /**
     * Document type object.
     *
     * @var BusinessDocument
     */
    public $model;

    /**
     * Link to the mailing class.
     *
     * @var NewMail
     */
    public $newMail;

    /**
     * List of document ids preselected by the user.
     *
     * @var string
     */
    private $modelIds;

    /**
     * Class name of the document type.
     *
     * @var string
     */
    private $modelName;

    /**
     * Container of the data necessary to send the email.
     *
     * @var array
     */
    private $mailData;

    /**
     * Determines the initial state of the checkboxes.
     *
     * @return bool
     */
    public function checkedInitial(): bool
    {
        return false === empty($this->modelIds);
    }

    /**
     * List of columns and fields to be displayed in the list of documents.
     *
     * @return array
     */
    public function getColumns(): array
    {
        return [
            ['title' => 'code', 'field' => 'codigo'],
            ['title' => 'number2', 'field' => 'numero2'],
            ['title' => 'name', 'field' => 'nombrecliente'],
            ['title' => 'email', 'field' => 'email'],
            ['title' => 'observations', 'field' => 'observaciones'],
            ['title' => 'total', 'field' => 'total'],
            ['title' => 'date', 'field' => 'fecha']
        ];
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'send-docs';
        $data['icon'] = 'fas fa-envelope';
        $data['menu'] = 'sales';
        $data['showonmenu'] = false;

        return $data;
    }

    /**
     * Runs the controller's private logic.
     *
     * @param Response $response
     * @param User $user
     * @param ControllerPermissions $permissions
     */
    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->init();

        $action = $this->request->get('action', '');
        if ($action === 'send' && $this->sendAction()) {
            $this->redirect($this->model->url('list'));
            return;
        }

        $this->processData();
        $this->loadData();
    }

    /**
     * Prepare attributes values
     */
    protected function init(): void
    {
        $this->modelIds = $this->request->get('ids', '');
        $this->modelName = $this->request->get('doc', 'FacturaCliente');

        $model = self::MODEL_NAMESPACE . $this->modelName;
        $this->model = new $model();
        $this->cursor = [];

        $this->newMail = new NewMail();
        $this->newMail->setUser($this->user);
        if (false === $this->newMail->canSendMail()) {
            $this->toolBox()->i18nLog()->warning('email-not-configured');
        }
    }

    /**
     * Loads the data to display.
     */
    protected function loadData(): void
    {
        $field = $this->model->primaryColumn();
        $where = (empty($this->modelIds))
            ? [new DataBaseWhere('femail', null)]
            : [new DataBaseWhere($field, $this->modelIds, 'IN')];

        $this->cursor = $this->model->all($where, [$field => 'DESC']);
    }

    /**
     * Recover data from previous form.
     */
    protected function processData(): void
    {
        // buscamos el texto de la notificación para usar el asunto y el cuerpo
        $notificationModel = new EmailNotification();
        $where = [
            new DataBaseWhere('name', 'sendmail-' . $this->model->modelClassName()),
            new DataBaseWhere('enabled', true)
        ];
        if ($notificationModel->loadFromCode('', $where)) {
            $this->newMail->title = $notificationModel->subject;
            $this->newMail->text = $notificationModel->body;
            return;
        }

        // si no hay notificación, usamos los datos de las traducciones
        switch ($this->model->modelClassName()) {
            case 'AlbaranCliente':
                $this->newMail->title = $this->toolBox()->i18n()->trans('delivery-note-email-subject', ['%code%' => $this->model->codigo]);
                $this->newMail->text = $this->toolBox()->i18n()->trans('delivery-note-email-text', ['%code%' => $this->model->codigo]);
                break;

            case 'FacturaCliente':
                $this->newMail->title = $this->toolBox()->i18n()->trans('invoice-email-subject', ['%code%' => $this->model->codigo]);
                $this->newMail->text = $this->toolBox()->i18n()->trans('invoice-email-text', ['%code%' => $this->model->codigo]);
                break;

            case 'PedidoCliente':
                $this->newMail->title = $this->toolBox()->i18n()->trans('order-email-subject', ['%code%' => $this->model->codigo]);
                $this->newMail->text = $this->toolBox()->i18n()->trans('order-email-text', ['%code%' => $this->model->codigo]);
                break;

            case 'PresupuestoCliente':
                $this->newMail->title = $this->toolBox()->i18n()->trans('estimation-email-subject', ['%code%' => $this->model->codigo]);
                $this->newMail->text = $this->toolBox()->i18n()->trans('estimation-email-text', ['%code%' => $this->model->codigo]);
                break;
        }

        $this->newMail->title = str_replace('%code%', '{code}', $this->newMail->title);
        $this->newMail->text = str_replace('%code%', '{code}', $this->newMail->text);
    }

    /**
     * Process to send the documents by mail.
     *
     * @return bool
     */
    protected function sendAction(): bool
    {
        $this->mailData['code'] = $this->request->request->get('code', []);
        $this->mailData['subject'] = $this->request->request->get('subject', '');
        $this->mailData['body'] = $this->request->request->get('body', '');
        $this->mailData['from'] = $this->request->request->get('email-from', '');
        if (false === $this->checkParams()) {
            return false;
        }

        // Main Process
        foreach ($this->mailData['code'] as $iddoc) {
            if (false == $this->model->loadFromCode($iddoc)) {
                self::toolBox()->i18nLog()->error('record-no-found');
                continue;
            }

            // Generate tmp doc
            $title = $this->modelName . '_' . $this->model->codigo;
            $fileName = strtolower($this->modelName) . '_' . time() . '.pdf';
            if (false === $this->generatePDF($title, $fileName)) {
                self::toolBox()->i18nLog()->error('pdf-generation-error');
                return false;
            }

            // Send mail
            if (false === $this->sendMail($this->model->getSubject(), $fileName)) {
                self::toolBox()->i18nLog()->error('email-error');
                return false;
            }

            // Actualizar femail.
            $this->model->femail = date(BusinessDocument::DATE_STYLE);
            $this->model->save();

            // Delete tmp doc
            $fileName = FS_FOLDER . '/MyFiles/' . $fileName;
            if (file_exists($fileName)) {
                unlink($fileName);
            }
        }

        return true;
    }

    /**
     * Checks the values entered by the user.
     *
     * @return bool
     */
    private function checkParams(): bool
    {
        if (empty($this->mailData['code'])) {
            $this->toolBox()->i18nLog()->warning('no-selected-item');
            return false;
        }

        if (empty($this->mailData['subject']) ||
            empty($this->mailData['body']) ||
            empty($this->mailData['from'])) {
            $this->toolBox()->i18nLog()->warning('mail-data-required');
            return false;
        }

        return true;
    }

    /**
     * Generate the document in PDF and save it in a temporary file.
     *
     * @param string $title
     * @param string $fileName
     *
     * @return bool
     */
    private function generatePDF($title, $fileName): bool
    {
        $exportManager = new ExportManager();
        $exportManager->newDoc('PDF', $title);
        $exportManager->addBusinessDocPage($this->model);

        $filePath = FS_FOLDER . '/MyFiles/' . $fileName;
        if (false === file_put_contents($filePath, $exportManager->getDoc())) {
            $this->toolBox()->i18nLog()->error('folder-not-writable');
            return false;
        }

        return true;
    }

    /**
     * Send an email with an attachment.
     *
     * @param Cliente $subjectDoc
     * @param string $fileName
     *
     * @return bool
     */
    private function sendMail($subjectDoc, $fileName): bool
    {
        $newMail = new NewMail();
        $newMail->fromNick = $this->user->nick;
        $newMail->addReplyTo($this->user->email, $this->user->nick);

        $shortCodes = ['{code}', '{name}', '{date}', '{total}'];
        $shortValues = [$this->model->codigo, $this->model->nombrecliente, $this->model->fecha, $this->model->total];
        $newMail->title = str_replace($shortCodes, $shortValues, $this->mailData['subject']);
        $newMail->text = str_replace($shortCodes, $shortValues, $this->mailData['body']);

        $newMail->setMailbox($this->mailData['from']);

        $newMail->addAddress($subjectDoc->email);
        if (isset($subjectDoc->emailto) && false === empty($subjectDoc->emailto)) {
            foreach (NewMail::splitEmails($subjectDoc->emailto) as $email) {
                $newMail->addAddress($email);
            }
        }

        if (isset($subjectDoc->emailcc) && false === empty($subjectDoc->emailcc)) {
            foreach (NewMail::splitEmails($subjectDoc->emailcc) as $email) {
                $newMail->addCC($email);
            }
        }

        if (isset($subjectDoc->emailbcc) && false === empty($subjectDoc->emailbcc)) {
            foreach (NewMail::splitEmails($subjectDoc->emailbcc) as $email) {
                $newMail->addBCC($email);
            }
        }

        $newMail->addAttachment(FS_FOLDER . '/MyFiles/' . $fileName, $fileName);
        return $newMail->send();
    }
}
