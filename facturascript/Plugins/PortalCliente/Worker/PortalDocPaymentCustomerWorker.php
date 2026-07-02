<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Worker;

use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Model\WorkEvent;
use FacturaScripts\Core\Template\WorkerClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Email\ButtonBlock;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Lib\Email\SpaceBlock;
use FacturaScripts\Dinamic\Lib\Email\TextBlock;
use FacturaScripts\Dinamic\Lib\ExportManager;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalDocPaymentCustomerWorker extends WorkerClass
{
    public function run(WorkEvent $event): bool
    {
        // obtenemos los parámetros del evento
        $params = $event->params();

        // campos obligatorios
        $fields = ['model_name', 'genera_doc'];

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

        // obtenemos el cliente
        $subject = $model->getSubject();

        // si el cliente no tiene email, terminamos
        if (empty($subject->email)) {
            return $this->done();
        }

        $mail = NewMail::create()
            ->to($subject->email, $subject->nombre)
            ->addMainBlock(new TextBlock(
                Tools::lang($subject->langcode)->trans('hello') . ' ' . $subject->nombre
                . "\n\n"
                . Tools::lang($subject->langcode)->trans('email-payment-made-correctly', ['%code%' => $model->codigo, '%total%' => Tools::money($model->total, $model->coddivisa)])
            ))
            ->addMainBlock(new SpaceBlock(10));

        switch ($params['model_name']) {
            case 'AlbaranCliente':
                $mail->subject(Tools::lang($subject->langcode)->trans('delivery-note-payment-made', ['%code%' => $model->codigo]))
                    ->addMainBlock(new ButtonBlock(
                        Tools::lang($subject->langcode)->trans('view-delivery-note'),
                        Tools::siteUrl() . '/' . $model->url('public'),
                    ));
                break;

            case 'FacturaCliente':
                $mail->subject(Tools::lang($subject->langcode)->trans('invoice-payment-made', ['%code%' => $model->codigo]))
                    ->addMainBlock(new ButtonBlock(
                        Tools::lang($subject->langcode)->trans('view-invoice'),
                        Tools::siteUrl() . '/' . $model->url('public'),
                    ));
                break;

            case 'PedidoCliente':
                $mail->subject(Tools::lang($subject->langcode)->trans('order-payment-made', ['%code%' => $model->codigo]))
                    ->addMainBlock(new ButtonBlock(
                        Tools::lang($subject->langcode)->trans('view-order'),
                        Tools::siteUrl() . '/' . $model->url('public'),
                    ));
                break;

            case 'PresupuestoCliente':
                $mail->subject(Tools::lang($subject->langcode)->trans('estimation-payment-made', ['%code%' => $model->codigo]))
                    ->addMainBlock(new ButtonBlock(
                        Tools::lang($subject->langcode)->trans('view-estimation'),
                        Tools::siteUrl() . '/' . $model->url('public'),
                    ));
                break;

            default:
                return $this->done();
        }

        // comprobamos si el documento generado es una factura
        if ($params['genera_doc'] === 'FacturaCliente') {
            $invoices = $model->childrenDocuments();
            if (false === empty($invoices)) {
                $this->attachInvoice($mail, $invoices[0], $subject->langcode);
            }
        } elseif ($params['model_name'] === 'FacturaCliente') {
            $this->attachInvoice($mail, $model, $subject->langcode);
        }

        // enviamos el email
        if (false === $mail->send()) {
            return $this->done();
        }

        // actualizamos el modelo con la fecha de envío del email
        $model->femail = Tools::date();
        $model->save();

        return $this->done();
    }

    private function attachInvoice(NewMail &$mail, SalesDocument $invoice, ?string $langcode): void
    {
        $pdf = new ExportManager();
        $fileTitle = Tools::lang($langcode)->trans('invoice') . '_' . $invoice->codigo . '.pdf';
        $nameFile = str_replace('.pdf', '_' . mt_rand() . '.pdf', $fileTitle);
        $pdf->newDoc('PDF', $nameFile);
        $pdf->addBusinessDocPage($invoice);

        // si no se ha podido guardar el archivo, terminamos
        if (false === file_put_contents($nameFile, $pdf->getDoc())) {
            return;
        }

        $mail->addAttachment($nameFile, $fileTitle)
            ->addMainBlock(new SpaceBlock(10))
            ->addMainBlock(new TextBlock(Tools::lang($langcode)->trans('email-attachment-invoice', ['%code%' => $invoice->codigo, '%total%' => Tools::money($invoice->total, $invoice->coddivisa)])))
            ->addMainBlock(new SpaceBlock(10))
            ->addMainBlock(new ButtonBlock(
                Tools::lang($langcode)->trans('view-invoice'),
                Tools::siteUrl() . '/' . $invoice->url('public'),
            ));
    }
}