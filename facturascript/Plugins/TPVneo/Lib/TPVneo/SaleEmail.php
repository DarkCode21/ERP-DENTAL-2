<?php
/**
 * Copyright (C) 2022-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TPVneo\Lib\TPVneo;

use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Model\AlbaranCliente;
use FacturaScripts\Core\Model\FacturaCliente;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Lib\ExportManager;

/**
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class SaleEmail
{
    protected static $namePdf = 'email_ticket.pdf';

    /**
     * @param AlbaranCliente|FacturaCliente $doc
     * @param string $email
     */
    public static function send($doc, string $email): bool
    {
        if (self::generatePdf($doc) === false) {
            return false;
        }

        $i18n = ToolBox::i18n();
        $customer = $doc->getSubject();
        $mail = new NewMail();
        $mail->addAddress($email, $customer->nombre);
        $mail->title = $i18n->trans($doc->modelClassName() . '-min') . ' ' . $doc->codigo;
        $mail->text = $i18n->trans('hello') . ".\n\n"
            . $i18n->trans('attachment-your', ['%doc%' => strtolower($i18n->trans($doc->modelClassName() . '-min')), '%codigo%' => $doc->codigo]) . ".\n\n"
            . $i18n->trans('thanks-greetings') . ".";
        $nameFile = $i18n->trans($doc->modelClassName() . '-min') . ' ' . $doc->codigo . '.pdf';
        $mail->addAttachment(self::$namePdf, $nameFile);

        if ($mail->send()) {
            $doc->femail = date('d-m-Y');
            $doc->save();
        }

        unlink(self::$namePdf);
        return true;
    }

    protected static function generatePdf($doc): bool
    {
        $pdf = new ExportManager();
        self::$namePdf = urlencode(strtolower($doc->codigo)) . '_' . self::$namePdf;
        $pdf->newDoc('PDF', self::$namePdf);
        $pdf->addBusinessDocPage($doc);
        return file_put_contents(self::$namePdf, $pdf->getDoc());
    }
}