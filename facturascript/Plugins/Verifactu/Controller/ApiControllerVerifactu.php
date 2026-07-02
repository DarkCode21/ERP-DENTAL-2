<?php

/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Controller;

use Symfony\Component\HttpFoundation\Response;
use FacturaScripts\Core\Template\ApiController;
use FacturaScripts\Dinamic\Model\FacturaCliente;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ApiControllerVerifactu extends ApiController
{
    /** @var string */
    private $action;

    /** @var FacturaCliente */
    private $invoice;

    protected function runResource(): void
    {
        // validamos el action
        if (!$this->validateAction()) {
            return;
        }

        // cargamos la factura
        if (!$this->loadInvoice()) {
            return;
        }

        // validamos que la factura esté enviada
        if (!$this->invoice->verifactuCheckAlta()) {
            $this->response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $this->response->setContent(json_encode([
                'status' => 'error',
                'message' => 'Invoice not sent to Verifactu',
            ]));
            return;
        }

        // si el action es qr, devolvemos el QR de la factura
        if ($this->action === 'qr') {
            $this->response->setContent($this->invoice->verifactuGetQr());
            return;
        }

        // si el action es alta, enviamos la factura a verifactu
        if ($this->action === 'alta') {
            if (!$this->invoice->verifactuAlta()) {
                $this->response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
                $this->response->setContent(json_encode([
                    'status' => 'error',
                    'message' => 'Error sending invoice to Verifactu',
                ]));
                return;
            }
            $this->response->setContent(json_encode([
                'status' => 'success',
                'message' => 'Invoice sent to Verifactu successfully',
            ]));
            return;
        }

        // si el action es subsanación, subsanamos la factura en verifactu
        if ($this->action === 'subsanacion') {
            if (!$this->invoice->verifactuSubsanacion()) {
                $this->response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
                $this->response->setContent(json_encode([
                    'status' => 'error',
                    'message' => 'Error correcting invoice in Verifactu',
                ]));
                return;
            }
            $this->response->setContent(json_encode([
                'status' => 'success',
                'message' => 'Invoice corrected in Verifactu successfully',
            ]));
            return;
        }

        // si el action es anulación, anulamos la factura en verifactu
        if ($this->action === 'anulacion') {
            if (!$this->invoice->verifactuAnulacion()) {
                $this->response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
                $this->response->setContent(json_encode([
                    'status' => 'error',
                    'message' => 'Error cancelling invoice in Verifactu',
                ]));
                return;
            }
            $this->response->setContent(json_encode([
                'status' => 'success',
                'message' => 'Invoice cancelled in Verifactu successfully',
            ]));
        }
    }

    private function loadInvoice(): bool
    {
        $this->invoice = new FacturaCliente();
        if (!$this->invoice->loadFromCode((int)$this->request->get('idfactura', 0))) {
            $this->response->setStatusCode(Response::HTTP_NOT_FOUND);
            $this->response->setContent(json_encode([
                'status' => 'error',
                'message' => 'Invoice not found',
            ]));
            return false;
        }

        return true;
    }

    private function validateAction(): bool
    {
        // si el action es qr, la llamada debe ser GET
        $this->action = strtolower($this->request->get('action', ''));
        if ($this->action === 'qr' && $this->request->getMethod() !== 'GET') {
            $this->response->setStatusCode(Response::HTTP_METHOD_NOT_ALLOWED);
            $this->response->setContent(json_encode([
                'status' => 'error',
                'message' => 'Method not allowed for QR action',
            ]));
            return false;
        }

        // si el action es alta, anulacion o subsanacion, la llamada debe ser POST
        if (in_array($this->action, ['alta', 'anulacion', 'subsanacion']) && $this->request->getMethod() !== 'POST') {
            $this->response->setStatusCode(Response::HTTP_METHOD_NOT_ALLOWED);
            $this->response->setContent(json_encode([
                'status' => 'error',
                'message' => 'Method not allowed for this action',
            ]));
            return false;
        }

        // si el action no es válido, devolvemos un error
        if (!in_array($this->action, ['qr', 'alta', 'anulacion', 'subsanacion'])) {
            $this->response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $this->response->setContent(json_encode([
                'status' => 'error',
                'message' => 'Invalid action',
            ]));
            return false;
        }

        return true;
    }
}
