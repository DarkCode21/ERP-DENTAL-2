<?php

namespace FacturaScripts\Plugins\ImportFacEmail;

use FacturaScripts\Core\Template\CronClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\ImportFacEmail\Model\CorreoInformacion;

class Cron extends CronClass
{
    const JOB = 'procesar-correos';
    const PERIOD = '24 hours';

    public $urlRootFile = FS_FOLDER . "/MyFiles/XMLFacturas/";

    public function run(): void
    {
        // $this->testConexionCorreo();
        $this->job(self::JOB)
            ->every(self::PERIOD)
            ->run(function () {
                $this->procesarCorreos();
            });
    }

    function listarAdjuntosDesdeConfig()
    {
        $config = $this->getEmailConfig();
        if (!$config) {
            Tools::log()->warning('no-email-config');
            return;
        }

        $listaCorreos = [$config];

        foreach ($listaCorreos as $config) {
            $tipeserv = strtolower($config->tipeserv);
            $seguridad = ($config->port == 993 || $config->port == 995) ? 'ssl' : 'tls';
            $host = 'mail.' . explode('@', $config->nomserv)[1];
            $servidorBase = "{{$host}:{$config->port}/{$tipeserv}/{$seguridad}/novalidate-cert}";

            $inbox = @imap_open($servidorBase, $config->usuario, $config->contrasena);
            if (!$inbox) {
                Tools::log()->warning('error-connecting-email', ['%user%' => $config->usuario, '%error%' => imap_last_error()]);
                continue;
            }

            $emails = imap_search($inbox, 'ALL');
            if (!$emails) {
                Tools::log()->info('no-emails-found', ['%user%' => $config->usuario]);
                imap_close($inbox);
                continue;
            }

            rsort($emails);

            foreach ($emails as $emailNumber) {
                $estructura = imap_fetchstructure($inbox, $emailNumber);
                if (!isset($estructura->parts) || count($estructura->parts) == 0) {
                    continue;
                }

                Tools::log()->info('processing-email', ['%number%' => $emailNumber, '%user%' => $config->usuario]);

                foreach ($estructura->parts as $i => $parte) {
                    if (isset($parte->ifdparameters)) {
                        foreach ($parte->dparameters as $param) {
                            if (strtolower($param->attribute) === 'filename') {
                                echo "   📎 $param->value\n";
                            }
                        }
                    }
                }
            }

            imap_close($inbox);
        }
    }



    private function procesarCorreos()
    {
        $config = $this->getEmailConfig();
        if ($config) {
            $this->leerCorreos($config);
        }
    }

    private function getEmailConfig()
    {
        $usuario = Tools::settings('importfacemail', 'usuario');
        $contrasena = Tools::settings('importfacemail', 'contrasena');
        $tipeserv = Tools::settings('importfacemail', 'tipeserv');
        $nomserv = Tools::settings('importfacemail', 'nomserv');
        $port = Tools::settings('importfacemail', 'port');

        if (empty($usuario) || empty($contrasena) || empty($nomserv)) {
            return null;
        }

        return (object)[
            'usuario' => $usuario,
            'contrasena' => $contrasena,
            'tipeserv' => $tipeserv,
            'nomserv' => $nomserv,
            'port' => $port
        ];
    }

    private function testConexionCorreo()
    {
        $config = $this->getEmailConfig();
        if (!$config) {
            return;
        }

        $resultados = [];
        $listaCorreos = [$config];

        foreach ($listaCorreos as $config) {
            $tipeserv = strtolower($config->tipeserv);
            $seguridad = ($config->port == 993 || $config->port == 995) ? 'ssl' : 'tls';

            $servidor = "{{$config->nomserv}:{$config->port}/{$tipeserv}/{$seguridad}/novalidate-cert}";
            $usuario = $config->usuario;
            $password = $config->contrasena;

            $inbox = @imap_open($servidor . "INBOX", $usuario, $password);

            if ($inbox) {
                $carpetas = imap_list($inbox, $servidor, "*");

                $carpetas = array_map(function ($c) use ($servidor) {
                    return str_replace($servidor, '', $c);
                }, $carpetas);

                $resultados[] = [
                    'servidor' => $servidor,
                    'usuario' => $usuario,
                    'estado' => 1,
                    'mensaje' => 'Conexión exitosa',
                    'carpetas' => $carpetas
                ];
                imap_close($inbox);
            } else {
                $resultados[] = [
                    'servidor' => $servidor,
                    'usuario' => $usuario,
                    'estado' => 0,
                    'mensaje' => imap_last_error(),
                    'carpetas' => []
                ];
            }
        }

        file_put_contents(FS_FOLDER . "/MyFiles/conexion_correo.json", json_encode($resultados, JSON_PRETTY_PRINT));
    }


