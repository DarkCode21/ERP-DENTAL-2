<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Facturae\Controller;

use Exception;
use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Serie;
use FacturaScripts\Plugins\Facturae\Lib\FacturaeImporter;

class ImportFacturae extends Controller
{

    /** @var array */
    public $mail_list = [];

    /** @var Serie */
    public $serie;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'purchases';
        $data['title'] = 'import-factura-e';
        $data['icon'] = 'fa-solid fa-qrcode';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);

        $this->setTemplate('ImportFacturae/Default');

        $this->serie = new Serie();

        $action = $this->request->request->get('action', '');
        switch ($action) {
            case 'email-file':
                $this->emailFileAction();
                break;

            case 'read-emails':
                $this->readEmailsAction();
                break;

            case 'upload-file':
                $this->uploadFileAction();
                break;
        }
    }

    protected function emailFileAction(): void
    {
        // comprobamos los permisos de importación
        if (false === $this->permissions->allowImport) {
            Tools::log()->warning('no-import-permission');
            return;
        } elseif (false === $this->validateFormToken()) {
            return;
        }

        $codalmacen = $this->request->request->get('codalmacen');
        if (empty($codalmacen)) {
            Tools::log()->warning('no-warehouse-selected');
            return;
        }

        $codserie = $this->request->request->get('codserie');
        if (empty($codserie)) {
            Tools::log()->warning('no-serie-selected');
            return;
        }

        $file_name = $this->request->request->get('file_name');
        if (empty($file_name)) {
            Tools::log()->warning('no-file-selected');
            return;
        }

        $file_path = Tools::folder('MyFiles', 'facturae_mail', $file_name);
        if (false === file_exists($file_path)) {
            Tools::log()->warning('file-not-found');
            return;
        }

        if (false === FacturaeImporter::import($file_path, $codalmacen, $codserie)) {
            Tools::log()->warning('import-error');
            return;
        }

        Tools::log()->notice('record-save-ok');

        $invoice = FacturaeImporter::getLastInvoice();
        if (null !== $invoice) {
            $this->redirect($invoice->url(), 1);
        }
    }

    protected function readEmailFiles($inbox, $email_number, $part, $partNumber): array
    {
        $files = [];

        // Si esta parte tiene subpartes, recorrerlas recursivamente
        if (isset($part->parts) && count($part->parts)) {
            foreach ($part->parts as $subPartNumber => $subPart) {
                foreach ($this->readEmailFiles($inbox, $email_number, $subPart, $partNumber . '.' . ($subPartNumber + 1)) as $found) {
                    $files[] = $found;
                }
            }
        } // Verificar si esta parte es un adjunto
        elseif (isset($part->disposition) && strtolower($part->disposition) == 'attachment') {
            // Obtener información del adjunto
            $attachment = imap_fetchbody($inbox, $email_number, $partNumber);

            // Si está codificado en base64, decodificarlo
            if ($part->encoding == 3) {
                $attachment = base64_decode($attachment);
            } // Si está codificado quoted-printable, decodificarlo
            elseif ($part->encoding == 4) {
                $attachment = quoted_printable_decode($attachment);
            }

            // Obtener nombre del archivo
            $filename = '';
            if (isset($part->parameters)) {
                foreach ($part->parameters as $param) {
                    if (strtolower($param->attribute) == 'name') {
                        $filename = $param->value;
                        break;
                    }
                }
            }

            // Si no encontramos el nombre en parameters, buscar en dparameters
            if ($filename == '' && isset($part->dparameters)) {
                foreach ($part->dparameters as $param) {
                    if (strtolower($param->attribute) == 'filename') {
                        $filename = $param->value;
                        break;
                    }
                }
            }

            // Verificar si es un archivo XML o XSIG
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if ($extension == 'xml' || $extension == 'xsig') {
                $ruta = Tools::folder('MyFiles', 'facturae_mail');
                Tools::folderCheckOrCreate($ruta);

                $rutaCompleta = Tools::folder('MyFiles', 'facturae_mail', rand(10000, 99999) . '_' . $filename);

                // Guardar el archivo
                if (file_put_contents($rutaCompleta, $attachment)) {
                    $files[] = basename($rutaCompleta);
                } else {
                    Tools::log()->error("Error al guardar: {$filename}");
                }
            }
        }

        return $files;
    }

    protected function readEmailsAction(): void
    {
        $this->mail_list = [];

        // comprobamos los permisos de importación
        if (false === $this->permissions->allowImport) {
            Tools::log()->warning('no-import-permission');
            return;
        } elseif (false === $this->validateFormToken()) {
            return;
        }

        // comprobamos que la extensión IMAP esté habilitada
        if (false === extension_loaded('imap')) {
            Tools::log()->warning('no-imap-extension');
            return;
        }

        $mail_user = $this->request->request->get('mail_user');
        $mail_pass = $this->request->request->get('mail_pass');
        $mail_server = $this->request->request->get('mail_server');
        if (empty($mail_user) || empty($mail_pass) || empty($mail_server)) {
            Tools::log()->warning('no-email-credentials');
            return;
        }

        // guardamos las credenciales en la configuración
        Tools::settingsSet('email', 'ife_mail_user', $mail_user);
        Tools::settingsSet('email', 'ife_mail_pass', $mail_pass);
        Tools::settingsSet('email', 'ife_mail_server', $mail_server);
        Tools::settingsSave();

        try {
            // intentamos conectarnos al servidor de correo
            $mail_server = '{' . $mail_server . ':993/imap/ssl/novalidate-cert}INBOX';
            $inbox = imap_open($mail_server, $mail_user, $mail_pass);

            // obtenemos los emails
            $emails = imap_search($inbox, 'ALL');
            if ($emails) {
                Tools::log()->notice('emails-found', ['%total%' => count($emails)]);
                $total = 0;

                // ordena los emails del más reciente al más antiguo
                rsort($emails);
                foreach ($emails as $email_number) {

                    // si el mensaje tiene más de 1 semana, terminamos
                    $overview = imap_fetch_overview($inbox, $email_number, 0);
                    $date = strtotime($overview[0]->date);
                    if (time() - $date > 604800) {
                        break;
                    }

                    $files = [];
                    $found = false;
                    $structure = imap_fetchstructure($inbox, $email_number);

                    // verificar si el mensaje tiene partes (potencialmente adjuntos)
                    if (isset($structure->parts) && count($structure->parts)) {
                        // recorrer cada parte del mensaje
                        for ($i = 0; $i < count($structure->parts); $i++) {
                            foreach ($this->readEmailFiles($inbox, $email_number, $structure->parts[$i], $i + 1) as $found) {
                                $files[] = $found;
                                $total++;
                            }
                        }
                    }

                    if ($found) {
                        $this->mail_list[] = [
                            'date' => $date,
                            'from' => $overview[0]->from,
                            'subject' => $overview[0]->subject,
                            'files' => $files
                        ];
                    }
                }

                Tools::log()->notice('files-found', ['%total%' => $total]);
                if ($total > 0) {
                    $this->setTemplate('ImportFacturae/Emails');
                }

            } else {
                Tools::log()->notice('no-emails-found');
            }

            // cerrar conexión
            imap_close($inbox);
        } catch (Exception $e) {
            Tools::log()->error('imap-error', ['%error%' => $e->getMessage()]);
        }
    }

    protected function uploadFileAction(): void
    {
        // comprobamos los permisos de importación
        if (false === $this->permissions->allowImport) {
            Tools::log()->warning('no-import-permission');
            return;
        } elseif (false === $this->validateFormToken()) {
            return;
        }

        $codalmacen = $this->request->request->get('codalmacen');
        if (empty($codalmacen)) {
            Tools::log()->warning('no-warehouse-selected');
            return;
        }

        $codserie = $this->request->request->get('codserie');
        if (empty($codserie)) {
            Tools::log()->warning('no-serie-selected');
            return;
        }

        $upload_file = $this->request->files->get('file');
        if (empty($upload_file)) {
            Tools::log()->warning('no-file-uploaded');
            return;
        }

        if (false === FacturaeImporter::import($upload_file->getPathname(), $codalmacen, $codserie)) {
            Tools::log()->warning('import-error');
            return;
        }

        Tools::log()->notice('record-save-ok');

        $invoice = FacturaeImporter::getLastInvoice();
        if (null !== $invoice) {
            $this->redirect($invoice->url(), 1);
        }
    }
}