    private function leerCorreos($config)
    {
        $tipeserv = strtolower($config->tipeserv);
        $seguridad = ($config->port == 993 || $config->port == 995) ? 'ssl' : 'tls';

        $host = $config->nomserv;
        $servidorBase = "{{$host}:{$config->port}/{$tipeserv}/{$seguridad}/novalidate-cert}";
        $usuario = $config->usuario;
        $password = $config->contrasena;

        $conexion = @imap_open($servidorBase, $usuario, $password);
        if (!$conexion) {
            Tools::log()->error('error-connecting-email', ['%error%' => imap_last_error()]);
            return;
        }

        $carpetas = imap_list($conexion, $servidorBase, "*");
        imap_close($conexion);

        if (!$carpetas) {
            Tools::log()->error('cannot-get-folders');
            return;
        }

        $carpetasExcluidas = ['INBOX.Spam', 'INBOX.Trash', 'INBOX.Drafts', 'INBOX.Papelera', 'INBOX.Borrador'];

        foreach ($carpetas as $carpeta) {
            $nombreCarpeta = str_replace($servidorBase, '', $carpeta);

            if (in_array($nombreCarpeta, $carpetasExcluidas)) {
                continue;
            }

            $inbox = @imap_open($carpeta, $usuario, $password);

            if (!$inbox) {
                Tools::log()->error('cannot-open-folder', ['%folder%' => $nombreCarpeta, '%error%' => imap_last_error()]);
                continue;
            }

            $emails = imap_search($inbox, 'ALL');

            if ($emails) {
                rsort($emails);

                foreach ($emails as $emailNumber) {
                    $this->procesarAdjuntos($inbox, $emailNumber);
                }
            }

            imap_close($inbox);
        }
    }

    private function procesarAdjuntos($inbox, $emailNumber)
    {
        $header = imap_headerinfo($inbox, $emailNumber);
        $remitente = $header->from[0]->mailbox . "@" . $header->from[0]->host;

        $asunto = imap_mime_header_decode($header->subject);
        $asuntoFinal = "";
        foreach ($asunto as $part) {
            $asuntoFinal .= $part->text;
        }
        $asuntoFinal = trim($asuntoFinal);

        if (stripos($asuntoFinal, '***SPAM***') !== false) {
            return;
        }

        if (
            stripos($asuntoFinal, 'Undelivered Mail') !== false ||
            stripos($asuntoFinal, 'Delivery Notification') !== false ||
            stripos($asuntoFinal, 'Mail Delivery Failed') !== false
        ) {
            return;
        }

        $messageId = isset($header->message_id) ? trim($header->message_id) : md5($remitente . $header->date . $header->subject);

        $correoExistente = new CorreoInformacion();
        if ($correoExistente->existeCorreo($messageId)) {
            return;
        }

        $asuntoFinal = preg_replace('/\b(Fwd:\s*|INTERIBÉRICA:\s*)\b/i', '', $asuntoFinal);
        $asuntoFinal = trim($asuntoFinal);

        $fecha = date("Y-m-d H:i:s", strtotime($header->date));

        $contenido = imap_fetchbody($inbox, $emailNumber, 1);
        $contenido = quoted_printable_decode($contenido);
        $contenido = html_entity_decode($contenido, ENT_QUOTES | ENT_HTML401, 'UTF-8');
        
        $contenido = strip_tags($contenido);
        
        $contenido = preg_replace('/^--.*$/m', '', $contenido);
        $contenido = preg_replace('/^Content-.*?:.*$/m', '', $contenido);
        $contenido = preg_replace('/Content-.*?(\r?\n){2}/s', '', $contenido);
        $contenido = preg_replace('/[\r\n]+[-_]{5,}.*$/s', '', $contenido);
        
        $contenido = preg_replace('/\s+/', ' ', $contenido);
        $contenido = trim($contenido);

        $patrones = ["Aviso legal", "Este mensaje y cualquier de sus ficheros", "Si usted no es el destinatario"];
        foreach ($patrones as $patron) {
            $pos = stripos($contenido, $patron);
            if ($pos !== false) {
                $contenido = substr($contenido, 0, $pos);
                break;
            }
        }

        $archivoAdjunto = null;
        $estructura = imap_fetchstructure($inbox, $emailNumber);

        if (isset($estructura->parts) && count($estructura->parts) > 0) {
            for ($i = 0; $i < count($estructura->parts); $i++) {
                if ($estructura->parts[$i]->ifdparameters) {
                    $nombreAdjunto = $estructura->parts[$i]->dparameters[0]->value;

                    $contenidoAdjunto = imap_fetchbody($inbox, $emailNumber, $i + 1);

                    $encoding = $estructura->parts[$i]->encoding;
                    switch ($encoding) {
                        case 3:
                            $contenidoAdjunto = base64_decode($contenidoAdjunto);
                            break;
                        case 4:
                            $contenidoAdjunto = quoted_printable_decode($contenidoAdjunto);
                            break;
                    }

                    if (!$this->esArchivoXML($contenidoAdjunto)) {
                        continue;
                    }

                    $nameFile = $nombreAdjunto;
                    $rutaArchivo = $this->urlRootFile . $nameFile;
                    if (file_put_contents($rutaArchivo, $contenidoAdjunto) !== false) {
                        $archivoAdjunto = $nameFile;
                    } else {
                        Tools::log()->error('error-saving-file', ['%filepath%' => $rutaArchivo]);
                    }
                }
            }
        }

        $this->guardarCorreo($remitente, $asuntoFinal, $fecha, $contenido, $archivoAdjunto, $messageId);
    }



    private function esArchivoXML($contenido)
    {
        libxml_use_internal_errors(true);
        $contenido = trim($contenido);

        if (!str_starts_with($contenido, '<?xml')) {
            return false;
        }

        $xml = simplexml_load_string($contenido);
        if ($xml === false) {
            return false;
        }

        return true;
    }

    private function guardarCorreo($remitente, $asunto, $fecha, $contenido, $archivoAdjunto, $messageId)
    {
        $correo = new CorreoInformacion();
        $correo->remitente = $remitente;
        $correo->asunto = $asunto;
        $correo->fecha = $fecha;
        $correo->contenido = $contenido;
        $correo->adjunto = $archivoAdjunto;
        $correo->message_id = $messageId;

        if ($correo->save() == false) {
            Tools::log()->error('error-saving-email', ['%subject%' => $asunto]);
        }
    }
}
